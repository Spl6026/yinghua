<?php

declare(strict_types=1);

// [E-40] 真MariaDB讀寫／限速／並行；外部provider及已發布讀取邊界使用明確替身，無對外請求。
require __DIR__ . '/../vendor/autoload.php';
$config = new Yinghua\Config();
if ($config->string('DB_DATABASE') !== 'yinghua_test') {
    throw new RuntimeException('Isolated database required');
}
$input = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (!is_array($input) || !is_string($input['placeId'] ?? null) || !is_string($input['prefix'] ?? null) || !str_starts_with($input['prefix'], 'p8ui')) {
    throw new RuntimeException('Named fixture required');
}
$db = Yinghua\Repositories\Database::connect($config);
$row = $db->row('SELECT id FROM attractions WHERE id=:id AND slug=:slug', ['id' => $input['placeId'], 'slug' => $input['prefix'] . '-place']);
if ($row === null) {
    throw new RuntimeException('Owned fixture missing');
}
$records = new Yinghua\Repositories\AttractionRepository($db);
$public = new class extends Yinghua\Services\PublicAttractions {
    /** @var array<string,mixed> */
    public array $snapshot = [];
    public function __construct() {}
    public function get(string $slug): array
    {
        return ['attraction' => $this->snapshot];
    }
};
$provider = new class implements Yinghua\Contracts\PlaceSummaryProvider {
    public int $calls = 0;
    public ?Closure $duringFetch = null;
    public function fetch(string $placeId): Yinghua\DTO\GoogleSummary
    {
        $this->calls++;
        ($this->duringFetch ?? static function (): void {})();
        return new Yinghua\DTO\GoogleSummary('Synthetic original text; never persisted.', 'https://www.google.com/maps');
    }
};
$clock = new class implements Yinghua\Contracts\Clock {
    private int $time;
    public function __construct()
    {
        $this->time = time();
    }
    public function now(): int
    {
        return $this->time;
    }
};
$limiter = new Yinghua\Services\RateLimiter(new Yinghua\Repositories\RateLimitRepository($db), new Yinghua\Support\Secrets($config), $clock);
$service = new Yinghua\Services\AttractionSummary($public, $records, $provider, $limiter, new Yinghua\Repositories\ExternalRequestSlot($db));
$id = $input['placeId'];
$slug = $input['prefix'] . '-place';
$client = new Yinghua\DTO\ClientContext('owned', 'fallback-' . $id, 'owned', 'owned');
$checks = [];
$check = static function (string $name, bool $value) use (&$checks): void {
    if (!$value) {
        throw new RuntimeException($name);
    }$checks[] = $name;
};
$refresh = static function () use ($public, $records, $id): void {
    $public->snapshot = $records->find($id)?->publicArray() ?? [];
};
$db->execute("UPDATE attractions SET summary='',description='',google_place_id='ChIsynthetic123',version=version+1 WHERE id=:id", ['id' => $id]);
$refresh();
try {
    $check('original returned', $service->get($slug, $client)->text === 'Synthetic original text; never persisted.');
    $check('Google text not written to editor fields', $records->find($id)?->values['description'] === '');
    $provider->duringFetch = static function () use ($db, $id): void {
        $db->execute("UPDATE attractions SET status='archived',version=version+1 WHERE id=:id", ['id' => $id]);
    };
    $check('unpublish during vendor request discards response', $service->get($slug, $client)->text === '');
    $provider->duringFetch = null;
    $db->execute("UPDATE attractions SET status='published',version=version+1 WHERE id=:id", ['id' => $id]);
    $db->execute("UPDATE attractions SET description='Website description',version=version+1 WHERE id=:id", ['id' => $id]);
    $refresh();
    $before = $provider->calls;
    $service->get($slug, $client);
    $check('authored description avoids provider', $provider->calls === $before);
    $db->execute("UPDATE attractions SET status='archived',version=version+1 WHERE id=:id", ['id' => $id]);
    $service->get($slug, $client);
    $check('fresh archived row avoids provider', $provider->calls === $before);
    $db->execute("UPDATE attractions SET status='published',description='',version=version+1 WHERE id=:id", ['id' => $id]);
    $refresh();
    $other = new Yinghua\Repositories\ExternalRequestSlot(Yinghua\Repositories\Database::connect($config));
    $check('parallel slot held', $other->acquire());
    try {
        try {
            $service->get($slug, $client);
            throw new RuntimeException('Concurrent request accepted');
        } catch (Yinghua\Exceptions\ApplicationError $error) {
            $check('parallel request refused', $error->errorCode === 'RATE_LIMITED');
        }
    } finally {
        $other->release();
    }
    for ($i = 0; $i < 3; $i++) {
        $service->get($slug, $client);
    }
    $before = $provider->calls;
    try {
        $service->get($slug, $client);
        throw new RuntimeException('Place quota bypassed');
    } catch (Yinghua\Exceptions\ApplicationError $error) {
        $check('per-place quota enforced before provider', $error->errorCode === 'RATE_LIMITED' && $provider->calls === $before);
    }
} finally {
    $db->execute('UPDATE attractions SET google_place_id=NULL,version=version+1 WHERE id=:id', ['id' => $id]);
}
echo json_encode(['checks' => $checks, 'passed' => true, 'externalGoogleCalls' => 0], JSON_THROW_ON_ERROR);
