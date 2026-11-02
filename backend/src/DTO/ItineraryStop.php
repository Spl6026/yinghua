<?php

declare(strict_types=1);

namespace Yinghua\DTO;

/** snapshotName 是加入行程當下的公開景點名稱，之後景點草稿怎麼改都不影響。 */
readonly class ItineraryStop
{
    public function __construct(
        public string $id,
        public string $attractionId,
        public int $sortOrder,
        public ?string $plannedTime,
        public string $note,
        public string $snapshotName,
    ) {}
}
