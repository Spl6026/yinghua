<?php

declare(strict_types=1);

namespace Yinghua\DTO;

/** 優惠資料；和最新消息分開存放。 */
readonly class Offer extends PublishedRecord
{
    /** @param array<string,string|int|null> $fields
     * @param array<string,mixed> $values
     */
    public function __construct(
        string $id,
        int $version,
        array $fields,
        array $values,
        public bool $merchantAvailable = true,
        ?string $updatedAt = null,
    ) {
        parent::__construct($id, $version, $fields, $values, $updatedAt);
    }
}
