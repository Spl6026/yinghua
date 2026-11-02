<?php

declare(strict_types=1);

namespace Yinghua\Adapters;

use RuntimeException;
use Yinghua\Contracts\Transactions;
use Yinghua\DTO\ClientContext;
use Yinghua\DTO\FilmInput;
use Yinghua\Repositories\Database;
use Yinghua\Repositories\EventRepository;
use Yinghua\Repositories\FilmRepository;
use Yinghua\Support\Secrets;

/** 匯入電影示範資料。原始資料沒有放映年份，所以不去猜年份或結束時間。 */
class FilmSeeder
{
    public function __construct(
        private readonly Database $database,
        private readonly FilmRepository $films,
        private readonly Transactions $transactions,
        private readonly EventRepository $events,
    ) {}

    public function seed(string $path, string $actorId, ClientContext $client): void
    {
        $text = file_get_contents($path);
        $items = $text === false ? null : json_decode($text, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($items) || !array_is_list($items) || count($items) !== 4) {
            throw new RuntimeException('Invalid controlled film source.');
        }
        $this->transactions->run(function () use ($items, $actorId, $client): void {
            if ($this->database->row(
                "SELECT id FROM admin_users WHERE id=:id AND is_root=1 AND status='active' AND deleted_at IS NULL FOR UPDATE",
                ['id' => $actorId],
            ) === null) {
                throw new RuntimeException('Active fixed administrator required.');
            }
            foreach ($items as $index => $item) {
                if (!is_array($item) || !is_string($item['title'] ?? null) || !is_string($item['slug'] ?? null)
                    || !is_string($item['genre'] ?? null) || !is_string($item['synopsis'] ?? null)
                    || !is_string($item['posterUrl'] ?? null) || parse_url($item['posterUrl'], PHP_URL_HOST) !== 'images.unsplash.com') {
                    throw new RuntimeException('Unexpected controlled film.');
                }
                if ($this->database->row('SELECT id FROM films WHERE prototype_id=:prototypeId', ['prototypeId' => $index]) !== null) {
                    continue;
                }
                $input = FilmInput::fromArray([
                    'title' => $item['title'],
                    'slug' => $item['slug'],
                    'genre' => $item['genre'],
                    'synopsis' => $item['synopsis'],
                    'posterUrl' => $item['posterUrl'],
                    'posterAlt' => $item['title'] . '電影海報',
                    'posterSource' => '原始交付展示素材',
                    'posterLicense' => '正式上線前須確認授權',
                    'status' => 'published',
                ], true);
                $id = Secrets::uuid();
                $this->films->save($id, $input, true, $actorId);
                $this->database->execute('UPDATE films SET prototype_id=:prototypeId WHERE id=:id', ['prototypeId' => $index,'id' => $id]);
                $this->events->content($actorId, 'film-seed', 'film', $id, null, 1, 'published', $client);
            }
        });
    }
}
