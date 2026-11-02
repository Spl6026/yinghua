<?php

declare(strict_types=1);

namespace Yinghua\Repositories;

/** SQL 的表名／欄名只來自固定對照表；各模組都要先經 Service 授權才會查詢。 */
class OperationsRepository
{
    private const array SOURCES = [
        'attractions' => ['attractions', 'name'],
        'films' => ['films', 'title'],
        'news' => ['news_posts', 'title'],
        'offers' => ['offers', 'title'],
        'members' => ['members', 'display_name'],
    ];

    public function __construct(private readonly Database $database) {}

    public function count(string $module): int
    {
        $table = self::SOURCES[$module][0];
        return (new Row($this->database->row(
            'SELECT COUNT(*) AS total FROM ' . $table . ' WHERE deleted_at IS NULL',
        ) ?? []))->integer('total');
    }

    /** @return list<array{module:string,title:string,status:string,updatedAt:string}> */
    public function recent(string $module): array
    {
        if ($module === 'members') {
            return [];
        }
        [$table, $title] = self::SOURCES[$module];
        $items = [];
        foreach ($this->database->rows(
            'SELECT ' . $title . ' AS title,status,updated_at FROM ' . $table . ' WHERE deleted_at IS NULL ORDER BY updated_at DESC,id DESC LIMIT 5',
        ) as $values) {
            $row = new Row($values);
            $items[] = [
                'module' => $module,
                'title' => $row->string('title'),
                'status' => $row->string('status'),
                'updatedAt' => $row->string('updated_at'),
            ];
        }
        return $items;
    }

    /** @return array{items:list<array<string,string>>,total:int,page:int,pageSize:int} */
    public function audits(int $page): array
    {
        $total = (new Row($this->database->row('SELECT COUNT(*) AS total FROM audit_logs') ?? []))->integer('total');
        $items = [];
        foreach ($this->database->rows(
            'SELECT id,actor_admin_id,action,resource_type,resource_id,created_at FROM audit_logs ORDER BY id DESC LIMIT :limit OFFSET :offset',
            ['limit' => 20, 'offset' => ($page - 1) * 20],
        ) as $values) {
            $row = new Row($values);
            $items[] = [
                'id' => (string) $row->integer('id'),
                'actorId' => $row->string('actor_admin_id'),
                'action' => $row->string('action'),
                'resourceType' => $row->string('resource_type'),
                'resourceId' => $row->string('resource_id'),
                'createdAt' => $row->string('created_at'),
            ];
        }
        return ['items' => $items, 'total' => $total, 'page' => $page, 'pageSize' => 20];
    }
}
