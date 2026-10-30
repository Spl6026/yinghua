<?php

declare(strict_types=1);

use Yinghua\Http\{Router, Route};
use Yinghua\Models\IdentityKind;
use Yinghua\Controllers\MemberItineraryController;

/** [S H15；E-33] 在P0 current靜態路由之後註冊，所有操作強制會員session。 */
return static function (Router $router, MemberItineraryController $trips): void {
    $kind = IdentityKind::Member;
    $base = '/api/v1/me/itineraries';
    $router->add(new Route('GET', $base, $kind, $trips->listing(...), true));
    $router->add(new Route('POST', $base, $kind, $trips->create(...), true));
    $router->add(new Route('GET', $base . '/{id}', $kind, $trips->get(...), true));
    $router->add(new Route('PUT', $base . '/{id}', $kind, $trips->update(...), true));
    $router->add(new Route('DELETE', $base . '/{id}', $kind, $trips->delete(...), true));
    $router->add(new Route('PUT', $base . '/{id}/current', $kind, $trips->select(...), true));
    $router->add(new Route('POST', $base . '/{id}/routes', $kind, $trips->apply(...), true));
};
