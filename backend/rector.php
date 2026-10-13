<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()->withPaths([__DIR__ . '/src', __DIR__ . '/public', __DIR__ . '/bin', __DIR__ . '/config'])
    ->withCache(__DIR__ . '/.build/rector')->withPhpSets();
