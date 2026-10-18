<?php

declare(strict_types=1);

namespace Yinghua\DTO;

readonly class AttractionWriteResult
{
    public function __construct(public ?Attraction $attraction, public bool $synchronized) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return ['attraction' => $this->attraction?->toArray(), 'publication' => [
            'status' => $this->synchronized ? 'succeeded' : 'failed',
            'message' => $this->synchronized ? '資料與公開頁面已同步。' : '資料已儲存，公開頁面更新失敗；請重試發布。',
        ]];
    }
}
