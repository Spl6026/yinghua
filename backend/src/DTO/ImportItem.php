<?php

declare(strict_types=1);

namespace Yinghua\DTO;

/** [E-26] 匯入識別獨立於內容表單；ID 不由標題或目前陣列位置推算。 */
readonly class ImportItem
{
    /** @param array<string,mixed> $values */
    public function __construct(public string $id, public array $values) {}
}
