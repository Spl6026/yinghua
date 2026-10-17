<?php

declare(strict_types=1);

use Yinghua\Config;

/** [E-18] 僅隔離測試 volume 的故障注入；沒有 HTTP 入口。 */
require dirname(__DIR__) . '/vendor/autoload.php';
$config = new Config();
if ($config->string('APP_ENV') !== 'test' || $config->string('DB_DATABASE') !== 'yinghua_test'
    || $config->string('PUBLICATION_PATH') !== '/var/www/publication') {
    throw new RuntimeException('Content fixtures require the isolated test environment.');
}
$path = '/var/www/publication/generations';
if (!is_dir($path) || !in_array($argv[1] ?? '', ['renderer-fail','renderer-restore'], true)) {
    throw new RuntimeException('Invalid fixture.');
}
if (!chmod($path, ($argv[1] ?? '') === 'renderer-fail' ? 0555 : 0755)) {
    throw new RuntimeException('Cannot configure isolated renderer.');
}
fwrite(STDOUT, "Isolated renderer permissions updated.\n");
