<?php

declare(strict_types=1);

namespace Yinghua\Http;

use Throwable;
use Yinghua\DTO\ClientContext;
use Yinghua\DTO\AuthenticatedIdentity;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Exceptions\PublicationPending;
use Yinghua\Services\Authentication;
use Yinghua\Services\Authorization;
use Yinghua\Support\Secrets;
use Yinghua\Repositories\EventRepository;
use Yinghua\Models\IdentityKind;

/** 所有 API 請求共用的處理流程：錯誤處理 → 路由 → CSRF → 身分 → 權限 → Controller。 */
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
            $identity = $this->identity($route, $token);
            $actor = $identity?->effective();
            $context = new ApiContext($request, $route->kind, $client, $anonymous, $actor, $token, $match['parameters'], $identity);
            if (!in_array($request->method, ['GET', 'HEAD'], true)) {
                $this->csrf->verify($request, $anonymous, $route->kind);
            }
            // 模擬檢視時一律唯讀，在這裡統一擋下；退出模擬與登出不受被模擬者的權限影響。
            if ($identity?->isPreview() && !$route->allowPreviewControl) {
                if (!in_array($request->method, ['GET', 'HEAD'], true)) {
                    throw new ApplicationError('PREVIEW_READ_ONLY', '目前為唯讀模擬，請先退出模擬再修改資料。');
                }
                if (!$identity->previewAvailable()) {
                    throw new ApplicationError('FORBIDDEN', '模擬對象已停用或刪除，請退出模擬。');
                }
            }
            if ($actor !== null && $route->module !== null && $route->action !== null) {
                $this->authorization->require($actor, $route->module, $route->action);
            }
            $response = ($route->handler)($context);
            $response = $this->withSessionTiming($response, $context);

            return $anonymous->newCookie ? $response->withCookie($this->csrf->cookie($anonymous, $route->kind)) : $response;
        } catch (ApplicationError $error) {
            if ($error instanceof PublicationPending) {
                error_log('request=' . $request->requestId . ' ' . $error->diagnostic());
            }
            if ($context !== null && in_array($error->errorCode, ['FORBIDDEN', 'CSRF_INVALID', 'PREVIEW_READ_ONLY'], true)) {
                $this->events->auth(
                    'access-denied',
                    false,
                    $error->errorCode,
                    $context->kind,
                    $context->identity?->owner,
                    '',
                    $context->client,
                );
            }

            return $this->withSessionTiming(self::error($error), $context);
        } catch (Throwable $error) {
            error_log('request=' . $request->requestId . ' exception=' . $error::class);

            return self::error(new ApplicationError('INTERNAL_ERROR', '系統暫時無法完成操作，請稍後再試。'));
        }
    }

    /** 依實際驗證過的 session 回傳到期時間，瀏覽器無法自行宣告延長。 */
    private function withSessionTiming(Response $response, ?ApiContext $context): Response
    {
        if ($context?->identity === null || $response->status === 401) {
            return $response;
        }
        $timing = $context->identity->timing;

        return $response->withHeaders([
            'X-Session-Kind' => $context->kind->value,
            'X-Session-Owner' => $context->identity->owner->id,
            'X-Session-Server-Time' => (string) $timing->serverTime,
            'X-Session-Expires-At' => (string) $timing->expiresAt,
            'X-Session-Idle-Expires-At' => (string) $timing->idleExpiresAt,
        ]);
    }

    public static function error(ApplicationError $error): Response
    {
        $status = match ($error->errorCode) {
            'BAD_REQUEST' => 400,
            'UNAUTHENTICATED', 'INVALID_CREDENTIALS' => 401,
            'FORBIDDEN', 'CSRF_INVALID', 'PREVIEW_READ_ONLY' => 403,
            'NOT_FOUND' => 404,
            'METHOD_NOT_ALLOWED' => 405,
            'CONFLICT' => 409,
            'PAYLOAD_TOO_LARGE' => 413,
            'UNSUPPORTED_MEDIA_TYPE' => 415,
            'VALIDATION_FAILED' => 422,
            'RATE_LIMITED' => 429,
            'SERVICE_UNAVAILABLE', 'MAIL_UNAVAILABLE', 'PUBLICATION_PENDING', 'MAP_UNAVAILABLE' => 503,
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
            'message' => $status === 405 ? '不支援的 HTTP 方法。' : $error->getMessage(),
            'fieldErrors' => (object) $error->fieldErrors], [], $headers);
    }

    private function identity(Route $route, string $token): ?AuthenticatedIdentity
    {
        if ($route->authenticated) {
            return $this->authentication->current($route->kind, $token);
        }
        // 模擬檢視中，連不需登入的管理員端點（登入、改密碼）也不能寫入；但失效的 Cookie 不能擋住正常登入。
        if ($route->kind !== IdentityKind::Admin || $token === '') {
            return null;
        }
        try {
            $identity = $this->authentication->current($route->kind, $token);

            return $identity->isPreview() ? $identity : null;
        } catch (ApplicationError $error) {
            if ($error->errorCode !== 'UNAUTHENTICATED') {
                throw $error;
            }

            return null;
        }
    }
}
