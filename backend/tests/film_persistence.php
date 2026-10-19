<?php

declare(strict_types=1);

/** [E-25] 真實 MariaDB 的資料層測試；所有資料在 finally 回滾。 */
require dirname(__DIR__) . '/vendor/autoload.php';

use Yinghua\Config;
use Yinghua\DTO\FilmInput;
use Yinghua\DTO\ScreeningInput;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Repositories\Database;
use Yinghua\Repositories\FilmRepository;
use Yinghua\Repositories\FilmScreeningRepository;
use Yinghua\Support\Secrets;

$db = Database::connect(new Config());
if (($db->row('SELECT DATABASE() AS name')['name'] ?? '') !== 'yinghua_test') {
    throw new RuntimeException('Isolated test database required.');
}
$checks = 0;
$check = static function (bool $passed) use (&$checks): void {
    if (!$passed) {
        throw new RuntimeException('Film persistence check failed.');
    }
    $checks++;
};
$db->pdo->beginTransaction();
try {
    $screenings = new FilmScreeningRepository($db);
    $films = new FilmRepository($db, $screenings);
    $first = Secrets::uuid();
    $second = Secrets::uuid();
    foreach ([$first, $second] as $id) {
        $films->save($id, FilmInput::fromArray(['title' => '隔離電影測試','slug' => 'test-' . str_replace('-', '', $id)], true), true, null);
    }
    $a = ScreeningInput::fromArray(['startsAt' => '2090-10-19T14:00:00+08:00']);
    $b = ScreeningInput::fromArray(['startsAt' => '2090-10-19T14:30:00+08:00']);
    $screenings->replace($first, [$a, $b]);
    $film = $films->find($first, true);
    $check($film !== null && count($film->screenings) === 2);
    $check($film?->screenings[1]->startsAt === '2090-10-19T06:30:00+00:00');
    try {
        $screenings->assertAvailable($second, [$a]);
        $check(false);
    } catch (ApplicationError $error) {
        $check($error->errorCode === 'CONFLICT');
    }
    $screenings->assertAvailable($second, [ScreeningInput::fromArray(['startsAt' => '2090-10-20T14:00:00+08:00'])]);
    $check(true);
    try {
        $screenings->replace($second, [new ScreeningInput($film?->screenings[0]->id, $a->startsAt, '')]);
        $check(false);
    } catch (ApplicationError $error) {
        $check($error->errorCode === 'VALIDATION_FAILED');
    }
    $check(count($films->find($first)?->screenings ?? []) === 2);
    $db->execute("UPDATE films SET status='archived' WHERE id=:id", ['id' => $first]);
    $screenings->assertAvailable($second, [$a]);
    $check(true);
    $check(isset($screenings->forFilms([$first, $second])[$first]));
    echo json_encode(['filmPersistenceChecks' => $checks,'rolledBack' => true], JSON_THROW_ON_ERROR) . "\n";
} finally {
    $db->pdo->rollBack();
}
