<?php

declare(strict_types=1);

namespace Yinghua\DTO;

/** 匯入檔中的一筆內容；ID 由匯入檔明確指定，不從標題或在陣列中的位置推算。 */
readonly class ImportItem
{
    /** @param array<string,mixed> $values */
    public function __construct(public string $id, public array $values) {}
}
