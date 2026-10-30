<?php

declare(strict_types=1);

/** [D D19；E-33] 實際MariaDB查詢；自有資料於finally全部回滾，不改環境。 */
require dirname(__DIR__) . '/vendor/autoload.php';

use Yinghua\Config;
use Yinghua\Repositories\Database;
use Yinghua\Repositories\ItineraryRepository;
use Yinghua\Support\Secrets;

$config = new Config();
if ($config->string('APP_ENV') !== 'test' || $config->string('DB_DATABASE') !== 'yinghua_test') {
    throw new RuntimeException('Isolated database required.');
}
$db = Database::connect($config);
$member = Secrets::uuid();
$db->pdo->beginTransaction();
try {
    $db->execute("INSERT INTO members(id,email,display_name,password_hash) VALUES(:id,:email,'行程查詢驗證','not-a-login-hash')", ['id' => $member, 'email' => $member . '@example.test']);
    $records = new ItineraryRepository($db);
    for ($index = 0; $index < 160; $index++) {
        $records->createOwned($member, Secrets::uuid(), '行程' . $index, null);
    }
    $queries = [
        'collection' => ["SELECT id,member_id,title,trip_date,version FROM itineraries WHERE member_id=:member AND status='active' AND deleted_at IS NULL ORDER BY updated_at DESC,id DESC LIMIT 20 OFFSET 100", ['member' => $member]],
        'version' => ['SELECT COALESCE(MAX(version),0) AS version FROM itineraries WHERE member_id=:member', ['member' => $member]],
        'current' => ["SELECT id FROM itineraries WHERE member_id=:member AND current_slot=1 AND status='active' AND deleted_at IS NULL", ['member' => $member]],
    ];
    $report = ['version' => $db->row('SELECT VERSION() AS version'), 'performanceSchema' => $db->row("SHOW VARIABLES LIKE 'performance_schema'"), 'fixtureRows' => 160, 'queries' => []];
    foreach ($queries as $name => [$sql, $parameters]) {
        $report['queries'][$name] = ['explain' => $db->rows('EXPLAIN ' . $sql, $parameters), 'analyze' => $db->rows('ANALYZE FORMAT=JSON ' . $sql, $parameters)];
    }
    echo json_encode($report, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
} finally {
    $db->pdo->rollBack();
}
