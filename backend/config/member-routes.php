<?php

declare(strict_types=1);

use Yinghua\Controllers\FavoriteController;
use Yinghua\Controllers\CurrentItineraryController;
use Yinghua\Http\Route;
use Yinghua\Http\Router;
use Yinghua\Models\IdentityKind;

/** [S：E13／G15；E-27] 本人資料一律用會員 session，不掛管理員 RBAC。 */
return static function (Router $router, FavoriteController $favorites, CurrentItineraryController $itinerary): void {
    $kind = IdentityKind::Member;
    $router->add(new Route('GET', '/api/v1/me/favorites', $kind, $favorites->listing(...), true));
    $router->add(new Route('GET', '/api/v1/me/favorites/status', $kind, $favorites->status(...), true));
    $router->add(new Route('PUT', '/api/v1/me/favorites/{attractionId}', $kind, $favorites->add(...), true));
    $router->add(new Route('DELETE', '/api/v1/me/favorites/{attractionId}', $kind, $favorites->remove(...), true));
    $router->add(new Route('GET', '/api/v1/me/itineraries/current', $kind, $itinerary->listing(...), true));
    $router->add(new Route('PUT', '/api/v1/me/itineraries/current/items/{attractionId}', $kind, $itinerary->add(...), true));
    $router->add(new Route('DELETE', '/api/v1/me/itineraries/current/items/{attractionId}', $kind, $itinerary->remove(...), true));
};
