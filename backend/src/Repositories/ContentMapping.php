<?php

declare(strict_types=1);

namespace Yinghua\Repositories;

/** [E-26] 只共用 DB 型別及公開欄位映射；SQL 與主題規則仍在各 Repository。 */
final class ContentMapping
{
    /** @param array<string,mixed> $values
     * @param list<string> $keys
     * @return array<string,string|int|null>
     */
    public static function fields(array $values, array $keys): array
    {
        $row = new Row($values);
        $result = [];
        foreach ($keys as $key) {
            $value = $values[$key] ?? null;
            $result[$key] = $value === null ? null : $row->string($key);
        }
        return $result;
    }

    public static function iso(?string $value): ?string
    {
        return $value === null ? null : str_replace(' ', 'T', $value) . '+00:00';
    }

    /** @return list<string> */
    public static function sharedKeys(): array
    {
        return ['slug','title','cover_media_id','cover_url','cover_alt','cover_source','cover_license',
            'status','publish_at','seo_title','seo_description','deleted_at'];
    }

    /** @param array<string,mixed> $values
     * @return array<string,mixed>
     */
    public static function common(array $values, string $topic): array
    {
        $row = new Row($values);
        $storage = $values['storage_key'] ?? null;
        $alt = $row->string('cover_alt');
        $image = is_string($storage) ? '/media/' . $storage : $row->string('cover_url');
        return ['id' => $row->string('id'),'slug' => $row->string('slug'),
            'legacyId' => ($values['legacy_id'] ?? null) === null ? null : $row->integer('legacy_id'),
            'title' => $row->string('title'),'publishAt' => self::iso($row->string('publish_at')),
            'image' => $image,'imageAlt' => $alt !== '' ? $alt : (is_string($values['media_alt'] ?? null) ? $values['media_alt'] : ''),
            'url' => '/' . $topic . '/' . $row->string('slug') . '.html',
            'seoTitle' => $row->string('seo_title'),'seoDescription' => $row->string('seo_description')];
    }
}
