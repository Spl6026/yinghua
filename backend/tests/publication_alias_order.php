<?php

declare(strict_types=1);

/** 真 MariaDB 交易內，刻意反轉舊網址查詢順序，確認 generation 不受查詢順序影響；產物隔離，不切換網站 current。 */
require dirname(__DIR__) . '/vendor/autoload.php';

use Yinghua\Adapters\NodeContentRenderer;
use Yinghua\Config;
use Yinghua\Contracts\Clock;
use Yinghua\Contracts\Transactions;
use Yinghua\DTO\MerchantInput;
use Yinghua\DTO\NewsInput;
use Yinghua\DTO\OfferInput;
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
use Yinghua\Support\Secrets;

$config = new Config();
$connection = Database::connect($config);
if (($connection->row('SELECT DATABASE() AS name')['name'] ?? '') !== 'yinghua_test') {
    throw new RuntimeException('Isolated database required.');
}
$database = new class ($connection->pdo) extends Database {
    public bool $reverse = false;

    #[Override]
    public function rows(string $sql, array $parameters = []): array
    {
        if (str_contains($sql, 'FROM news_aliases a ') || str_contains($sql, 'FROM offer_aliases a ')) {
            $sql .= ' ORDER BY a.path ' . ($this->reverse ? 'DESC' : 'ASC');
        }
        return parent::rows($sql, $parameters);
    }
};
$directory = '/tmp/spl-alias-order-' . bin2hex(random_bytes(6));
mkdir($directory, 0700);
putenv('PUBLICATION_PATH=' . $directory);
$clock = new class implements Clock {
    private readonly int $fixed;
    public function __construct()
    {
        $this->fixed = time();
    }
    public function now(): int
    {
        return $this->fixed;
    }
};
$checks = 0;
$check = static function (bool $ok) use (&$checks): void {
    if (!$ok) {
        throw new RuntimeException('Alias order assertion ' . ($checks + 1));
    }
    $checks++;
};
$database->pdo->beginTransaction();
try {
    $actor = (new AccountRepository($database))->byLogin(IdentityKind::Admin, 'admin') ?? throw new RuntimeException('Test root required.');
    $merchant = Secrets::uuid();
    (new MerchantRepository($database))->save($merchant, MerchantInput::fromArray(['name' => '查詢順序隔離店家'], true), true);
    $news = new NewsRepository($database);
    $offers = new OfferRepository($database);
    $offerIds = [];
    foreach (['z','a'] as $prefix) {
        $id = Secrets::uuid();
        $news->save($id, NewsInput::fromArray(['title' => '隔離消息','type' => 'city','slug' => $prefix . '-' . $id,'status' => 'published','publishAt' => '2020-01-01T00:00:00+08:00'], true, time()), true, $actor->id);
        $id = Secrets::uuid();
        $offerIds[] = $id;
        $offers->save($id, OfferInput::fromArray(['title' => '隔離優惠','discount' => '九折','terms' => '隔離驗收使用條款','merchantId' => $merchant,'slug' => $prefix . '-' . $id,'status' => 'published','publishAt' => '2020-01-01T00:00:00+08:00'], true, time()), true, $actor->id);
    }
    // 外層測試交易最後會回滾，搜尋索引寫入直接沿用該交易，不另開巢狀交易。
    $outerTransaction = new class implements Transactions {
        public function run(callable $operation): mixed
        {
            return $operation();
        }
    };
    // 正式環境每個請求各自建立 ContentPublication，同一請求內才沿用已驗證的快照；
    // 測試要檢查「內容或時間改變後不能再提供舊快照」時，以新的實例代表下一個請求。
    $newRequest = static fn(): ContentPublication => new ContentPublication(
        new PublicationRepository($database),
        new NodeContentRenderer($config),
        new SearchRepository($database, $outerTransaction),
        new PublicationSnapshotBuilder(
            $config,
            $clock,
            new AttractionRepository($database),
            new FilmRepository($database, new FilmScreeningRepository($database)),
            $news,
            $offers,
            new RecommendedRouteRepository($database),
            new SeoPublication(new SeoSettingsRepository($database), new SeoOverrideRepository($database)),
            new GeneralSettingsRepository($database),
        ),
    );
    $publication = $newRequest();
    $raw = $database->rows('SELECT a.path FROM offer_aliases a WHERE 1=1');
    $check($publication->synchronize());
    $generation = $publication->read()['generation'];
    $database->reverse = true;
    $publication = $newRequest();
    $reversed = $database->rows('SELECT a.path FROM offer_aliases a WHERE 1=1');
    $check($raw !== $reversed && count($raw) === count($reversed));
    $check($publication->read()['generation'] === $generation);
    $check($publication->ready());
    $database->execute("UPDATE offers SET status='archived' WHERE id=:id", ['id' => $offerIds[0]]);
    $publication = $newRequest();
    // 內容改變後，下一次發布前仍提供上一版；發布程序的比對會發現需要重新產生。
    $check($publication->read()['generation'] === $generation);
    $check(!$publication->ready());
    $check($publication->synchronize());
    $check($publication->read()['generation'] !== $generation);
} finally {
    $database->pdo->rollBack();
    $remove = static function (string $path) use (&$remove, $directory): void {
        if (!str_starts_with($path, $directory . '/') && $path !== $directory) {
            throw new RuntimeException('Temporary cleanup escaped its directory.');
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
echo json_encode(['checks' => $checks, 'realMariaDb' => true, 'forcedQueryOrders' => true, 'rolledBack' => true, 'isolatedPublicationDirectory' => true], JSON_THROW_ON_ERROR) . "\n";
