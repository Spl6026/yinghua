<?php

declare(strict_types=1);

/** [S：G15；D：C19–D21；E-27] 真實 MariaDB 約束／本人條件；自有 fixture 全部回滾。 */
require dirname(__DIR__) . '/vendor/autoload.php';

use Yinghua\Config;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\IdentityKind;
use Yinghua\Repositories\AccountRepository;
use Yinghua\Repositories\Database;
use Yinghua\Repositories\FavoriteRepository;
use Yinghua\Repositories\ItineraryRepository;
use Yinghua\Services\MemberAccess;
use Yinghua\Support\Secrets;

$db = Database::connect(new Config());
if (($db->row('SELECT DATABASE() AS name')['name'] ?? '') !== 'yinghua_test') {
    throw new RuntimeException('Isolated database required.');
}
$checks = 0;
$check = static function (bool $ok) use (&$checks): void {
    if (!$ok) {
        throw new RuntimeException('Member persistence check failed at ' . ($checks + 1));
    }
    $checks++;
};
$reject = static function (callable $operation) use ($check): void {
    try {
        $operation();
    } catch (PDOException $error) {
        $check(in_array($error->getCode(), ['23000', 'HY000'], true));

        return;
    }
    throw new RuntimeException('Expected database constraint rejection.');
};
$member = Secrets::uuid();
$other = Secrets::uuid();
$place = Secrets::uuid();
$second = Secrets::uuid();
$itineraryId = Secrets::uuid();
$db->pdo->beginTransaction();
try {
    foreach ([$member, $other] as $id) {
        $db->execute('INSERT INTO members(id,email,display_name,password_hash) VALUES(:id,:email,:name,:hash)', ['id' => $id, 'email' => $id . '@example.test', 'name' => '隔離會員', 'hash' => password_hash(bin2hex(random_bytes(20)), PASSWORD_ARGON2ID)]);
    }
    foreach ([$place, $second] as $id) {
        $db->execute("INSERT INTO attractions(id,slug,name,category,summary,description,address,publish_at) VALUES(:id,:slug,'公開快照名稱','景點','','','',UTC_TIMESTAMP())", ['id' => $id, 'slug' => 'member-test-' . $id]);
    }
    $favorites = new FavoriteRepository($db);
    $trips = new ItineraryRepository($db);
    $accounts = new AccountRepository($db);
    $access = new MemberAccess($accounts);
    $actor = $accounts->find(IdentityKind::Member, $member) ?? throw new RuntimeException('Fixture missing.');
    $check($access->require($actor, true)->id === $member);
    $check($trips->current($member) === null);
    $check($favorites->page($member, 21) === []);
    $favorites->add($member, $place);
    $favorites->add($member, $second);
    $check($favorites->exists($member, $place) && !$favorites->exists($other, $place));
    $check(count($favorites->status($member, [$place, $second])) === 2 && $favorites->status($other, [$place]) === []);
    $page = $favorites->page($member, 1);
    $check(count($page) === 1);
    $check(count($favorites->page($member, 10, ['at' => $page[0]->createdAt, 'id' => $page[0]->attractionId])) === 1);
    $reject(static fn() => $favorites->add($member, $place));
    $favorites->remove($other, $place);
    $check($favorites->exists($member, $place));
    $trip = $trips->createCurrent($member, $itineraryId);
    $check($trip->version === 1 && $trip->tripDate === null && $trip->title === '我的旅行計畫');
    $check($trips->findOwned($other, $itineraryId) === null);
    $reject(static fn() => $trips->createCurrent($member, Secrets::uuid()));
    $trips->append($member, $itineraryId, Secrets::uuid(), $place, '加入當時名稱');
    $check($trips->item($member, $itineraryId, $place)?->snapshotName === '加入當時名稱');
    $check($trips->item($other, $itineraryId, $place) === null && $trips->items($other, $itineraryId, 10) === []);
    $reject(static fn() => $trips->append($member, $itineraryId, Secrets::uuid(), $place, '重複站點'));
    $trips->append($member, $itineraryId, Secrets::uuid(), $second, '第二站');
    $check(array_map(static fn($item) => $item->sortOrder, $trips->items($member, $itineraryId, 10)) === [1, 2]);
    $trips->remove($other, $itineraryId, $place);
    $check($trips->item($member, $itineraryId, $place) !== null);
    $check($trips->advance($member, $itineraryId)->version === 2);
    $db->execute("UPDATE attractions SET name='未公開的新名稱',status='archived',deleted_at=UTC_TIMESTAMP() WHERE id=:id", ['id' => $place]);
    $check($trips->item($member, $itineraryId, $place)?->snapshotName === '加入當時名稱');
    $trips->remove($member, $itineraryId, $place);
    $check(count($trips->items($member, $itineraryId, 10)) === 1);
    $trips->remove($member, $itineraryId, $second);
    $check($trips->current($member) !== null && $trips->items($member, $itineraryId, 10) === []);
    $reject(static fn() => $db->execute('UPDATE itineraries SET current_slot=0 WHERE id=:id', ['id' => $itineraryId]));
    $reject(static fn() => $db->execute('UPDATE itineraries SET deleted_at=UTC_TIMESTAMP() WHERE id=:id', ['id' => $itineraryId]));
    $db->execute("UPDATE members SET status='disabled' WHERE id=:id", ['id' => $member]);
    try {
        $access->require($actor, true);
        throw new RuntimeException('Disabled member accepted.');
    } catch (ApplicationError $error) {
        $check($error->errorCode === 'UNAUTHENTICATED');
    }
} finally {
    $db->pdo->rollBack();
}
$check($db->row('SELECT id FROM members WHERE id=:id', ['id' => $member]) === null);
echo json_encode(['memberPersistenceChecks' => $checks, 'rolledBack' => true]), PHP_EOL;
