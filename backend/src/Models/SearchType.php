<?php

declare(strict_types=1);

namespace Yinghua\Models;

/** 搜尋結果的分組與固定顯示順序；「美食」是從景點中依分類另外篩出來的一組。 */
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
