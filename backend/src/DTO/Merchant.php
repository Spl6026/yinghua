<?php

declare(strict_types=1);

namespace Yinghua\DTO;

/** [D：資料表建議 C15；E-26] 店家 profile 與景點內容有明確各自的用途。 */
readonly class Merchant
{
    /** @param array<string,string|int|null> $fields */
    public function __construct(public string $id, public int $version, public array $fields) {}
}
