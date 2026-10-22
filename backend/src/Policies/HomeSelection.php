<?php

declare(strict_types=1);

namespace Yinghua\Policies;

use Yinghua\DTO\HomeContent;
use Yinghua\DTO\PublicCollection;
use Yinghua\DTO\ScreeningWindow;

/** [S：功能規格 5；E-26] 沿用原型的精選、三部電影、三則消息與排序。 */
final class HomeSelection
{
    /** @param array<string,mixed> $snapshot */
    public static function fromSnapshot(array $snapshot, ScreeningWindow $window): HomeContent
    {
        $items = PublicCollection::items($snapshot, 'items');
        $museum = array_find($items, static fn(array $item): bool => ($item['isMuseum'] ?? false) === true);
        $featured = array_find($items, static fn(array $item): bool => ($item['featured'] ?? false) === true) ?? $museum;
        $films = WeeklyFilmSelection::select(PublicCollection::items($snapshot, 'films'), $window);
        return new HomeContent($museum, $featured, array_slice($films, 0, 3), count($films), $window->toArray(), array_slice(PublicCollection::items($snapshot, 'news'), 0, 3));
    }
}
