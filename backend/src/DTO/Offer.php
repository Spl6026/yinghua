<?php

declare(strict_types=1);

namespace Yinghua\DTO;

/** [D：資料表建議 16；E-26] 明確主題資料，不把優惠混入消息。 */
readonly class Offer extends PublishedRecord
{
    /** @param array<string,string|int|null> $fields
     * @param array<string,mixed> $values
     */
    public function __construct(string $id, int $version, array $fields, array $values, public bool $merchantAvailable = true)
    {
        parent::__construct($id, $version, $fields, $values);
    }
}
