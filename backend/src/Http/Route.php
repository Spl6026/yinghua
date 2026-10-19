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
        public bool $allowPreviewControl = false,
    ) {}

    /** @return array<string,string>|null */
    public function match(string $path): ?array
    {
        $expression = str_replace(preg_quote('{id}', '~'), '(?P<id>[a-f0-9-]{36})', preg_quote($this->path, '~'));
        $expression = str_replace(preg_quote('{slug}', '~'), '(?P<slug>[a-z0-9][a-z0-9-]{0,159})', $expression);
        if (!preg_match('~^' . $expression . '$~D', $path, $matches)) {
            return null;
        }

        $parameters = [];
        foreach (['id','slug'] as $name) {
            if (isset($matches[$name])) {
                $parameters[$name] = $matches[$name];
            }
        }

        return $parameters;
    }
}
