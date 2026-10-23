<?php

declare(strict_types=1);

namespace Yinghua\DTO;

readonly class FavoriteWriteResult
{
    public function __construct(public string $attractionId, public bool $created) {}

    /** @return array{attractionId:string,favorited:bool} */
    public function toArray(): array
    {
        return ['attractionId' => $this->attractionId, 'favorited' => true];
    }
}
