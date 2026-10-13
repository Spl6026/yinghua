<?php

declare(strict_types=1);

use Yinghua\Config;
use Yinghua\Repositories\Database;
use Yinghua\Adapters\PdoTransactions;
use Yinghua\Adapters\SystemClock;
use Yinghua\Repositories\AccountRepository;
use Yinghua\Repositories\ResetRepository;
use Yinghua\Repositories\SessionRepository;
use Yinghua\Services\RootInitializer;
use Yinghua\Services\PasswordHasher;

require dirname(__DIR__) . '/vendor/autoload.php';
$config = new Config();
if ($config->string('APP_ENV') !== 'test' || $config->string('DB_DATABASE') !== 'yinghua_test') {
    throw new RuntimeException('Fixtures are restricted to the named test database.');
}
$database = Database::connect($config);
$input = stream_get_contents(STDIN);
if ($input === false) {
    throw new RuntimeException('Missing fixture input.');
}
if (($argv[1] ?? '') === 'root') {
    $root = new RootInitializer(
        new AccountRepository($database),
        new PasswordHasher(),
        new PdoTransactions($database),
        new SessionRepository($database, $config, new SystemClock()),
        new ResetRepository($database),
    );
    $root->initialize('root@example.test', trim($input));
    $root->recover(trim($input));
    fwrite(STDOUT, "Isolated test root initialized.\n");
} else {
    // 測試程序從 stdin 指定 SQL，僅能接觸 yinghua_test；沒有 HTTP 測試後門。
    $statement = $database->pdo->query($input);
    fwrite(STDOUT, json_encode($statement === false ? [] : $statement->fetchAll(PDO::FETCH_ASSOC), JSON_THROW_ON_ERROR));
}
