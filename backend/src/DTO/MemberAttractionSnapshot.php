<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use Yinghua\Models\AttractionAvailability;

readonly class MemberAttractionSnapshot
{
    /** @param array<string,array<string,mixed>> $items */
    public function __construct(public bool $ready, public array $items) {}

    /** @return array{availability:string,attraction:array<string,mixed>|null} */
    public function view(string $id): array
    {
        $item = $this->items[$id] ?? null;
        $availability = !$this->ready
            ? AttractionAvailability::Unknown
            : ($item === null ? AttractionAvailability::Unavailable : AttractionAvailability::Available);

        return ['availability' => $availability->value, 'attraction' => $item];
    }
}
