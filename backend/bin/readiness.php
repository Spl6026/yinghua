<?php

declare(strict_types=1);

/** 容器內的 readiness 檢查指令，和 FPM 的 liveness 探測分開，只輸出 JSON 狀態。 */
require dirname(__DIR__) . '/vendor/autoload.php';

use Yinghua\Adapters\PdoDatabaseHealthProbe;
use Yinghua\Composition;
use Yinghua\Config;
use Yinghua\Repositories\Database;
use Yinghua\Services\ApplicationHealth;

try {
    $config = new Config();
    $report = (new ApplicationHealth($config, new PdoDatabaseHealthProbe($config, dirname(__DIR__) . '/database/migrations')))->inspect();
    if (!$report->ready) {
        echo json_encode($report->toArray(), JSON_THROW_ON_ERROR) . "\n";
        exit(1);
    }
    $mode = ($argv ?? [])[1] ?? 'core';
    if ($mode === 'core') {
        echo json_encode($report->toArray(), JSON_THROW_ON_ERROR) . "\n";
    } elseif ($mode === 'public') {
        $snapshot = (new Composition($config))->publication(Database::connect($config))->read();
        echo json_encode(['status' => 'ok', 'version' => $report->version,
            'generation' => $snapshot['generation']], JSON_THROW_ON_ERROR) . "\n";
    } else {
        throw new RuntimeException('Unknown readiness mode.');
    }
} catch (Throwable $error) {
    \Yinghua\Support\ErrorLog::exception('readiness', $error);
    fwrite(STDOUT, "{\"status\":\"unavailable\"}\n");
    exit(1);
}
