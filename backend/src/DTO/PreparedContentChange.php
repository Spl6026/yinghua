<?php

declare(strict_types=1);

namespace Yinghua\DTO;

/** [S G22／G23；E-31] HTTP與CLI共用的已驗證命令，不包含匯入檔格式。 */
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
