<?php

declare(strict_types=1);

use Yinghua\Http\{Router, Route};
use Yinghua\Models\IdentityKind;
use Yinghua\Controllers\RecommendedRouteController;

return static function (Router $router, RecommendedRouteController $routes): void {
    $kind = IdentityKind::Admin;
    $router->add(new Route('GET', '/api/v1/routes', IdentityKind::Member, $routes->published(...)));
    $router->add(new Route('GET', '/api/v1/admin/routes/attractions', $kind, $routes->candidates(...), true));
    $router->add(new Route('GET', '/api/v1/admin/routes', $kind, $routes->listing(...), true, 'routes', 'view'));
    $router->add(new Route('POST', '/api/v1/admin/routes', $kind, $routes->create(...), true, 'routes', 'create'));
    $router->add(new Route('GET', '/api/v1/admin/routes/{id}', $kind, $routes->get(...), true));
    $router->add(new Route('PUT', '/api/v1/admin/routes/{id}', $kind, $routes->update(...), true, 'routes', 'edit'));
    $router->add(new Route('DELETE', '/api/v1/admin/routes/{id}', $kind, $routes->delete(...), true, 'routes', 'delete'));
};
