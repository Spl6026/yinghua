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

    /** 提供會員套用前預覽的路線資訊，只讀成功發布的公開投影。
     * @return array<string,mixed>
     */
    public function get(string $id): array
    {
        \Yinghua\DTO\ContentFields::uuid($id, 'id');
        $snapshot = $this->publication->read();
        foreach (PublicCollection::items($snapshot, 'recommendedRoutes') as $route) {
            if (($route['id'] ?? null) === $id) {
                return ['route' => $route,'generation' => $snapshot['generation'] ?? ''];
            }
        }
        throw new \Yinghua\Exceptions\ApplicationError('NOT_FOUND', '找不到此公開推薦路線。');
    }
}
