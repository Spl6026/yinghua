<?php

declare(strict_types=1);

namespace Yinghua\DTO;

readonly class TripView
{
    /** @param list<array<string,mixed>> $items */
    public function __construct(public MemberItinerary $itinerary, public bool $isCurrent, public array $items) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [...$this->itinerary->toArray(),'isCurrent' => $this->isCurrent,'items' => $this->items];
    }
}
