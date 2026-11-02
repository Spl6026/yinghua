<?php

declare(strict_types=1);

namespace Yinghua\DTO;

/** Repository 讀出的內容紀錄；公開輸出不可帶出作者、媒體內部識別或狀態。 */
readonly class PublishedRecord
{
    /** updatedAt 只供搜尋索引內部使用，publicArray() 仍只輸出原本的白名單欄位。
     * @param array<string,string|int|null> $fields
     * @param array<string,mixed> $values
     */
    public function __construct(
        public string $id,
        public int $version,
        public array $fields,
        private array $values,
        public ?string $updatedAt = null,
    ) {}

    /** @return array<string,mixed> */
    public function publicArray(): array
    {
        return $this->values;
    }
}
