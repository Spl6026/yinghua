<?php

declare(strict_types=1);

namespace Yinghua\Models;

use Yinghua\Exceptions\ApplicationError;

/** [E-25] 共用媒體端點依所屬業務模組授權；不產生新的權限種類。 */
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
