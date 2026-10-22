<?php

declare(strict_types=1);

/** [E-26] MariaDB 12.3 的實際發布查詢計畫；唯讀，不增加索引或重啟資料庫。 */
require dirname(__DIR__) . '/vendor/autoload.php';

$config = new Yinghua\Config();
if ($config->string('DB_DATABASE') !== 'yinghua_test' || $config->string('APP_ENV') !== 'test') {
    throw new RuntimeException('Isolated database required.');
}
$database = Yinghua\Repositories\Database::connect($config);
$queries = [
    'news' => "SELECT n.*,m.storage_key,m.alt AS media_alt FROM news_posts n LEFT JOIN media_assets m ON m.id=n.cover_media_id WHERE n.deleted_at IS NULL AND n.status='published' AND n.publish_at<=:now ORDER BY n.publish_at DESC,n.id",
    'offers' => "SELECT n.*,m.storage_key,m.alt AS media_alt,c.name AS merchant_name,c.district AS merchant_district,c.address AS merchant_address,c.phone AS merchant_phone,c.website AS merchant_website FROM offers n LEFT JOIN media_assets m ON m.id=n.cover_media_id JOIN merchants c ON c.id=n.merchant_id WHERE n.deleted_at IS NULL AND n.status='published' AND n.publish_at<=:now AND c.deleted_at IS NULL AND c.status='active' ORDER BY n.publish_at DESC,n.id",
];
$result = ['version' => $database->row('SELECT VERSION() AS version'),'performanceSchema' => $database->row("SHOW VARIABLES LIKE 'performance_schema'"),'queries' => []];
foreach ($queries as $name => $sql) {
    $parameters = ['now' => gmdate('Y-m-d H:i:s')];
    $result['queries'][$name] = ['explain' => $database->rows('EXPLAIN ' . $sql, $parameters),
        'analyze' => $database->rows('ANALYZE FORMAT=JSON ' . $sql, $parameters)];
}
echo json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
