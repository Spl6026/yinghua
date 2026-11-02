<?php

declare(strict_types=1);

namespace Yinghua\Models;

/** 消息分類；優惠另有自己的模組，不在這裡。 */
enum NewsType: string
{
    case Event = 'event';
    case City = 'city';
}
