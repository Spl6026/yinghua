<?php

declare(strict_types=1);

namespace Yinghua\Http;

use Yinghua\Config;
use Yinghua\Contracts\Clock;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\IdentityKind;
use Yinghua\Support\Secrets;

/** [S-SEC-09／E-02] 匿名簽章 Cookie 與登入 token 分離，且每種身分各自綁定。 */
class Csrf
{
    public function __construct(private readonly Config $config, private readonly Clock $clock, private readonly Secrets $secrets) {}

    public function context(Request $request, IdentityKind $kind): CsrfContext
    {
        $value = $request->cookies[$this->anonymousCookie($kind)] ?? '';
        $parts = explode('.', $value);
        if (count($parts) === 3 && preg_match('/^[a-f0-9]{64}$/D', $parts[0]) && ctype_digit($parts[1])
            && (int) $parts[1] > $this->clock->now()
            && hash_equals($this->secrets->keyed('anonymous-' . $kind->value, $parts[0] . '.' . $parts[1]), $parts[2])) {
            return new CsrfContext($parts[0], $value, false);
        }

        return $this->rotate($kind);
    }

    public function rotate(IdentityKind $kind): CsrfContext
    {
        $id = Secrets::token();
        $value = $id . '.' . ($this->clock->now() + $this->config->integer('CSRF_SECONDS', 3600));

        return new CsrfContext($id, $value . '.' . $this->secrets->keyed('anonymous-' . $kind->value, $value), true);
    }

    public function token(CsrfContext $context, IdentityKind $kind): string
    {
        $expires = $this->clock->now() + $this->config->integer('CSRF_SECONDS', 3600);

        return $expires . '.' . $this->secrets->keyed('csrf-' . $kind->value, $context->id . ':' . $expires);
    }

    public function verify(Request $request, CsrfContext $context, IdentityKind $kind): void
    {
        $origin = $request->headers['origin'] ?? null;
        if (($origin !== null && $origin !== $this->config->origin())
            || ($request->headers['sec-fetch-site'] ?? '') === 'cross-site') {
            throw new ApplicationError('CSRF_INVALID', '請由本站送出操作。');
        }
        $parts = explode('.', $request->headers['x-csrf-token'] ?? '');
        if ($context->newCookie || count($parts) !== 2 || !ctype_digit($parts[0])
            || (int) $parts[0] <= $this->clock->now()
            || (int) $parts[0] > $this->clock->now() + $this->config->integer('CSRF_SECONDS', 3600)
            || !hash_equals($this->secrets->keyed('csrf-' . $kind->value, $context->id . ':' . $parts[0]), $parts[1])) {
            throw new ApplicationError('CSRF_INVALID', '頁面驗證已過期，請重試。');
        }
    }

    public function cookie(CsrfContext $context, IdentityKind $kind): Cookie
    {
        return new Cookie(
            $this->anonymousCookie($kind),
            $context->cookieValue,
            $this->clock->now() + $this->config->integer('CSRF_SECONDS', 3600),
            $this->config->secureCookies(),
        );
    }

    public function authCookie(IdentityKind $kind, string $token, bool $clear = false): Cookie
    {
        return new Cookie(
            'spl_' . $kind->value . '_session',
            $token,
            $clear ? 1 : $this->clock->now() + $this->config->integer('AUTH_SESSION_SECONDS', 43200),
            $this->config->secureCookies(),
        );
    }

    public function sessionToken(Request $request, IdentityKind $kind): string
    {
        return $request->cookies['spl_' . $kind->value . '_session'] ?? '';
    }

    private function anonymousCookie(IdentityKind $kind): string
    {
        return 'spl_' . $kind->value . '_anonymous';
    }
}
