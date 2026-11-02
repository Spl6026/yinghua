<?php

declare(strict_types=1);

namespace Yinghua\Models;

use Yinghua\Exceptions\ApplicationError;

/** 媒體 API 是共用的，依圖片所屬的業務模組檢查權限，沒有獨立的媒體權限。 */
enum MediaModule: string
{
    case Attractions = 'attractions';
    case Films = 'films';
    case News = 'news';
    case Offers = 'offers';

    public static function parse(string $value): self
    {
        return self::tryFrom($value) ?? throw ApplicationError::invalid('module', '不支援的媒體模組。');
    }
}
