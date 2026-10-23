<?php

declare(strict_types=1);

namespace Yinghua\DTO;

readonly class CurrentItineraryPage
{
    /** @param list<array<string,mixed>> $items */
    public function __construct(public ?MemberItinerary $itinerary, public array $items, public ?string $nextCursor) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return ['itinerary' => $this->itinerary === null ? null : [...$this->itinerary->toArray(), 'items' => $this->items], 'nextCursor' => $this->nextCursor];
    }
}
