<?php

declare(strict_types=1);

namespace Yinghua\Http;

readonly class Cookie
{
    public function __construct(public string $name, public string $value, public int $expires, public bool $secure) {}

    public function emit(): void
    {
        setcookie($this->name, $this->value, ['expires' => $this->expires, 'path' => '/',
            'secure' => $this->secure, 'httponly' => true, 'samesite' => 'Lax']);
    }
}
