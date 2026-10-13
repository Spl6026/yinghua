<?php

declare(strict_types=1);

namespace Yinghua\Adapters;

use Yinghua\Contracts\Clock;

class SystemClock implements Clock
{
    public function now(): int
    {
        return time();
    }
}
