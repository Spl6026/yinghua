<?php

declare(strict_types=1);

namespace Yinghua\DTO;

/** [E-26] Repository 映射後的內容；公開輸出不可帶作者、媒體內部識別或狀態。 */
readonly class PublishedRecord
{
    /** [E-34] updatedAt是索引內部中繼資料，publicArray仍只回既有白名單。
     * @param array<string,string|int|null> $fields
     * @param array<string,mixed> $values
     */
    public function __construct(public string $id, public int $version, public array $fields, private array $values, public ?string $updatedAt = null) {}

    /** @return array<string,mixed> */
    public function publicArray(): array
    {
        return $this->values;
    }
}
