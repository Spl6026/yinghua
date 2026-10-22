<?php

declare(strict_types=1);

/** [E-26] 真實 MariaDB＋Node 的跨日發布：獨立 /tmp 產物，交易回滾，不換主／測試 current。 */
require dirname(__DIR__) . '/vendor/autoload.php';

use Yinghua\Adapters\NodeContentRenderer;
use Yinghua\Config;
use Yinghua\Contracts\Clock;
use Yinghua\DTO\MerchantInput;
use Yinghua\DTO\NewsInput;
use Yinghua\DTO\OfferInput;
use Yinghua\DTO\OfferQuery;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\IdentityKind;
use Yinghua\Repositories\AccountRepository;
use Yinghua\Repositories\AttractionRepository;
use Yinghua\Repositories\Database;
use Yinghua\Repositories\FilmRepository;
use Yinghua\Repositories\FilmScreeningRepository;
use Yinghua\Repositories\MerchantRepository;
use Yinghua\Repositories\NewsRepository;
use Yinghua\Repositories\OfferRepository;
use Yinghua\Repositories\PublicationRepository;
use Yinghua\Services\ContentPublication;
use Yinghua\Services\PublicOffers;
use Yinghua\Support\Secrets;

$config = new Config();
$db = Database::connect($config);
if (($db->row('SELECT DATABASE() AS name')['name'] ?? '') !== 'yinghua_test') {
    throw new RuntimeException('Isolated database required.');
}
$directory = '/tmp/spl-phase5-clock-' . bin2hex(random_bytes(6));
mkdir($directory, 0700);
putenv('PUBLICATION_PATH=' . $directory);
$clock = new class implements Clock {
    public int $value;
    public function now(): int
    {
        return $this->value;
    }
};
$checks = 0;
$check = static function (bool $ok) use (&$checks): void {
    if (!$ok) {
        throw new RuntimeException('Publication clock assertion ' . ($checks + 1));
    }
    $checks++;
};
$db->pdo->beginTransaction();
try {
    $actor = (new AccountRepository($db))->byLogin(IdentityKind::Admin, 'admin') ?? throw new RuntimeException('Test root required.');
    $merchant = Secrets::uuid();
    $offerId = Secrets::uuid();
    $newsId = Secrets::uuid();
    (new MerchantRepository($db))->save($merchant, MerchantInput::fromArray(['name' => '跨日店家'], true), true);
    $offers = new OfferRepository($db);
    $offers->save($offerId, OfferInput::fromArray(['title' => '跨日優惠','slug' => 'clock-' . $offerId,'merchantId' => $merchant,'discount' => '九折','terms' => '當日有效',
        'status' => 'published','publishAt' => '2026-10-01T00:00:00+08:00','validFrom' => '2026-10-22','validTo' => '2026-10-22'], true, time()), true, $actor->id);
    $news = new NewsRepository($db);
    $news->save($newsId, NewsInput::fromArray(['title' => '跨日排程消息','type' => 'city','slug' => 'clock-' . $newsId,'status' => 'published','publishAt' => '2026-10-23T00:00:00+08:00'], true, time()), true, $actor->id);
    $publication = new ContentPublication(
        new AttractionRepository($db),
        new PublicationRepository($db),
        new NodeContentRenderer($config),
        $config,
        new FilmRepository($db, new FilmScreeningRepository($db)),
        $clock,
        $news,
        $offers,
    );
    $public = new PublicOffers($publication);
    $clock->value = (int) strtotime('2026-10-22T23:59:59+08:00');
    $check($publication->synchronize());
    $check($public->get('clock-' . $offerId)['offer']['validity'] === 'active');
    $check(!array_any($publication->read()['news'], static fn(array $item): bool => $item['id'] === $newsId));
    $oldGeneration = $publication->read()['generation'];
    $clock->value++;
    $check(!$publication->ready());
    try {
        $publication->read();
        $check(false);
    } catch (ApplicationError $error) {
        $check($error->errorCode === 'PUBLICATION_PENDING');
    }
    $check($publication->tick());
    $check($publication->read()['generation'] !== $oldGeneration);
    $check($public->get('clock-' . $offerId)['offer']['validity'] === 'expired');
    $check(!array_any($public->listing(new OfferQuery(true, '跨日優惠', '', 1, 20))['items'], static fn(array $item): bool => $item['id'] === $offerId));
    $check(array_any($publication->read()['news'], static fn(array $item): bool => $item['id'] === $newsId));
    $check(str_contains(file_get_contents($directory . '/current/offers/clock-' . $offerId . '.html'), '這項優惠已結束'));
    $generation = $publication->read()['generation'];
    $clock->value++;
    $check($publication->tick() && $publication->read()['generation'] === $generation);
    echo json_encode(['publicationClockChecks' => $checks,'rolledBack' => true,'isolatedPublicationDirectory' => true], JSON_THROW_ON_ERROR) . "\n";
} finally {
    $db->pdo->rollBack();
    $remove = static function (string $path) use (&$remove, $directory): void {
        if (!str_starts_with($path, $directory . '/') && $path !== $directory) {
            throw new RuntimeException('Temporary cleanup escaped its own directory.');
        }
        if (is_link($path) || is_file($path)) {
            unlink($path);
            return;
        }
        foreach (new DirectoryIterator($path) as $entry) {
            if (!$entry->isDot()) {
                $remove($entry->getPathname());
            }
        }
        rmdir($path);
    };
    $remove($directory);
}
