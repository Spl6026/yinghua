<?php

declare(strict_types=1);

namespace Yinghua\Http;

use Yinghua\Exceptions\ApplicationError;
use Yinghua\Exceptions\ErrorCode;

class Router
{
    /** @var list<Route> */
    private array $routes = [];

    public function add(Route $route): void
    {
        $this->routes[] = $route;
    }

    /** @return array{route:Route,parameters:array<string,string>} */
    public function resolve(Request $request): array
    {
        $allowed = [];
        foreach ($this->routes as $route) {
            $parameters = $route->match($request->path);
            if ($parameters === null) {
                continue;
            }
            $allowed[] = $route->method;
            if ($route->method === $request->method || ($request->method === 'HEAD' && $route->method === 'GET')) {
                return ['route' => $route, 'parameters' => $parameters];
            }
        }
        if ($allowed !== []) {
            throw new ApplicationError(ErrorCode::MethodNotAllowed, implode(', ', $allowed));
        }
        throw new ApplicationError(ErrorCode::NotFound, '找不到此 API。');
    }
}
