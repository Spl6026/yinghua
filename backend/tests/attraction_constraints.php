<?php

declare(strict_types=1);

/** [E-18／R3-04] 真實 MariaDB CHECK，測試結束回滾。 */
require dirname(__DIR__) . '/vendor/autoload.php';
$config = new Yinghua\Config();
if ($config->string('APP_ENV') !== 'test' || $config->string('DB_DATABASE') !== 'yinghua_test') {
    throw new RuntimeException('Isolated test database required.');
}
$database = Yinghua\Repositories\Database::connect($config);
$database->pdo->beginTransaction();
$checks = 0;
try {
    foreach ([[null,null,true],[24.8,120.97,true],[null,120.97,false],[24.8,null,false],[91,120,false],[24,181,false],[-90,-180,true],[90,180,true]] as [$latitude,$longitude,$allowed]) {
        $accepted = true;
        try {
            $database->execute("UPDATE attractions SET latitude=:latitude,longitude=:longitude WHERE legacy_id=2", ['latitude' => $latitude,'longitude' => $longitude]);
        } catch (PDOException $error) {
            if (($error->errorInfo[1] ?? null) !== 4025) {
                throw $error;
            }
            $accepted = false;
        }
        if ($accepted !== $allowed) {
            throw new RuntimeException('Coordinate CHECK allowed an invalid pair.');
        }
        ++$checks;
    }
} finally {
    $database->pdo->rollBack();
}
fwrite(STDOUT, $checks . " coordinate checks passed.\n");
