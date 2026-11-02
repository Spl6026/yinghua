<?php

declare(strict_types=1);

namespace Yinghua\DTO;

/** 驗證完成、準備寫入的內容變更，HTTP 與 CLI 共用；和匯入檔的格式無關。 */
readonly class PreparedContentChange
{
    /** @param list<string> $warnings */
    public function __construct(
        public string $id,
        public NewsInput|OfferInput|MerchantInput $input,
        public bool $creating,
        public bool $unchanged,
        public int $version,
        public array $warnings = [],
    ) {}
}
