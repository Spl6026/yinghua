<?php

declare(strict_types=1);

namespace Yinghua\Contracts;

/** 富文字淨化介面；實際使用哪個第三方淨化套件只有 Adapter 知道。 */
interface RichTextSanitizer
{
    public function clean(string $html): string;
}
