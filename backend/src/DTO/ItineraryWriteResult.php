<?php

declare(strict_types=1);

namespace Yinghua\DTO;

readonly class ItineraryWriteResult
{
    public function __construct(public ?string $itineraryId, public int $version, public string $attractionId, public bool $changed) {}

    /** @return array{itineraryId:?string,version:int,attractionId:string,changed:bool} */
    public function toArray(): array
    {
        return ['itineraryId' => $this->itineraryId, 'version' => $this->version,
            'attractionId' => $this->attractionId, 'changed' => $this->changed];
    }
}
