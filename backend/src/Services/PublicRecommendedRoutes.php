<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\DTO\PublicCollection;

class PublicRecommendedRoutes
{
    public function __construct(private readonly ContentPublication $publication) {}

    /** @return array<string,mixed> */
    public function listing(string $theme, int $page, int $pageSize): array
    {
        $snapshot = $this->publication->read();
        $items = PublicCollection::items($snapshot, 'recommendedRoutes');
        if ($theme !== '') {
            $items = array_values(array_filter($items, static fn(array $item): bool => ($item['theme'] ?? null) === $theme));
        }
        return PublicCollection::page($items, $page, $pageSize, $snapshot);
    }
}
