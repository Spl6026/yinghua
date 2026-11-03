<?php

declare(strict_types=1);

namespace Yinghua\Repositories;

use Yinghua\DTO\ItineraryStop;
use Yinghua\DTO\MemberItinerary;

/** 會員行程的存取。即使呼叫端已知行程 ID，SQL 仍一律加上會員本人與有效行程的條件。 */
class ItineraryRepository
{
    // 版本號以「每份行程」為單位：只有該行程的內容（名稱、日期、站點）改變或刪除時才加一。
    // 切換目前行程不改版本，所以同一位會員連續操作不同行程時不會互相造成衝突。
    public function __construct(private readonly Database $database) {}

    public function current(string $memberId, bool $lock = false): ?MemberItinerary
    {
        $values = $this->database->row(
            "SELECT id,member_id,title,trip_date,version FROM itineraries WHERE member_id=:member AND current_slot=1 AND status='active' AND deleted_at IS NULL" . ($lock ? ' FOR UPDATE' : ''),
            ['member' => $memberId],
        );

        return $values === null ? null : $this->map($values);
    }

    public function findOwned(string $memberId, string $id, bool $lock = false): ?MemberItinerary
    {
        $values = $this->database->row(
            "SELECT id,member_id,title,trip_date,version FROM itineraries WHERE member_id=:member AND id=:id AND status='active' AND deleted_at IS NULL" . ($lock ? ' FOR UPDATE' : ''),
            ['member' => $memberId, 'id' => $id],
        );

        return $values === null ? null : $this->map($values);
    }

    public function createCurrent(string $memberId, string $id): MemberItinerary
    {
        return $this->createOwned($memberId, $id, '我的旅行計畫', null);
    }

    public function item(string $memberId, string $id, string $attractionId): ?ItineraryStop
    {
        $values = $this->database->row(
            self::ITEM_SELECT . " WHERE t.member_id=:member AND t.id=:id AND t.deleted_at IS NULL AND t.status='active' AND i.attraction_id=:attraction",
            ['member' => $memberId, 'id' => $id, 'attraction' => $attractionId],
        );

        return $values === null ? null : $this->mapItem($values);
    }

    /** @return list<ItineraryStop> */
    public function items(string $memberId, string $id, int $limit, int $after = 0): array
    {
        return array_map($this->mapItem(...), $this->database->rows(
            self::ITEM_SELECT . " WHERE t.member_id=:member AND t.id=:id AND t.deleted_at IS NULL AND t.status='active' AND i.sort_order>:after ORDER BY i.sort_order LIMIT :limit",
            ['member' => $memberId, 'id' => $id, 'after' => $after, 'limit' => $limit],
        ));
    }

    public function append(string $memberId, string $itineraryId, string $itemId, string $attractionId, string $name): void
    {
        $row = $this->database->row(
            'SELECT COALESCE(MAX(i.sort_order),0) AS last_order FROM itinerary_items i JOIN itineraries t ON t.id=i.itinerary_id WHERE t.member_id=:member AND t.id=:id AND t.deleted_at IS NULL',
            ['member' => $memberId, 'id' => $itineraryId],
        );
        $order = (new Row($row ?? []))->integer('last_order') + 1;
        $this->database->execute(
            "INSERT INTO itinerary_items(id,itinerary_id,attraction_id,sort_order,snapshot_name) SELECT :item,t.id,:attraction,:order,:name FROM itineraries t WHERE t.member_id=:member AND t.id=:id AND t.deleted_at IS NULL AND t.status='active'",
            [
                'item' => $itemId,
                'attraction' => $attractionId,
                'order' => $order,
                'name' => $name,
                'member' => $memberId,
                'id' => $itineraryId,
            ],
        );
    }

    public function remove(string $memberId, string $id, string $attractionId): void
    {
        $this->database->execute(
            'DELETE i FROM itinerary_items i JOIN itineraries t ON t.id=i.itinerary_id WHERE t.member_id=:member AND t.id=:id AND t.deleted_at IS NULL AND i.attraction_id=:attraction',
            ['member' => $memberId, 'id' => $id, 'attraction' => $attractionId],
        );
    }

    public function advance(string $memberId, string $id): MemberItinerary
    {
        $this->database->execute(
            'UPDATE itineraries SET version=version+1,updated_at=UTC_TIMESTAMP() WHERE member_id=:member AND id=:id AND deleted_at IS NULL',
            ['member' => $memberId, 'id' => $id],
        );

        return $this->findOwned($memberId, $id) ?? throw new \RuntimeException('Updated itinerary missing.');
    }

    private const string ITEM_SELECT = 'SELECT i.id,i.attraction_id,i.sort_order,i.planned_time,i.note,i.snapshot_name FROM itinerary_items i JOIN itineraries t ON t.id=i.itinerary_id';

    /** @return array{items:list<MemberItinerary>,total:int,stopCounts:array<string,int>} */
    public function collection(string $memberId, int $page, int $size): array
    {
        $where = "member_id=:member AND status='active' AND deleted_at IS NULL";
        $count = new Row($this->database->row('SELECT COUNT(*) AS total FROM itineraries WHERE ' . $where, ['member' => $memberId]) ?? []);
        // 總覽要顯示每份行程的地點數；以子查詢一次算完，不必逐份再讀站點。
        $rows = $this->database->rows(
            'SELECT id,member_id,title,trip_date,version,(SELECT COUNT(*) FROM itinerary_items i WHERE i.itinerary_id=itineraries.id) AS stop_count FROM itineraries WHERE ' . $where . ' ORDER BY updated_at DESC,id DESC LIMIT :size OFFSET :offset',
            ['member' => $memberId,'size' => $size,'offset' => ($page - 1) * $size],
        );
        $stops = [];
        foreach ($rows as $values) {
            $row = new Row($values);
            $stops[$row->string('id')] = $row->integer('stop_count');
        }
        return ['items' => array_map($this->map(...), $rows),'total' => $count->integer('total'),'stopCounts' => $stops];
    }

    /**
     * 每份行程依順序取前幾站，供總覽卡片預覽；一次查完整頁行程，不逐份讀取。
     *
     * @param list<string> $ids
     * @return array<string,list<array{attractionId:string,snapshotName:string}>>
     */
    public function previewStops(string $memberId, array $ids, int $limit): array
    {
        if ($ids === []) {
            return [];
        }
        [$placeholders, $parameters] = Database::inList('trip', $ids);
        $rows = $this->database->rows(
            'SELECT itinerary_id,attraction_id,snapshot_name FROM (SELECT i.itinerary_id,i.attraction_id,i.snapshot_name,i.sort_order,'
            . 'ROW_NUMBER() OVER (PARTITION BY i.itinerary_id ORDER BY i.sort_order) AS position FROM itinerary_items i '
            . 'JOIN itineraries t ON t.id=i.itinerary_id WHERE t.member_id=:member AND t.deleted_at IS NULL AND i.itinerary_id IN (' . $placeholders . ')'
            . ') ranked WHERE position<=:limit ORDER BY itinerary_id,position',
            ['member' => $memberId, 'limit' => $limit, ...$parameters],
        );
        $result = [];
        foreach ($rows as $values) {
            $row = new Row($values);
            $result[$row->string('itinerary_id')][] = ['attractionId' => $row->string('attraction_id'), 'snapshotName' => $row->string('snapshot_name')];
        }

        return $result;
    }

    /** @return list<ItineraryStop> */
    public function allItems(string $memberId, string $id): array
    {
        return array_map($this->mapItem(...), $this->database->rows(
            self::ITEM_SELECT . " WHERE t.member_id=:member AND t.id=:id AND t.status='active' AND t.deleted_at IS NULL ORDER BY i.sort_order",
            ['member' => $memberId,'id' => $id],
        ));
    }

    public function createOwned(string $memberId, string $id, string $title, ?string $date): MemberItinerary
    {
        $this->database->execute(
            'UPDATE itineraries SET current_slot=NULL WHERE member_id=:member AND current_slot=1',
            ['member' => $memberId],
        );
        try {
            $this->database->execute(
                'INSERT INTO itineraries (id,member_id,title,trip_date,current_slot,version) VALUES (:id,:member,:title,:date,1,1)',
                ['id' => $id,'member' => $memberId,'title' => $title,'date' => $date],
            );
        } catch (\PDOException $error) {
            if (Database::isDuplicateKey($error)) {
                throw new \Yinghua\Exceptions\ContentConflict('Itinerary identity unavailable.', previous: $error);
            }
            throw $error;
        }
        return $this->findOwned($memberId, $id) ?? throw new \RuntimeException('Created itinerary missing.');
    }

    public function selectCurrent(string $memberId, string $id): MemberItinerary
    {
        if ($this->current($memberId)?->id === $id) {
            return $this->findOwned($memberId, $id) ?? throw new \RuntimeException('Itinerary missing.');
        }
        $this->database->execute(
            'UPDATE itineraries SET current_slot=NULL WHERE member_id=:member AND current_slot=1',
            ['member' => $memberId],
        );
        $this->database->execute(
            'UPDATE itineraries SET current_slot=1,updated_at=UTC_TIMESTAMP() WHERE member_id=:member AND id=:id AND deleted_at IS NULL AND status=\'active\'',
            ['member' => $memberId,'id' => $id],
        );
        return $this->findOwned($memberId, $id) ?? throw new \RuntimeException('Selected itinerary missing.');
    }

    /** @param list<ItineraryStop> $items */
    public function saveOwned(string $memberId, string $id, string $title, ?string $date, array $items): MemberItinerary
    {
        $this->database->execute(
            'UPDATE itineraries SET title=:title,trip_date=:date WHERE member_id=:member AND id=:id AND deleted_at IS NULL',
            ['title' => $title,'date' => $date,'member' => $memberId,'id' => $id],
        );
        $this->database->execute(
            'DELETE i FROM itinerary_items i JOIN itineraries t ON t.id=i.itinerary_id WHERE t.member_id=:member AND t.id=:id AND t.deleted_at IS NULL',
            ['member' => $memberId,'id' => $id],
        );
        foreach ($items as $item) {
            $this->database->execute(
                "INSERT INTO itinerary_items (id,itinerary_id,attraction_id,sort_order,planned_time,note,snapshot_name) SELECT :item,t.id,:place,:position,:time,:note,:name FROM itineraries t WHERE t.member_id=:member AND t.id=:id AND t.deleted_at IS NULL AND t.status='active'",
                [
                    'item' => $item->id,
                    'place' => $item->attractionId,
                    'position' => $item->sortOrder,
                    'time' => $item->plannedTime,
                    'note' => $item->note,
                    'name' => $item->snapshotName,
                    'member' => $memberId,
                    'id' => $id,
                ],
            );
        }
        return $this->advance($memberId, $id);
    }

    public function deleteOwned(string $memberId, string $id): void
    {
        $wasCurrent = $this->current($memberId)?->id === $id;
        $this->database->execute(
            "UPDATE itineraries SET deleted_at=UTC_TIMESTAMP(),current_slot=NULL,status='deleted',version=version+1 WHERE member_id=:member AND id=:id AND deleted_at IS NULL",
            ['member' => $memberId,'id' => $id],
        );
        if ($wasCurrent) {
            $next = $this->collection($memberId, 1, 1)['items'][0] ?? null;
            if ($next !== null) {
                $this->selectCurrent($memberId, $next->id);
            }
        }
    }

    /** @param array<string,mixed> $values */
    private function map(array $values): MemberItinerary
    {
        $row = new Row($values);

        return new MemberItinerary(
            $row->string('id'),
            $row->string('member_id'),
            $row->string('title'),
            $row->nullableString('trip_date'),
            $row->integer('version'),
        );
    }

    /** @param array<string,mixed> $values */
    private function mapItem(array $values): ItineraryStop
    {
        $row = new Row($values);

        return new ItineraryStop(
            $row->string('id'),
            $row->string('attraction_id'),
            $row->integer('sort_order'),
            $row->nullableString('planned_time'),
            $row->string('note'),
            $row->string('snapshot_name'),
        );
    }
}
