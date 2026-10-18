<?php

declare(strict_types=1);

namespace Yinghua\DTO;

readonly class AttractionPage
{
    /** @param list<Attraction> $items */
    public function __construct(public array $items, public int $total, public int $page, public int $pageSize) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return ['items' => array_map(static fn(Attraction $item): array => $item->toArray(), $this->items),
            'total' => $this->total,'page' => $this->page,'pageSize' => $this->pageSize];
    }
}
