<?php

declare(strict_types=1);

namespace Yinghua\Http;

use Closure;
use Yinghua\Models\IdentityKind;

readonly class Route
{
    /** @param Closure(ApiContext):Response $handler */
    public function __construct(
        public string $method,
        public string $path,
        public IdentityKind $kind,
        public Closure $handler,
        public bool $authenticated = false,
        public ?string $module = null,
        public ?string $action = null,
        public bool $allowPasswordChange = false,
    ) {}

    /** @return array<string,string>|null */
    public function match(string $path): ?array
    {
        $expression = str_replace(preg_quote('{id}', '~'), '(?P<id>[a-f0-9-]{36})', preg_quote($this->path, '~'));
        if (!preg_match('~^' . $expression . '$~D', $path, $matches)) {
            return null;
        }

        return isset($matches['id']) ? ['id' => $matches['id']] : [];
    }
}
