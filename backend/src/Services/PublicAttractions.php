<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\Exceptions\ApplicationError;

/** [S：功能規格 6/13；E-18] 公開 API 與 HTML 共用成功發布的資料版本。 */
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
        $counts = ['all' => 0,'景點' => 0,'美食' => 0,'在地店家' => 0];
        foreach ($this->items($snapshot) as $item) {
            $key = $item['category'] ?? '';
            if (is_string($key) && array_key_exists($key, $counts)) {
                $counts[$key]++;
                $counts['all']++;
            }
        }
        $items = array_values(array_filter($this->items($snapshot), static function (array $item) use ($q, $category, $district): bool {
            return ($category === '' || ($item['category'] ?? null) === $category)
                && ($district === '' || ($item['district'] ?? null) === $district)
                && ($q === '' || mb_stripos(implode(' ', array_filter([$item['name'] ?? '',$item['summary'] ?? '',$item['typeLabel'] ?? ''], is_string(...))), $q) !== false);
        }));

        return ['items' => array_slice($items, ($page - 1) * $size, $size),'total' => count($items),'page' => $page,'pageSize' => $size,'counts' => $counts,'generation' => $snapshot['generation']];
    }

    /** @return array<string,mixed> */
    public function get(string $slug): array
    {
        foreach ($this->items($this->publication->read()) as $item) {
            if (($item['slug'] ?? null) === $slug) {
                return ['attraction' => $item];
            }
        }
        throw new ApplicationError('NOT_FOUND', '找不到此公開景點。');
    }

    /** @return array<string,mixed> */
    public function museum(): array
    {
        foreach ($this->items($this->publication->read()) as $item) {
            if (($item['isMuseum'] ?? false) === true) {
                return ['attraction' => $item];
            }
        }
        throw new ApplicationError('NOT_FOUND', '目前沒有已發布的博物館介紹。');
    }

    /** @param array<string,mixed> $snapshot
     * @return list<array<string,mixed>>
     */
    private function items(array $snapshot): array
    {
        $items = $snapshot['items'] ?? [];
        if (!is_array($items)) {
            throw new \RuntimeException('Invalid public projection.');
        }
        $result = [];
        foreach ($items as $item) {
            if (!is_array($item)) {
                throw new \RuntimeException('Invalid public item.');
            }
            $row = [];
            foreach ($item as $key => $value) {
                if (is_string($key)) {
                    $row[$key] = $value;
                }
            }
            $result[] = $row;
        }

        return $result;
    }
}
