<?php

declare(strict_types=1);

namespace Yinghua\Repositories;

use Yinghua\DTO\SeoOverride;
use Yinghua\Models\SeoResource;
use Yinghua\Support\Secrets;

/** 一份內容的所有 SEO 覆寫設定一次讀取或寫入，並參與外層交易。 */
class SeoOverrideRepository
{
    public function __construct(private readonly Database $database) {}

    /** @return list<SeoOverride> */
    public function all(): array
    {
        return array_map(
            static function (array $values): SeoOverride {
                $row = new Row($values);
                return new SeoOverride(
                    SeoResource::from($row->string('resource_type')),
                    $row->nullableString('resource_id') ?? $row->string('path_pattern'),
                    $row->nullableString('seo_title'),
                    $row->nullableString('seo_description'),
                    $row->nullableString('seo_keywords'),
                    $row->nullableString('canonical_path'),
                    $row->nullableString('robots'),
                    $row->nullableString('og_image_url'),
                );
            },
            $this->database->rows(
                'SELECT resource_type,resource_id,path_pattern,seo_title,seo_description,seo_keywords,canonical_path,robots,og_image_url FROM content_seo ORDER BY resource_type,resource_id,path_pattern',
            ),
        );
    }

    /** @param list<SeoOverride> $overrides */
    public function replace(array $overrides, string $actor): void
    {
        $this->database->execute('DELETE FROM content_seo');
        foreach ($overrides as $override) {
            $this->database->execute(
                'INSERT INTO content_seo(id,resource_type,resource_id,path_pattern,seo_title,seo_description,seo_keywords,canonical_path,robots,og_image_url,updated_by) VALUES(:id,:type,:resource,:path,:title,:description,:keywords,:canonical,:robots,:image,:actor)',
                [
                    'id' => Secrets::uuid(),
                    'type' => $override->type->value,
                    'resource' => $override->type === SeoResource::Page ? null : $override->target,
                    'path' => $override->type === SeoResource::Page ? $override->target : null,
                    'title' => $override->title,
                    'description' => $override->description,
                    'keywords' => $override->keywords,
                    'canonical' => $override->canonicalPath,
                    'robots' => $override->robots,
                    'image' => $override->ogImageUrl,
                    'actor' => $actor,
                ],
            );
        }
    }
}
