<?php

declare(strict_types=1);

namespace Yinghua\Repositories;

use Yinghua\DTO\FilmScreening;
use Yinghua\DTO\ScreeningInput;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Support\Secrets;
use Yinghua\Support\UtcDateTime;
use Yinghua\Exceptions\ErrorCode;

/** 電影場次的存取；與電影主檔共用同一個 PDO，一起參與同一筆交易。 */
class FilmScreeningRepository
{
    public function __construct(private readonly Database $database) {}

    /** @param list<string> $ids
     * @return array<string,list<FilmScreening>>
     */
    public function forFilms(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        [$placeholders, $parameters] = Database::inList('id', $ids);
        $result = [];
        foreach ($this->database->rows(
            'SELECT s.id,s.film_id,s.starts_at,s.ticket_url,f.ticket_url AS default_ticket FROM film_screenings s JOIN films f ON f.id=s.film_id WHERE s.film_id IN (' . $placeholders . ') ORDER BY s.starts_at,s.id',
            $parameters,
        ) as $values) {
            $row = new Row($values);
            $ticket = $row->string('ticket_url');
            $result[$row->string('film_id')][] = new FilmScreening(
                $row->string('id'),
                UtcDateTime::iso($row->string('starts_at')),
                $ticket,
                $ticket !== '' ? $ticket : $row->string('default_ticket'),
            );
        }

        return $result;
    }

    /**
     * 呼叫端已持有全域發布寫入鎖，所以不同電影也不會同時搶到同一個開始分鐘。
     *
     * @param list<ScreeningInput> $items
     */
    public function assertAvailable(string $filmId, array $items): void
    {
        foreach ($items as $index => $item) {
            $exists = $this->database->row(
                "SELECT s.id FROM film_screenings s JOIN films f ON f.id=s.film_id WHERE s.starts_at=:starts AND f.id<>:film AND f.deleted_at IS NULL AND f.status<>'archived' LIMIT 1",
                ['starts' => $item->startsAt->format('Y-m-d H:i:s'),'film' => $filmId],
            );
            if ($exists !== null) {
                throw new ApplicationError(
                    ErrorCode::Conflict,
                    '所選日期與開始時間已有場次。',
                    ['screenings.' . $index . '.startsAt' => '此開始時間已有其他場次。'],
                );
            }
        }
    }

    /** @param list<ScreeningInput> $items */
    public function replace(string $filmId, array $items): void
    {
        // 先確認場次屬於這部電影再刪除或寫入；拿別部電影的場次 ID 來送也改不到任何資料。
        $created = [];
        foreach ($items as $index => $item) {
            if ($item->id !== null) {
                $existing = $this->database->row(
                    'SELECT created_at FROM film_screenings WHERE id=:id AND film_id=:film',
                    ['id' => $item->id,'film' => $filmId],
                );
                if ($existing === null) {
                    throw ApplicationError::invalid('screenings.' . $index . '.id', '找不到屬於此電影的場次。');
                }
                $created[$item->id] = (new Row($existing))->string('created_at');
            }
        }
        $this->database->execute('DELETE FROM film_screenings WHERE film_id=:film', ['film' => $filmId]);
        foreach ($items as $item) {
            $this->database->execute(
                'INSERT INTO film_screenings (id,film_id,starts_at,ticket_url,created_at) VALUES (:id,:film,:starts,:ticket,COALESCE(:created,UTC_TIMESTAMP()))',
                [
                    'id' => $item->id ?? Secrets::uuid(),
                    'film' => $filmId,
                    'starts' => $item->startsAt->format('Y-m-d H:i:s'),
                    'ticket' => $item->ticketUrl,
                    'created' => $item->id === null ? null : $created[$item->id],
                ],
            );
        }
    }
}
