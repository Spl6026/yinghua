<?php

declare(strict_types=1);

namespace Yinghua\Policies;

use Yinghua\DTO\RecommendedRoute;

/** [S G14；D API草案 G14；E-32] 跳過下架站點；全數不可用時不公開路線。 */
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
