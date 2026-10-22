<?php

declare(strict_types=1);

namespace Yinghua\Contracts;

/** [S：功能規格 G10／G22；E-26] 只由 Adapter 知道第三方淨化工具。 */
interface RichTextSanitizer
{
    public function clean(string $html): string;
}
