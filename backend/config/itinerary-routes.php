<?php

declare(strict_types=1);

use Yinghua\Http\{Router, Route};
use Yinghua\Models\IdentityKind;
use Yinghua\Controllers\MemberItineraryController;

/** 會員的多份行程路由；必須註冊在 current 靜態路由之後，所有操作都要求會員登入。 */
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
