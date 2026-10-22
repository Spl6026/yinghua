<?php

declare(strict_types=1);

namespace Yinghua\Models;

/** [E-26] 來源只由伺服器入口建立，不能由 HTTP 欄位選擇。 */
enum ImageOrigin
{
    case HttpUpload;
    case LocalCli;
}
