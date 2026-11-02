<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\DTO\SearchQuery;
use Yinghua\Repositories\SearchRepository;

class PublicSearch
{
    public function __construct(private readonly ContentPublication $publication, private readonly SearchRepository $records) {}

    /** 與發布及索引清理共用同一把鎖，確保總數與分頁結果來自同一個 generation。
     * @return array<string,mixed>
     */
    public function listing(SearchQuery $query): array
    {
        return $this->publication->exclusive(function () use ($query): array {
            $snapshot = $this->publication->read();
            $generation = is_string($snapshot['generation'] ?? null) ? $snapshot['generation'] : '';
            return trim($query->q) === ''
                ? ['items' => [],'total' => 0,'page' => $query->page,'pageSize' => $query->pageSize,'generation' => $generation]
                : $this->records->search($generation, $query);
        });
    }
}
