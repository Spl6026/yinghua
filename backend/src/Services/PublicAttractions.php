<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\DTO\PublicCollection;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Exceptions\ErrorCode;

/** 公開景點查詢；API 與靜態 HTML 讀的是同一份成功發布的快照。 */
class PublicAttractions
{
    public function __construct(private readonly ContentPublication $publication) {}

    /** @return array<string,mixed> */
    public function listing(string $q, string $category, string $district, int $page, int $size): array
    {
        if (!in_array($category, ['','景點','美食','在地店家'], true)) {
            throw ApplicationError::invalid('category', '不支援的分類。');
        }
        if (!in_array($district, ['','北區','東區','香山區'], true)) {
            throw ApplicationError::invalid('district', '不支援的行政區。');
        }
        $snapshot = $this->publication->read();
        $all = PublicCollection::items($snapshot, 'items');
        $counts = ['all' => 0,'景點' => 0,'美食' => 0,'在地店家' => 0];
        foreach ($all as $item) {
            $key = $item['category'] ?? '';
            if (is_string($key) && array_key_exists($key, $counts)) {
                $counts[$key]++;
                $counts['all']++;
            }
        }
        $items = array_values(array_filter($all, static function (array $item) use ($q, $category, $district): bool {
            return ($category === '' || ($item['category'] ?? null) === $category)
                && ($district === '' || ($item['district'] ?? null) === $district)
                && ($q === ''
                    || mb_stripos(implode(' ', array_filter([$item['name'] ?? '',
                        $item['summary'] ?? '',
                        $item['typeLabel'] ?? ''], is_string(...))), $q) !== false);
        }));

        return ['items' => array_slice($items, ($page - 1) * $size, $size),
            'total' => count($items),
            'page' => $page,
            'pageSize' => $size,
            'counts' => $counts,
            'generation' => $snapshot['generation']];
    }

    /** @return array<string,mixed> */
    public function get(string $slug): array
    {
        foreach (PublicCollection::items($this->publication->read(), 'items') as $item) {
            if (($item['slug'] ?? null) === $slug) {
                return ['attraction' => $item];
            }
        }
        throw new ApplicationError(ErrorCode::NotFound, '找不到此公開景點。');
    }

    /** @return array<string,mixed> */
    public function museum(): array
    {
        foreach (PublicCollection::items($this->publication->read(), 'items') as $item) {
            if (($item['isMuseum'] ?? false) === true) {
                return ['attraction' => $item];
            }
        }
        throw new ApplicationError(ErrorCode::NotFound, '目前沒有已發布的博物館介紹。');
    }
}
