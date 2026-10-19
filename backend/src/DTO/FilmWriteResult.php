<?php

declare(strict_types=1);

namespace Yinghua\DTO;

readonly class FilmWriteResult
{
    public function __construct(public ?Film $film, public bool $synchronized) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return ['film' => $this->film?->toArray(),'publication' => ['status' => $this->synchronized ? 'succeeded' : 'failed',
            'message' => $this->synchronized ? '資料與公開頁面已同步。' : '資料已儲存，公開頁面更新失敗；請重試公開頁面同步。']];
    }
}
