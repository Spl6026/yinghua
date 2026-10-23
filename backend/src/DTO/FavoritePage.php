<?php

declare(strict_types=1);

namespace Yinghua\DTO;

readonly class FavoritePage
{
    /** @param list<array{attractionId:string,savedAt:string,availability:string,attraction:array<string,mixed>|null}> $items */
    public function __construct(public array $items, public ?string $nextCursor) {}

    /** @return array{items:list<array{attractionId:string,savedAt:string,availability:string,attraction:array<string,mixed>|null}>,nextCursor:?string} */
    public function toArray(): array
    {
        return ['items' => $this->items, 'nextCursor' => $this->nextCursor];
    }
}
