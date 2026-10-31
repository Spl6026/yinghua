<?php

declare(strict_types=1);

namespace Yinghua\Repositories;

use Yinghua\Contracts\Transactions;
use Yinghua\DTO\{SearchDocument, SearchQuery};

/** [S G18；E-35] 只搜尋成功公開投影，使用綁定參數，不拼接關鍵字。 */
class SearchRepository
{
    public function __construct(private readonly Database $database, private readonly Transactions $transactions) {}

    /** @param list<SearchDocument> $documents */
    public function replace(string $generation, array $documents): void
    {
        $this->transactions->run(function () use ($generation, $documents): void {
            $this->database->execute('DELETE FROM search_documents WHERE generation=:generation', ['generation' => $generation]);
            foreach ($documents as $document) {
                $this->database->execute(
                    'INSERT INTO search_documents (generation,resource_type,resource_id,type_order,title,summary,search_text,slug,url,thumbnail,thumbnail_alt) VALUES (:generation,:type,:id,:order,:title,:summary,:text,:slug,:url,:image,:alt)',
                    ['generation' => $generation,'type' => $document->type->value,'id' => $document->id,'order' => $document->type->order(),'title' => $document->title,'summary' => $document->summary,'text' => $document->text,'slug' => $document->slug,'url' => $document->url,'image' => $document->thumbnail,'alt' => $document->thumbnailAlt],
                );
            }
        });
    }

    public function prune(string $generation): void
    {
        $this->database->execute('DELETE FROM search_documents WHERE generation<>:generation', ['generation' => $generation]);
    }

    /** @return array<string,mixed> */
    public function search(string $generation, SearchQuery $query): array
    {
        $where = 'generation=:generation AND LOCATE(LOWER(:q),LOWER(search_text))>0';
        $params = ['generation' => $generation,'q' => $query->q];
        if ($query->type !== '') {
            $where .= ' AND resource_type=:type';
            $params['type'] = $query->type;
        }
        $total = new Row($this->database->row('SELECT COUNT(*) AS total FROM search_documents WHERE ' . $where, $params) ?? []);
        $items = $this->database->rows('SELECT resource_id AS id,resource_type AS type,title,summary,slug,url,thumbnail,thumbnail_alt AS thumbnailAlt FROM search_documents WHERE ' . $where . ' ORDER BY type_order,CASE WHEN title=:exact THEN 0 ELSE 1 END,resource_id LIMIT :limit OFFSET :offset', [...$params,'exact' => $query->q,'limit' => $query->pageSize,'offset' => ($query->page - 1) * $query->pageSize]);
        return ['items' => $items,'total' => $total->integer('total'),'page' => $query->page,'pageSize' => $query->pageSize,'generation' => $generation];
    }
}
