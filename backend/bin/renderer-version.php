<?php

declare(strict_types=1);

// 正式映像建置時執行一次：把發布用的版本雜湊寫進前端原始檔目錄，執行期直接讀取，不必每個請求重新雜湊。

use Yinghua\Adapters\NodeContentRenderer;
use Yinghua\Config;

require dirname(__DIR__) . '/vendor/autoload.php';

$source = $argv[1] ?? '/var/www/frontend-source';
$version = (new NodeContentRenderer(new Config()))->computeVersion($source);
file_put_contents($source . '/' . NodeContentRenderer::PRECOMPUTED_VERSION_FILE, $version . "\n");
fwrite(STDOUT, $version . "\n");
