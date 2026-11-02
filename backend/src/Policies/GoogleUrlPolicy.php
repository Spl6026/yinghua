<?php

declare(strict_types=1);

namespace Yinghua\Policies;

use Yinghua\Exceptions\ApplicationError;

/** Google 地圖網址檢查：比對精確的網域與協定白名單，不用「字串包含 google」這種寬鬆判斷。 */
class GoogleUrlPolicy
{
    public function validate(string $url, bool $api = false): string
    {
        $parts = parse_url($url);
        $hosts = $api ? ['places.googleapis.com'] : ['maps.app.goo.gl','goo.gl','www.google.com','maps.google.com','www.google.com.tw'];
        if ($parts === false || strlen($url) > 2000 || preg_match('/[\x00-\x20\\\\]/', $url)
            || ($parts['scheme'] ?? '') !== 'https' || !in_array($parts['host'] ?? '', $hosts, true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])
            || (isset($parts['port']) && $parts['port'] !== 443)) {
            throw ApplicationError::invalid('mapUrl', '僅接受可信任的 HTTPS Google 地圖網址。');
        }
        $host = $parts['host'];
        $path = $parts['path'] ?? '/';
        if ((!$api && $host === 'goo.gl' && !str_starts_with($path, '/maps/'))
            || (!$api && in_array($host, ['www.google.com','www.google.com.tw'], true) && !str_starts_with($path, '/maps'))
            || ($api && !str_starts_with($path, '/v1/places'))) {
            throw ApplicationError::invalid('mapUrl', '此網址不是支援的 Google 地圖入口。');
        }

        return $host;
    }

    public function publicIp(string $ip): bool
    {
        $parts = explode('.', $ip);
        $shared = ($parts[0] ?? '') === '100' && (int) ($parts[1] ?? 0) >= 64 && (int) ($parts[1] ?? 0) <= 127;

        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false
            && !$shared && (int) $parts[0] < 224;
    }
}
