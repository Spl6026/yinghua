<?php

declare(strict_types=1);

namespace Yinghua\Repositories;

use Yinghua\DTO\SeoContentTarget;
use Yinghua\DTO\SeoOverride;
use Yinghua\Models\SeoResource;

/** 只查 SEO 選擇器需要的識別資料，不讀正文；SQL 中的表名／欄名一律來自 enum。 */
class SeoContentRepository
{
    public function __construct(private readonly Database $database) {}

    /** @return array{string,string} */
    private function source(SeoResource $type): array
    {
        return match ($type) {
            SeoResource::Attraction => ['attractions','name'],
            SeoResource::Film => ['films','title'],
            SeoResource::News => ['news_posts','title'],
            SeoResource::Offer => ['offers','title'],
            SeoResource::Page => throw new \LogicException('Pages do not have content records.'),
        };
    }

    public function find(SeoResource $type, string $id): ?SeoContentTarget
    {
        [$table, $title] = $this->source($type);
        $row = $this->database->row(
            "SELECT id,$title AS title,slug,status,publish_at,seo_title,seo_description FROM $table WHERE id=:id AND deleted_at IS NULL",
            ['id' => $id],
        );
        return $row === null ? null : $this->map($type, $row);
    }

    /** @param list<SeoOverride> $overrides
     * @return array<string,SeoContentTarget>
     */
    public function lookup(array $overrides): array
    {
        $result = [];
        foreach (SeoResource::cases() as $type) {
            if ($type === SeoResource::Page) {
                continue;
            }
            $ids = array_values(array_unique(array_map(
                static fn(SeoOverride $item): string => $item->target,
                array_filter($overrides, static fn(SeoOverride $item): bool => $item->type === $type),
            )));
            if ($ids === []) {
                continue;
            }
            [$table, $title] = $this->source($type);
            $params = [];
            foreach ($ids as $index => $id) {
                $params['id' . $index] = $id;
            }
            $in = implode(',', array_map(static fn(string $key): string => ':' . $key, array_keys($params)));
            foreach ($this->database->rows(
                "SELECT id,$title AS title,slug,status,publish_at,seo_title,seo_description FROM $table WHERE id IN ($in) AND deleted_at IS NULL",
                $params,
            ) as $row) {
                $item = $this->map($type, $row);
                $result[$type->value . ':' . $item->id] = $item;
            }
        }
        return $result;
    }

    /** @return list<SeoContentTarget> */
    public function choices(SeoResource $type, bool $includeDraft, string $query): array
    {
        [$table, $title] = $this->source($type);
        $where = $includeDraft ? '' : " AND status='published' AND publish_at<=UTC_TIMESTAMP()";
        $where .= $query === '' ? '' : " AND $title LIKE :query ESCAPE '='";
        $params = $query === '' ? [] : ['query' => '%' . strtr($query, ['=' => '==','%' => '=%','_' => '=_']) . '%'];
        return array_map(
            fn(array $row): SeoContentTarget => $this->map($type, $row),
            $this->database->rows(
                "SELECT id,$title AS title,slug,status,publish_at,seo_title,seo_description FROM $table WHERE deleted_at IS NULL$where ORDER BY updated_at DESC,id LIMIT 51",
                $params,
            ),
        );
    }

    /** @param array<string,mixed> $values */
    private function map(SeoResource $type, array $values): SeoContentTarget
    {
        $row = new Row($values);
        return new SeoContentTarget(
            $type,
            $row->string('id'),
            $row->string('title'),
            $row->string('slug'),
            $row->string('status') === 'published' && $row->string('publish_at') <= gmdate('Y-m-d H:i:s'),
            $row->string('seo_title'),
            $row->string('seo_description'),
        );
    }
}
