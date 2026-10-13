<?php

declare(strict_types=1);

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

return (new Config())->setRules(['@PER-CS' => true, 'no_unused_imports' => true])
    ->setRiskyAllowed(false)->setUsingCache(false)
    ->setFinder(Finder::create()->in([__DIR__ . '/src', __DIR__ . '/public', __DIR__ . '/bin', __DIR__ . '/config', __DIR__ . '/tests'])->name('*.php'));
