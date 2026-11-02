<?php

declare(strict_types=1);

namespace Yinghua\Models;

/** 每日營業狀態；沒有提供時間（Unknown）不等於公休。 */
enum OpeningDayStatus: string
{
    case Unknown = 'unknown';
    case Closed = 'closed';
    case AllDay = 'all_day';
    case Periods = 'periods';
}
