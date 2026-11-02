<?php

declare(strict_types=1);

/** 在真實 MariaDB 驗證 SEO 設定的資料層、唯一約束與回滾；不會改到正式設定。 */
require dirname(__DIR__) . '/vendor/autoload.php';

use Yinghua\Config;
use Yinghua\DTO\SeoDefaults;
use Yinghua\DTO\SeoOverride;
use Yinghua\Models\SeoResource;
use Yinghua\Repositories\ContentSeoRepository;
use Yinghua\Repositories\Database;
use Yinghua\Repositories\SiteSettingsRepository;

$db = Database::connect(new Config());
if (($db->row('SELECT DATABASE() AS name')['name'] ?? '') !== 'yinghua_test') {
    throw new RuntimeException('Isolated database required.');
}
$actor = $db->row('SELECT id FROM admin_users WHERE is_root=1 LIMIT 1')['id'] ?? null;
if (!is_string($actor)) {
    throw new RuntimeException('Test administrator fixture required.');
}
$checks = 0;
$check = static function (bool $condition) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException('SEO persistence check failed at ' . ($checks + 1));
    }
    $checks++;
};
$reject = static function (callable $action) use ($check): void {
    try {
        $action();
    } catch (PDOException) {
        $check(true);
        return;
    }
    throw new RuntimeException('Expected SEO database constraint rejection.');
};
$settings = new SiteSettingsRepository($db);
$overrides = new ContentSeoRepository($db);
$before = [$settings->read()->toArray(), array_map(static fn(SeoOverride $item): array => $item->toArray(), $overrides->all())];
$db->pdo->beginTransaction();
try {
    $current = $settings->read(true);
    $defaults = new SeoDefaults(siteName: '隔離 SEO', canonicalOrigin: 'https://example.test');
    $settings->save($defaults, $current->version + 1, $actor, $current->version === 0);
    $check($settings->read()->version === $current->version + 1);
    $check($settings->read()->defaults->toArray() === $defaults->toArray());
    $overrides->replace([new SeoOverride(SeoResource::Page, '/food.html', '美食測試'), new SeoOverride(SeoResource::Film, '07000000-0000-4000-8000-000000000001', description: '電影測試')], $actor);
    $check(count($overrides->all()) === 2);
    $check($overrides->all()[0]->title === '美食測試');
    $reject(static fn() => $db->execute("INSERT INTO content_seo(id,resource_type,path_pattern,updated_by) VALUES(UUID(),'page','/food.html',:actor)", ['actor' => $actor]));
    $reject(static fn() => $db->execute("INSERT INTO content_seo(id,resource_type,resource_id,updated_by) VALUES(UUID(),'film','07000000-0000-4000-8000-000000000001',:actor)", ['actor' => $actor]));
    $reject(static fn() => $db->execute("INSERT INTO content_seo(id,resource_type,updated_by) VALUES(UUID(),'page',:actor)", ['actor' => $actor]));
    $reject(static fn() => $db->execute("INSERT INTO content_seo(id,resource_type,resource_id,path_pattern,updated_by) VALUES(UUID(),'film','07000000-0000-4000-8000-000000000001','/food.html',:actor)", ['actor' => $actor]));
    $reject(static fn() => $db->execute("UPDATE site_settings SET value_json='not-json' WHERE `key`='seo'"));
    $reject(static fn() => $db->execute("UPDATE site_settings SET version=0 WHERE `key`='seo'"));
    $check(($db->row("EXPLAIN SELECT version FROM site_settings WHERE `key`='seo'")['key'] ?? '') === 'PRIMARY');
    $check(($db->row("EXPLAIN SELECT id FROM content_seo WHERE path_pattern='/food.html'")['key'] ?? '') === 'seo_path');
    $overrides->replace([], $actor);
    $check($overrides->all() === []);
} finally {
    $db->pdo->rollBack();
}
$check($before === [$settings->read()->toArray(), array_map(static fn(SeoOverride $item): array => $item->toArray(), $overrides->all())]);
echo json_encode(['checks' => $checks, 'rolledBack' => true], JSON_THROW_ON_ERROR) . PHP_EOL;
