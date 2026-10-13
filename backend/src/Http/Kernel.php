<?php

declare(strict_types=1);

namespace Yinghua\Http;

use Throwable;
use Yinghua\DTO\ClientContext;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Services\Authentication;
use Yinghua\Services\Authorization;
use Yinghua\Support\Secrets;
use Yinghua\Repositories\EventRepository;

/** [E-01] 共用管線：錯誤 → 路由 → CSRF → 身分 → 權限 → Controller。 */
class Kernel
{
    public function __construct(
        private readonly Router $router,
        private readonly Csrf $csrf,
        private readonly Authentication $authentication,
        private readonly Authorization $authorization,
        private readonly Secrets $secrets,
        private readonly EventRepository $events,
    ) {}

    public function handle(Request $request): Response
    {
        $context = null;
        try {
            $match = $this->router->resolve($request);
            $route = $match['route'];
            $anonymous = $this->csrf->context($request, $route->kind);
            $client = new ClientContext(
                $anonymous->id,
                $this->secrets->keyed('ip', $request->ip),
                $this->secrets->keyed('agent', $request->userAgent),
                $request->requestId,
            );
            $token = $this->csrf->sessionToken($request, $route->kind);
            $actor = $route->authenticated ? $this->authentication->current($route->kind, $token) : null;
            $context = new ApiContext($request, $route->kind, $client, $anonymous, $actor, $token, $match['parameters']);
            if (!in_array($request->method, ['GET', 'HEAD'], true)) {
                $this->csrf->verify($request, $anonymous, $route->kind);
            }
            if ($actor !== null && $actor->mustChangePassword && !$route->allowPasswordChange) {
                throw new ApplicationError('PASSWORD_CHANGE_REQUIRED', '請先變更初始密碼。');
            }
            if ($actor !== null && $route->module !== null && $route->action !== null) {
                $this->authorization->require($actor, $route->module, $route->action);
            }
            $response = ($route->handler)($context);

            return $anonymous->newCookie ? $response->withCookie($this->csrf->cookie($anonymous, $route->kind)) : $response;
        } catch (ApplicationError $error) {
            if ($context !== null && in_array($error->errorCode, ['FORBIDDEN', 'CSRF_INVALID', 'PASSWORD_CHANGE_REQUIRED'], true)) {
                $this->events->auth('access-denied', false, $error->errorCode, $context->kind, $context->account, '', $context->client);
            }

            return self::error($error);
        } catch (Throwable $error) {
            error_log('request=' . $request->requestId . ' exception=' . $error::class);

            return self::error(new ApplicationError('INTERNAL_ERROR', '系統暫時無法完成操作，請稍後再試。'));
        }
    }

    public static function error(ApplicationError $error): Response
    {
        $status = match ($error->errorCode) {
            'BAD_REQUEST' => 400,
            'UNAUTHENTICATED', 'INVALID_CREDENTIALS' => 401,
            'FORBIDDEN', 'CSRF_INVALID', 'PASSWORD_CHANGE_REQUIRED' => 403,
            'NOT_FOUND' => 404,
            'METHOD_NOT_ALLOWED' => 405,
            'CONFLICT' => 409,
            'PAYLOAD_TOO_LARGE' => 413,
            'UNSUPPORTED_MEDIA_TYPE' => 415,
            'VALIDATION_FAILED' => 422,
            'RATE_LIMITED' => 429,
            'MAIL_UNAVAILABLE' => 503,
            default => 500,
        };
        $headers = [];
        if ($error->retryAfter !== null) {
            $headers['Retry-After'] = (string) $error->retryAfter;
        }
        if ($status === 405) {
            $headers['Allow'] = $error->getMessage();
        }

        return new Response($status, ['code' => $error->errorCode,
            'message' => $status === 405 ? '不支援的 HTTP 方法。' : $error->getMessage(), 'fieldErrors' => (object) $error->fieldErrors], [], $headers);
    }
}
