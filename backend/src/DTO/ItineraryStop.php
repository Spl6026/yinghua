<?php

declare(strict_types=1);

namespace Yinghua\DTO;

/** [D：C20；E-27] snapshotName 只取加入當時公開名稱，不隨草稿修改。 */
readonly class ItineraryStop
{
    public function __construct(public string $id, public string $attractionId, public int $sortOrder, public ?string $plannedTime, public string $note, public string $snapshotName) {}
}
