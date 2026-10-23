<?php

declare(strict_types=1);

/** [E-29] 維護窗口專用 CLI，沒有 HTTP 路由；主庫復原不在演練授權內。 */
require dirname(__DIR__) . '/vendor/autoload.php';

use Yinghua\Adapters\PdoTransactions;
use Yinghua\Adapters\RecoveryMediaInspector;
use Yinghua\Config;
use Yinghua\Repositories\Database;
use Yinghua\Repositories\RecoveryRepository;

try {
    $config = new Config();
    $database = Database::connect($config);
    $repository = new RecoveryRepository($database);
    $mode = ($argv ?? [])[1] ?? '';
    if ($mode === 'media') {
        echo json_encode((object) (new RecoveryMediaInspector($config))->inspect($repository->mediaKeys()), JSON_THROW_ON_ERROR) . "\n";
    } elseif ($mode === 'revoke-auth' && getenv('RESTORE_AUTHORIZATION_WINDOW') === 'confirmed-empty-target') {
        (new PdoTransactions($database))->run($repository->revokeRestoredCredentials(...));
        fwrite(STDOUT, "Restored authorization revoked; accounts, content and audit retained.\n");
    } else {
        throw new RuntimeException('Explicit recovery mode/window required.');
    }
} catch (Throwable $error) {
    error_log('recovery_exception=' . $error::class);
    fwrite(STDERR, "Recovery refused.\n");
    exit(1);
}
