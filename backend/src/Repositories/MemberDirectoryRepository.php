<?php

declare(strict_types=1);

namespace Yinghua\Repositories;

use Yinghua\DTO\MemberSummary;

/** 會員名單查詢：每頁固定 20 筆、排序穩定，搜尋字串中的 LIKE 萬用字元當作一般文字處理。 */
class MemberDirectoryRepository
{
    public function __construct(private readonly Database $database) {}

    /** @return array{items:list<array<string,string>>,total:int,page:int,pageSize:int} */
    public function listing(string $query, int $page): array
    {
        $where = 'deleted_at IS NULL';
        $parameters = [];
        if ($query !== '') {
            $where .= " AND (display_name LIKE :name ESCAPE '!' OR email LIKE :email ESCAPE '!')";
            $pattern = Database::contains($query);
            $parameters = ['name' => $pattern, 'email' => $pattern];
        }
        $total = (new Row($this->database->row(
            'SELECT COUNT(*) AS total FROM members WHERE ' . $where,
            $parameters,
        ) ?? []))->integer('total');
        $rows = $this->database->rows(
            'SELECT id,display_name,email,status,created_at FROM members WHERE ' . $where . ' ORDER BY created_at DESC,id DESC LIMIT :limit OFFSET :offset',
            [...$parameters, 'limit' => 20, 'offset' => ($page - 1) * 20],
        );
        $items = [];
        foreach ($rows as $values) {
            $row = new Row($values);
            $items[] = (new MemberSummary(
                $row->string('id'),
                $row->string('display_name'),
                $row->string('email'),
                $row->string('status'),
                $row->string('created_at'),
            ))->toArray();
        }
        return ['items' => $items, 'total' => $total, 'page' => $page, 'pageSize' => 20];
    }
}
