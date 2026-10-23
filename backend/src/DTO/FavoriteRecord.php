<?php

declare(strict_types=1);

namespace Yinghua\DTO;

readonly class FavoriteRecord
{
    public function __construct(public string $attractionId, public string $createdAt) {}
}
