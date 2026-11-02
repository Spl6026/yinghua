<?php

declare(strict_types=1);

use Yinghua\Controllers\FavoriteController;
use Yinghua\Controllers\CurrentItineraryController;
use Yinghua\Http\Route;
use Yinghua\Http\Router;
use Yinghua\Models\IdentityKind;

/** 收藏與目前行程都是會員本人的資料，只認會員 session，不套用管理員的權限設定。 */
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
