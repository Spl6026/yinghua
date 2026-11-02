<?php

declare(strict_types=1);

namespace Yinghua\DTO;

/** 優惠店家資料；店家和景點是兩種不同的資料，各自維護。 */
readonly class Merchant
{
    /** @param array<string,string|int|null> $fields */
    public function __construct(public string $id, public int $version, public array $fields) {}
}
