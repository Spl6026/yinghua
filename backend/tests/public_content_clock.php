<?php

declare(strict_types=1);

/** 以真實 MariaDB 與 Node 驗證跨日發布：產物放在獨立的 /tmp，交易會回滾，不切換主站或測試站的 current。 */
require dirname(__DIR__) . '/vendor/autoload.php';

use Yinghua\Adapters\NodeContentRenderer;
use Yinghua\Config;
use Yinghua\Contracts\Clock;
use Yinghua\Contracts\Transactions;
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
use Yinghua\Repositories\GeneralSettingsRepository;
use Yinghua\Repositories\MerchantRepository;
use Yinghua\Repositories\NewsRepository;
use Yinghua\Repositories\OfferRepository;
use Yinghua\Repositories\PublicationRepository;
use Yinghua\Repositories\RecommendedRouteRepository;
use Yinghua\Repositories\SearchRepository;
use Yinghua\Repositories\SeoOverrideRepository;
use Yinghua\Repositories\SeoSettingsRepository;
use Yinghua\Services\ContentPublication;
use Yinghua\Services\PublicationSnapshotBuilder;
use Yinghua\Services\SeoPublication;
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
    // 正式環境每個請求各自建立 ContentPublication，同一請求內才沿用已驗證的快照；
    // 測試要檢查「內容或時間改變後不能再提供舊快照」時，以新的實例代表下一個請求。
    $newRequest = static fn(): ContentPublication => new ContentPublication(
        new PublicationRepository($db),
        new NodeContentRenderer($config),
        // 外層測試交易最後會回滾，搜尋索引寫入直接沿用該交易，不另開巢狀交易。
        new SearchRepository($db, new class implements Transactions {
            public function run(callable $operation): mixed
            {
                return $operation();
            }
        }),
        new PublicationSnapshotBuilder(
            $config,
            $clock,
            new AttractionRepository($db),
            new FilmRepository($db, new FilmScreeningRepository($db)),
            $news,
            $offers,
            new RecommendedRouteRepository($db),
            new SeoPublication(new SeoSettingsRepository($db), new SeoOverrideRepository($db)),
            new GeneralSettingsRepository($db),
        ),
    );
    $publication = $newRequest();
    $public = new PublicOffers($publication);
    $clock->value = (int) strtotime('2026-10-22T23:59:59+08:00');
    $check($publication->synchronize());
    $check($public->get('clock-' . $offerId)['offer']['validity'] === 'active');
    $check(!array_any($publication->read()['news'], static fn(array $item): bool => $item['id'] === $newsId));
    $oldGeneration = $publication->read()['generation'];
    $clock->value++;
    $publication = $newRequest();
    $public = new PublicOffers($publication);
    $check(!$publication->ready());
    try {
        $publication->read();
        $check(false);
    } catch (ApplicationError $error) {
        $check($error->errorCode === Yinghua\Exceptions\ErrorCode::PublicationPending);
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
    // 以台北時間的週一為界切換，首頁只能顯示新一週的場次。
    $screenings = new FilmScreeningRepository($db);
    $films = new FilmRepository($db, $screenings);
    $filmId = Secrets::uuid();
    $oldFilmId = Secrets::uuid();
    foreach ([$filmId => '換週保留電影',$oldFilmId => '換週移除電影'] as $id => $title) {
        $films->save($id, \Yinghua\DTO\FilmInput::fromArray(['title' => $title,'slug' => 'clock-' . $id,'status' => 'published','publishAt' => '2026-10-01T00:00:00+08:00'], true), true, $actor->id);
        $rows = [\Yinghua\DTO\ScreeningInput::fromArray(['startsAt' => '2026-10-25T23:30:00+08:00'])];
        if ($id === $filmId) {
            $rows[] = \Yinghua\DTO\ScreeningInput::fromArray(['startsAt' => '2026-10-26T00:00:00+08:00']);
        }
        $screenings->replace($id, $rows);
    }
    $clock->value = (int) strtotime('2026-10-25T23:59:59+08:00');
    $check($publication->synchronize());
    $home = (new \Yinghua\Services\PublicHome($publication))->get();
    $first = array_find($home['films'], static fn(array $item): bool => $item['id'] === $filmId);
    $check($first !== null && count($first['screenings']) === 1);
    $check(array_any($home['films'], static fn(array $item): bool => $item['id'] === $oldFilmId));
    $generation = $home['generation'];
    $clock->value++;
    $check(!$publication->ready());
    $check($publication->tick());
    $home = (new \Yinghua\Services\PublicHome($publication))->get();
    $check($home['period']['from'] === '2026-10-25T16:00:00+00:00');
    $first = array_find($home['films'], static fn(array $item): bool => $item['id'] === $filmId);
    $check($first !== null && count($first['screenings']) === 1 && $first['screenings'][0]['startsAt'] === '2026-10-25T16:00:00+00:00');
    $check(!array_any($home['films'], static fn(array $item): bool => $item['id'] === $oldFilmId) && $home['generation'] !== $generation);
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
