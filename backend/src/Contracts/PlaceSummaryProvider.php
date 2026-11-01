<?php

declare(strict_types=1);

namespace Yinghua\Contracts;

use Yinghua\DTO\GoogleSummary;

interface PlaceSummaryProvider
{
    public function fetch(string $placeId): GoogleSummary;
}
