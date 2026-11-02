<?php

declare(strict_types=1);

namespace Yinghua\DTO;

/** 管理員列表的資料與分頁資訊。 */
readonly class AdminPage
{
    /** @param list<AccountSummary> $items */
    public function __construct(public array $items, public int $total, public int $page, public int $pageSize) {}

    /** @return array{items:list<array<string,bool|int|string|null>>,total:int,page:int,pageSize:int} */
    public function toArray(): array
    {
        return ['items' => array_map(static fn(AccountSummary $item): array => $item->toArray(), $this->items),
            'total' => $this->total, 'page' => $this->page, 'pageSize' => $this->pageSize];
    }
}
