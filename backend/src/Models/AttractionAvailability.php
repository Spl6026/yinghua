<?php

declare(strict_types=1);

namespace Yinghua\Models;

/** [E-27] 公開生成故障不是下架；私人清單需明確區分未知。 */
enum AttractionAvailability: string
{
    case Available = 'available';
    case Unavailable = 'unavailable';
    case Unknown = 'unknown';
}
