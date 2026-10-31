<?php

declare(strict_types=1);

namespace Yinghua\Models;

/** [S E18；E-35] 固定分組順序；美食由景點分類獨立篩選。 */
enum SearchType: string
{
    case Place = 'place';
    case Food = 'food';
    case Film = 'film';
    case News = 'news';
    case Offer = 'offer';

    public function order(): int
    {
        return match ($this) {
            self::Place => 0, self::Food => 1, self::Film => 2, self::News => 3, self::Offer => 4,
        };
    }
}
