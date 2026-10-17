<?php

declare(strict_types=1);

namespace Yinghua\Models;

/** [U-09／E-20] 未提供時間不等於公休。 */
enum OpeningDayStatus: string
{
    case Unknown = 'unknown';
    case Closed = 'closed';
    case AllDay = 'all_day';
    case Periods = 'periods';
}
