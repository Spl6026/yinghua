<?php

declare(strict_types=1);

namespace Yinghua\Models;

/** [S：功能規格 G9／G22] 優惠不屬於消息分類。 */
enum NewsType: string
{
    case Event = 'event';
    case City = 'city';
}
