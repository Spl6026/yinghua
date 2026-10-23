<?php

declare(strict_types=1);

/** [D：D19–D21；E-27] 實際 MariaDB 12.3 查詢計畫；自有資料最後全部回滾。 */
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
$trip = Secrets::uuid();
$places = [];
$db->pdo->beginTransaction();
try {
    $db->execute("INSERT INTO members(id,email,display_name,password_hash) VALUES(:id,:email,'查詢驗證','not-a-login-hash')", ['id' => $member, 'email' => $member . '@example.test']);
    $repository = new ItineraryRepository($db);
    $repository->createCurrent($member, $trip);
    for ($index = 0; $index < 160; $index++) {
        $id = Secrets::uuid();
        $places[] = $id;
        $db->execute("INSERT INTO attractions(id,slug,name,category,summary,description,address,publish_at) VALUES(:id,:slug,'查詢驗證','景點','','','',UTC_TIMESTAMP())", ['id' => $id, 'slug' => 'query-' . $id]);
        $db->execute('INSERT INTO favorites(member_id,attraction_id,created_at) VALUES(:member,:id,DATE_SUB(UTC_TIMESTAMP(),INTERVAL :seconds SECOND))', ['member' => $member, 'id' => $id, 'seconds' => $index]);
        $repository->append($member, $trip, Secrets::uuid(), $id, '公開名稱快照');
    }
    $queries = [
        'favorites-first' => ['SELECT attraction_id,created_at FROM favorites WHERE member_id=:member ORDER BY created_at DESC,attraction_id DESC LIMIT :limit', ['member' => $member, 'limit' => 21]],
        'favorites-cursor' => ['SELECT attraction_id,created_at FROM favorites WHERE member_id=:member AND (created_at<:at OR (created_at=:same_at AND attraction_id<:id)) ORDER BY created_at DESC,attraction_id DESC LIMIT :limit', ['member' => $member, 'at' => gmdate('Y-m-d H:i:s', time() - 80), 'same_at' => gmdate('Y-m-d H:i:s', time() - 80), 'id' => $places[80], 'limit' => 21]],
        'favorites-status' => ['SELECT attraction_id FROM favorites WHERE member_id=:member AND attraction_id IN (:first,:second)', ['member' => $member, 'first' => $places[0], 'second' => $places[1]]],
        'current-parent' => ["SELECT id,member_id,title,trip_date,version FROM itineraries WHERE member_id=:member AND current_slot=1 AND status='active' AND deleted_at IS NULL", ['member' => $member]],
        'current-items' => ["SELECT i.id,i.attraction_id,i.sort_order,i.planned_time,i.note,i.snapshot_name FROM itinerary_items i JOIN itineraries t ON t.id=i.itinerary_id WHERE t.member_id=:member AND t.id=:id AND t.deleted_at IS NULL AND t.status='active' AND i.sort_order>:after ORDER BY i.sort_order LIMIT :limit", ['member' => $member, 'id' => $trip, 'after' => 80, 'limit' => 51]],
    ];
    $report = ['version' => $db->row('SELECT VERSION() AS version'), 'performanceSchema' => $db->row("SHOW VARIABLES LIKE 'performance_schema'"), 'fixtureRows' => 160, 'queries' => []];
    foreach ($queries as $name => [$sql, $parameters]) {
        $report['queries'][$name] = ['explain' => $db->rows('EXPLAIN ' . $sql, $parameters), 'analyze' => $db->rows('ANALYZE FORMAT=JSON ' . $sql, $parameters)];
    }
    echo json_encode($report, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
} finally {
    $db->pdo->rollBack();
}
