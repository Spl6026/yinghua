<?php

declare(strict_types=1);

namespace Yinghua\Models;

/** 圖片的來源管道，由伺服器端程式決定，不能由 HTTP 欄位指定。 */
enum ImageOrigin
{
    case HttpUpload;
    case LocalCli;
}
