<?php

declare(strict_types=1);

namespace Yinghua\Models;

/** 收藏或行程中的景點目前能否顯示；公開快照讀取失敗時是 Unknown，不能當成已下架。 */
enum AttractionAvailability: string
{
    case Available = 'available';
    case Unavailable = 'unavailable';
    case Unknown = 'unknown';
}
