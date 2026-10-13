<?php

declare(strict_types=1);

namespace Yinghua\Contracts;

interface Clock
{
    public function now(): int;
}
