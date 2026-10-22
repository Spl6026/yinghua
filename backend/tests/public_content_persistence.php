<?php

declare(strict_types=1);

/** [E-26] 隔離 MariaDB：資料層、引用與資料庫限制；所有 fixture 在 finally 回滾。 */
require dirname(__DIR__) . '/vendor/autoload.php';

use Yinghua\Config;
use Yinghua\DTO\MerchantInput;
use Yinghua\DTO\NewsInput;
use Yinghua\DTO\OfferInput;
use Yinghua\Models\IdentityKind;
use Yinghua\Repositories\AccountRepository;
use Yinghua\Repositories\Database;
use Yinghua\Repositories\MerchantRepository;
use Yinghua\Repositories\NewsRepository;
use Yinghua\Repositories\OfferRepository;
use Yinghua\Support\Secrets;

$db = Database::connect(new Config());
if (($db->row('SELECT DATABASE() AS name')['name'] ?? '') !== 'yinghua_test') {
    throw new RuntimeException('Isolated test database required.');
}
$actor = (new AccountRepository($db))->byLogin(IdentityKind::Admin, 'admin') ?? throw new RuntimeException('Test root required.');
$checks = 0;
$check = static function (bool $ok) use (&$checks): void {
    if (!$ok) {
        throw new RuntimeException('Public content persistence check failed at ' . ($checks + 1));
    }
    $checks++;
};
$db->pdo->beginTransaction();
try {
    $merchants = new MerchantRepository($db);
    $news = new NewsRepository($db);
    $offers = new OfferRepository($db);
    $merchantId = Secrets::uuid();
    $newsId = Secrets::uuid();
    $offerId = Secrets::uuid();
    $merchants->save($merchantId, MerchantInput::fromArray(['name' => '隔離合作店家'], true), true);
    $check($merchants->find($merchantId)?->fields['attraction_id'] === null);
    $newsValues = ['title' => '隔離消息','slug' => 'test-' . $newsId,'type' => 'city','status' => 'published'];
    $news->save($newsId, NewsInput::fromArray($newsValues, true, time() - 10), true, $actor->id);
    $check($news->find($newsId)?->publicArray()['summary'] === '');
    $news->save($newsId, NewsInput::fromArray([...$newsValues,'slug' => 'updated-' . $newsId,'version' => 1], false, time()), false, $actor->id);
    $check($news->find($newsId)?->version === 2);
    $check(isset($news->aliases(time())['/news/test-' . $newsId . '.html']));
    $offers->save($offerId, OfferInput::fromArray(['title' => '隔離優惠','slug' => 'test-' . $offerId,'merchantId' => $merchantId,'discount' => '九折','terms' => '請出示本頁','status' => 'published'], true, time() - 10), true, $actor->id);
    $check($offers->find($offerId)?->publicArray()['validTo'] === null);
    $check(count(array_filter($offers->published(time()), static fn($offer) => $offer->id === $offerId)) === 1);
    $db->execute("UPDATE merchants SET status='inactive' WHERE id=:id", ['id' => $merchantId]);
    $check(count(array_filter($offers->published(time()), static fn($offer) => $offer->id === $offerId)) === 0);
    foreach (["UPDATE offers SET terms='' WHERE id=:id", "UPDATE offers SET valid_from='2026-10-23',valid_to='2026-10-22' WHERE id=:id", "UPDATE offers SET merchant_id='00000000-0000-4000-8000-000000000000' WHERE id=:id"] as $sql) {
        try {
            $db->execute($sql, ['id' => $offerId]);
            $check(false);
        } catch (PDOException) {
            $check(true);
        }
    }
    $db->execute("UPDATE offers SET valid_from=NULL,valid_to='2026-10-22' WHERE id=:id", ['id' => $offerId]);
    $check($offers->find($offerId)?->fields['valid_from'] === null);
    echo json_encode(['publicContentPersistenceChecks' => $checks,'rolledBack' => true], JSON_THROW_ON_ERROR) . "\n";
} finally {
    $db->pdo->rollBack();
}
