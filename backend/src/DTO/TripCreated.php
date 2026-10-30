<?php

declare(strict_types=1);

namespace Yinghua\DTO;

readonly class TripCreated
{
    public function __construct(public TripView $itinerary, public bool $created) {}
}
