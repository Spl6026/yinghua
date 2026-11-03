<?php

declare(strict_types=1);

namespace Yinghua\Http;

use Yinghua\Config;
use Yinghua\Contracts\Clock;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\IdentityKind;
use Yinghua\Support\Secrets;
use Yinghua\Exceptions\ErrorCode;

/** CSRF 防護：匿名階段用簽章 Cookie，和登入 token 分開，會員與管理員各自綁定。 */
class Csrf
{
    // 容許虛擬機時鐘有幾秒誤差；不會延長到期時間，也不接受沒有簽章的 token。
    private const int CLOCK_SKEW_SECONDS = 5;

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
        $value = $id . '.' . ($this->clock->now() + $this->config->csrfSeconds());

        return new CsrfContext($id, $value . '.' . $this->secrets->keyed('anonymous-' . $kind->value, $value), true);
    }

    public function token(CsrfContext $context, IdentityKind $kind): string
    {
        $expires = $this->clock->now() + $this->config->csrfSeconds();

        return $expires . '.' . $this->secrets->keyed('csrf-' . $kind->value, $context->id . ':' . $expires);
    }

    public function verify(Request $request, CsrfContext $context, IdentityKind $kind): void
    {
        $origin = $request->headers['origin'] ?? null;
        if (($origin !== null && $origin !== $this->config->origin())
            || ($request->headers['sec-fetch-site'] ?? '') === 'cross-site') {
            $this->reject($request, 'origin', '請由本站送出操作。');
        }
        $parts = explode('.', $request->headers['x-csrf-token'] ?? '');
        $now = $this->clock->now();
        $reason = match (true) {
            $context->newCookie => 'anonymous-cookie',
            count($parts) !== 2 || !ctype_digit($parts[0]) => 'format',
            (int) $parts[0] <= $now => 'expired',
            (int) $parts[0] > $now + $this->config->csrfSeconds() + self::CLOCK_SKEW_SECONDS => 'future',
            !hash_equals($this->secrets->keyed('csrf-' . $kind->value, $context->id . ':' . $parts[0]), $parts[1]) => 'signature',
            default => null,
        };
        if ($reason !== null) {
            $this->reject($request, $reason, '頁面驗證已過期，請重試。');
        }
    }

    private function reject(Request $request, string $reason, string $message): never
    {
        // 日誌只記 requestId 與固定的原因代碼，不可寫入 Cookie、token、個資或 request body。
        error_log('request=' . $request->requestId . ' csrf_reason=' . $reason);
        throw new ApplicationError(ErrorCode::CsrfInvalid, $message);
    }

    public function cookie(CsrfContext $context, IdentityKind $kind): Cookie
    {
        return new Cookie(
            $this->anonymousCookie($kind),
            $context->cookieValue,
            $this->clock->now() + $this->config->csrfSeconds(),
            $this->config->secureCookies(),
        );
    }

    public function authCookie(IdentityKind $kind, string $token, bool $clear = false): Cookie
    {
        return new Cookie(
            'spl_' . $kind->value . '_session',
            $token,
            $clear ? 1 : $this->clock->now() + $this->config->sessionSeconds(),
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
