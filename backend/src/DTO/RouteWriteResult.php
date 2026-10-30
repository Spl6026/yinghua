<?php

declare(strict_types=1);

namespace Yinghua\DTO;

readonly class RouteWriteResult
{
    public function __construct(public ?RecommendedRoute $record, public bool $synchronized) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return ['record' => $this->record?->toArray(),'publication' => ['status' => $this->synchronized ? 'succeeded' : 'failed','message' => $this->synchronized ? '資料與公開頁面已同步。' : '資料已保存，公開頁面尚未同步；請重新載入確認，勿重複新增。']];
    }
}
