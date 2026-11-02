<?php

declare(strict_types=1);

namespace Yinghua\Policies;

use Yinghua\DTO\RecommendedRoute;

/** 公開推薦路線時略過已下架的站點；所有站點都不可用時，整條路線不公開。 */
final class RecommendedRouteSelection
{
    /** @param list<RecommendedRoute> $routes
     * @param list<array<string,mixed>> $attractions
     * @return list<array<string,mixed>>
     */
    public static function project(array $routes, array $attractions): array
    {
        $places = [];
        foreach ($attractions as $place) {
            if (is_string($place['id'] ?? null)) {
                $places[$place['id']] = $place;
            }
        }
        $items = [];
        foreach ($routes as $route) {
            $stops = [];
            foreach ($route->stops as $stop) {
                $place = $places[$stop['attractionId']] ?? null;
                if ($place !== null) {
                    $stops[] = [...$stop,'sortOrder' => count($stops),'url' => $place['url'] ?? '','name' => $place['name'] ?? ''];
                }
            }
            if ($stops !== []) {
                $data = $route->toArray();
                unset($data['version']);
                $items[] = [...$data,'stops' => $stops];
            }
        }
        return $items;
    }
}
