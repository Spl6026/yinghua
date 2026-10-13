<?php

declare(strict_types=1);

namespace Yinghua;

use RuntimeException;

/** [U-01／E-08] 設定只有一個來源，秘密由 Docker 的 .env 提供。 */
readonly class Config
{
    public function string(string $key, string $default = ''): string
    {
        $value = getenv($key);

        return $value === false ? $default : $value;
    }

    public function integer(string $key, int $default): int
    {
        $value = $this->string($key, (string) $default);
        if (!ctype_digit($value) || (int) $value < 1) {
            throw new RuntimeException('Invalid integer configuration: ' . $key);
        }

        return (int) $value;
    }

    public function secret(): string
    {
        $key = $this->string('APP_KEY');
        if (strlen($key) < 32) {
            throw new RuntimeException('APP_KEY must contain at least 32 characters.');
        }

        return $key;
    }

    public function secureCookies(): bool
    {
        return $this->string('SESSION_SECURE', 'false') === 'true';
    }

    public function origin(): string
    {
        $url = rtrim($this->string('APP_URL', 'http://localhost:8080'), '/');
        $parts = parse_url($url);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])
            || !in_array($parts['scheme'], ['http', 'https'], true)
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])
            || (isset($parts['path']) && $parts['path'] !== '')) {
            throw new RuntimeException('APP_URL must be an HTTP origin.');
        }
        if ($this->string('APP_ENV', 'local') === 'production'
            && ($parts['scheme'] !== 'https' || !$this->secureCookies())) {
            throw new RuntimeException('Production requires HTTPS and Secure cookies.');
        }

        return $url;
    }
}
