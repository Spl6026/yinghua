<?php

declare(strict_types=1);

namespace Yinghua\Models;

/** 可以設定 SEO 的資源類型；推薦路線目前沒有個別 SEO 設定。 */
enum SeoResource: string
{
    case Page = 'page';
    case Attraction = 'attraction';
    case Film = 'film';
    case News = 'news';
    case Offer = 'offer';

    public function module(): ?string
    {
        return match ($this) {
            self::Page => null,
            self::Attraction => 'attractions',
            self::Film => 'films',
            self::News => 'news',
            self::Offer => 'offers',
        };
    }
}
