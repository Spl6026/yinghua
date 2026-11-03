<?php

declare(strict_types=1);

namespace Yinghua\Repositories;

use Yinghua\DTO\FavoriteRecord;

/** 每個查詢都帶上會員本人的條件，確保不會回傳其他會員的收藏。 */
class FavoriteRepository
{
    public function __construct(private readonly Database $database) {}

    public function exists(string $memberId, string $attractionId): bool
    {
        return $this->database->row(
            'SELECT attraction_id FROM favorites WHERE member_id=:member AND attraction_id=:attraction',
            ['member' => $memberId, 'attraction' => $attractionId],
        ) !== null;
    }

    /** @param list<string> $ids
     * @return list<string>
     */
    public function status(string $memberId, array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        [$placeholders, $parameters] = Database::inList('id', $ids);
        $parameters['member'] = $memberId;

        return array_map(
            static fn(array $row): string => (new Row($row))->string('attraction_id'),
            $this->database->rows(
                'SELECT attraction_id FROM favorites WHERE member_id=:member AND attraction_id IN (' . $placeholders . ')',
                $parameters,
            ),
        );
    }

    /** @param array{at:string,id:string}|null $after
     * @return list<FavoriteRecord>
     */
    public function page(string $memberId, int $limit, ?array $after = null): array
    {
        $where = 'member_id=:member';
        $parameters = ['member' => $memberId, 'limit' => $limit];
        if ($after !== null) {
            $where .= ' AND (created_at<:at OR (created_at=:same_at AND attraction_id<:id))';
            $parameters += ['at' => $after['at'], 'same_at' => $after['at'], 'id' => $after['id']];
        }

        return array_map(
            static function (array $values): FavoriteRecord {
                $row = new Row($values);

                return new FavoriteRecord($row->string('attraction_id'), $row->string('created_at'));
            },
            $this->database->rows(
                'SELECT attraction_id,created_at FROM favorites WHERE ' . $where . ' ORDER BY created_at DESC,attraction_id DESC LIMIT :limit',
                $parameters,
            ),
        );
    }

    public function add(string $memberId, string $attractionId): void
    {
        $this->database->execute(
            'INSERT INTO favorites(member_id,attraction_id) VALUES(:member,:attraction)',
            ['member' => $memberId, 'attraction' => $attractionId],
        );
    }

    public function remove(string $memberId, string $attractionId): void
    {
        $this->database->execute(
            'DELETE FROM favorites WHERE member_id=:member AND attraction_id=:attraction',
            ['member' => $memberId, 'attraction' => $attractionId],
        );
    }
}
