<?php

declare(strict_types=1);

namespace Yinghua\Repositories;

use Yinghua\DTO\ItineraryStop;
use Yinghua\DTO\MemberItinerary;

/** [S：G15；D：C19–D20；E-27] 即使內部知道行程 ID，SQL 仍須套會員與有效行程。 */
class ItineraryRepository
{
    public function __construct(private readonly Database $database) {}

    public function current(string $memberId, bool $lock = false): ?MemberItinerary
    {
        $values = $this->database->row("SELECT id,member_id,title,trip_date,version FROM itineraries WHERE member_id=:member AND current_slot=1 AND status='active' AND deleted_at IS NULL" . ($lock ? ' FOR UPDATE' : ''), ['member' => $memberId]);

        return $values === null ? null : $this->map($values);
    }

    public function findOwned(string $memberId, string $id): ?MemberItinerary
    {
        $values = $this->database->row("SELECT id,member_id,title,trip_date,version FROM itineraries WHERE member_id=:member AND id=:id AND status='active' AND deleted_at IS NULL", ['member' => $memberId, 'id' => $id]);

        return $values === null ? null : $this->map($values);
    }

    public function createCurrent(string $memberId, string $id): MemberItinerary
    {
        $this->database->execute('INSERT INTO itineraries(id,member_id,current_slot) VALUES(:id,:member,1)', ['id' => $id, 'member' => $memberId]);

        return $this->findOwned($memberId, $id) ?? throw new \RuntimeException('Created itinerary missing.');
    }

    public function item(string $memberId, string $id, string $attractionId): ?ItineraryStop
    {
        $values = $this->database->row(self::ITEM_SELECT . " WHERE t.member_id=:member AND t.id=:id AND t.deleted_at IS NULL AND t.status='active' AND i.attraction_id=:attraction", ['member' => $memberId, 'id' => $id, 'attraction' => $attractionId]);

        return $values === null ? null : $this->mapItem($values);
    }

    /** @return list<ItineraryStop> */
    public function items(string $memberId, string $id, int $limit, int $after = 0): array
    {
        return array_map($this->mapItem(...), $this->database->rows(self::ITEM_SELECT . " WHERE t.member_id=:member AND t.id=:id AND t.deleted_at IS NULL AND t.status='active' AND i.sort_order>:after ORDER BY i.sort_order LIMIT :limit", ['member' => $memberId, 'id' => $id, 'after' => $after, 'limit' => $limit]));
    }

    public function append(string $memberId, string $itineraryId, string $itemId, string $attractionId, string $name): void
    {
        $row = $this->database->row('SELECT COALESCE(MAX(i.sort_order),0) AS last_order FROM itinerary_items i JOIN itineraries t ON t.id=i.itinerary_id WHERE t.member_id=:member AND t.id=:id AND t.deleted_at IS NULL', ['member' => $memberId, 'id' => $itineraryId]);
        $order = (new Row($row ?? []))->integer('last_order') + 1;
        $this->database->execute("INSERT INTO itinerary_items(id,itinerary_id,attraction_id,sort_order,snapshot_name) SELECT :item,t.id,:attraction,:order,:name FROM itineraries t WHERE t.member_id=:member AND t.id=:id AND t.deleted_at IS NULL AND t.status='active'", ['item' => $itemId, 'attraction' => $attractionId, 'order' => $order, 'name' => $name, 'member' => $memberId, 'id' => $itineraryId]);
    }

    public function remove(string $memberId, string $id, string $attractionId): void
    {
        $this->database->execute('DELETE i FROM itinerary_items i JOIN itineraries t ON t.id=i.itinerary_id WHERE t.member_id=:member AND t.id=:id AND t.deleted_at IS NULL AND i.attraction_id=:attraction', ['member' => $memberId, 'id' => $id, 'attraction' => $attractionId]);
    }

    public function advance(string $memberId, string $id): MemberItinerary
    {
        $this->database->execute('UPDATE itineraries SET version=version+1,updated_at=UTC_TIMESTAMP() WHERE member_id=:member AND id=:id AND deleted_at IS NULL', ['member' => $memberId, 'id' => $id]);

        return $this->findOwned($memberId, $id) ?? throw new \RuntimeException('Updated itinerary missing.');
    }

    private const string ITEM_SELECT = 'SELECT i.id,i.attraction_id,i.sort_order,i.planned_time,i.note,i.snapshot_name FROM itinerary_items i JOIN itineraries t ON t.id=i.itinerary_id';

    /** @param array<string,mixed> $values */
    private function map(array $values): MemberItinerary
    {
        $row = new Row($values);

        return new MemberItinerary($row->string('id'), $row->string('member_id'), $row->string('title'), $row->nullableString('trip_date'), $row->integer('version'));
    }

    /** @param array<string,mixed> $values */
    private function mapItem(array $values): ItineraryStop
    {
        $row = new Row($values);

        return new ItineraryStop($row->string('id'), $row->string('attraction_id'), $row->integer('sort_order'), $row->nullableString('planned_time'), $row->string('note'), $row->string('snapshot_name'));
    }
}
