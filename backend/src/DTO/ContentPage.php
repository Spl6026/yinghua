<?php

declare(strict_types=1);

namespace Yinghua\DTO;

readonly class ContentPage
{
    /** @param list<NewsPost|Offer|Merchant> $items */
    public function __construct(public array $items, public int $total, public int $page, public int $pageSize) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return ['items' => array_map(static fn(NewsPost|Offer|Merchant $record): array => (new ContentRecordView($record))->toArray(), $this->items),
            'total' => $this->total, 'page' => $this->page, 'pageSize' => $this->pageSize];
    }
}
