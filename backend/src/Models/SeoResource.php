<?php

declare(strict_types=1);

namespace Yinghua\Models;

/** [D：C25；E-28] 只管理已交付內容，不提前建立路線 SEO 寫入。 */
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
