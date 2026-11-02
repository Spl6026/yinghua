<?php

declare(strict_types=1);

namespace Yinghua\Repositories;

use Yinghua\DTO\{AdminContentQuery, RecommendedRoute, RecommendedRouteInput};
use Yinghua\Exceptions\ApplicationError;

/** 推薦路線的存取；路線主檔與有順序的站點在同一筆交易內寫入，所有輸入都用綁定參數。 */
class RecommendedRouteRepository
{
    public function __construct(private readonly Database $database) {}

    /** @return array{items:list<array<string,mixed>>,total:int,page:int,pageSize:int} */
    public function listing(AdminContentQuery $query): array
    {
        $where = 'deleted_at IS NULL';
        $params = [];
        if ($query->q !== '') {
            $where .= " AND title LIKE :q ESCAPE '!'";
            $params['q'] = '%' . str_replace(['!','%','_'], ['!!','!%','!_'], $query->q) . '%';
        }
        if ($query->status !== '') {
            $where .= ' AND status=:status';
            $params['status'] = $query->status;
        }
        $count = new Row($this->database->row('SELECT COUNT(*) AS total FROM routes WHERE ' . $where, $params) ?? []);
        $rows = $this->database->rows(
            'SELECT * FROM routes WHERE ' . $where . ' ORDER BY sort_order,id LIMIT :size OFFSET :offset',
            [...$params,'size' => $query->pageSize,'offset' => ($query->page - 1) * $query->pageSize],
        );
        return [
            'items' => array_map(fn(array $row): array => $this->map($row)->toArray(), $rows),
            'total' => $count->integer('total'),
            'page' => $query->page,
            'pageSize' => $query->pageSize,
        ];
    }

    public function find(string $id, bool $lock = false): ?RecommendedRoute
    {
        $row = $this->database->row('SELECT * FROM routes WHERE id=:id' . ($lock ? ' FOR UPDATE' : ''), ['id' => $id]);
        return $row === null ? null : $this->map($row);
    }

    /** 鎖定路線引用的景點，避免儲存時剛好有人下架或刪除；依 ID 固定順序上鎖以免死鎖。 */
    public function assertAvailable(RecommendedRouteInput $input): void
    {
        $ids = array_map(static fn(\Yinghua\DTO\RouteStopInput $stop): string => $stop->attractionId, $input->stops);
        sort($ids);
        foreach ($ids as $id) {
            $row = $this->database->row(
                "SELECT id FROM attractions WHERE id=:id AND deleted_at IS NULL AND status='published' AND publish_at<=UTC_TIMESTAMP() FOR UPDATE",
                ['id' => $id],
            );
            if ($row === null) {
                throw ApplicationError::invalid('stops', '每個景點都必須存在、已發布且可用；請移除或更換不可用站點。');
            }
        }
    }

    public function save(string $id, RecommendedRouteInput $input, bool $creating): void
    {
        $params = [
            'id' => $id,
            'title' => $input->title,
            'theme' => $input->theme,
            'summary' => $input->summary,
            'duration' => $input->durationMinutes,
            'ordering' => $input->sortOrder,
            'status' => $input->status->value,
        ];
        if ($creating) {
            $this->database->execute(
                'INSERT INTO routes (id,slug,title,theme,summary,duration_minutes,sort_order,status,published_at) VALUES (:id,:slug,:title,:theme,:summary,:duration,:ordering,:status,UTC_TIMESTAMP())',
                [...$params,'slug' => $id],
            );
        } else {
            $this->database->execute(
                'UPDATE routes SET title=:title,theme=:theme,summary=:summary,duration_minutes=:duration,sort_order=:ordering,status=:status,published_at=COALESCE(published_at,UTC_TIMESTAMP()),updated_at=UTC_TIMESTAMP(),version=version+1 WHERE id=:id',
                $params,
            );
        }
        $this->database->execute('DELETE FROM route_attractions WHERE route_id=:id', ['id' => $id]);
        foreach ($input->stops as $order => $stop) {
            $this->database->execute(
                'INSERT INTO route_attractions (route_id,attraction_id,sort_order,suggested_time,note) VALUES (:route,:place,:position,:time,:note)',
                ['route' => $id,'place' => $stop->attractionId,'position' => $order,'time' => $stop->suggestedTime,'note' => $stop->note],
            );
        }
    }

    public function delete(string $id): void
    {
        $this->database->execute(
            "UPDATE routes SET status='archived',deleted_at=UTC_TIMESTAMP(),version=version+1 WHERE id=:id",
            ['id' => $id],
        );
        // 保留路線主檔的識別，方便日後追溯行程來源；同時解除站點引用，讓景點可以正常刪除。
        $this->database->execute('DELETE FROM route_attractions WHERE route_id=:id', ['id' => $id]);
    }

    /** @return list<RecommendedRoute> */
    public function published(): array
    {
        return array_map($this->map(...), $this->database->rows(
            "SELECT * FROM routes WHERE deleted_at IS NULL AND status='published' AND published_at<=UTC_TIMESTAMP() ORDER BY sort_order,id",
        ));
    }

    /** @return list<array{id:string,name:string}> */
    public function candidates(string $query, int $page): array
    {
        $rows = $this->database->rows(
            "SELECT id,name FROM attractions WHERE deleted_at IS NULL AND status='published' AND publish_at<=UTC_TIMESTAMP() AND name LIKE :q ESCAPE '!' ORDER BY name,id LIMIT 21 OFFSET :offset",
            ['q' => '%' . str_replace(['!','%','_'], ['!!','!%','!_'], $query) . '%','offset' => ($page - 1) * 20],
        );
        return array_map(
            static fn(array $row): array => ['id' => (new Row($row))->string('id'),'name' => (new Row($row))->string('name')],
            $rows,
        );
    }

    /** @param array<string,mixed> $values */
    private function map(array $values): RecommendedRoute
    {
        $r = new Row($values);
        $stops = [];
        foreach ($this->database->rows(
            "SELECT ra.*,a.name,(a.deleted_at IS NULL AND a.status='published' AND a.publish_at<=UTC_TIMESTAMP()) AS available FROM route_attractions ra JOIN attractions a ON a.id=ra.attraction_id WHERE ra.route_id=:id ORDER BY ra.sort_order",
            ['id' => $r->string('id')],
        ) as $stopValues) {
            $s = new Row($stopValues);
            $time = $s->nullableString('suggested_time');
            $stops[] = [
                'attractionId' => $s->string('attraction_id'),
                'sortOrder' => $s->integer('sort_order'),
                'suggestedTime' => $time === null ? null : substr($time, 0, 5),
                'note' => $s->string('note'),
                'name' => $s->string('name'),
                'available' => $s->integer('available') === 1,
            ];
        }
        return new RecommendedRoute(
            $r->string('id'),
            $r->string('slug'),
            $r->string('title'),
            $r->string('theme'),
            $r->string('summary'),
            $values['duration_minutes'] === null ? null : $r->integer('duration_minutes'),
            $r->integer('sort_order'),
            $r->string('status'),
            $r->integer('version'),
            $r->nullableString('deleted_at'),
            $stops,
        );
    }
}
