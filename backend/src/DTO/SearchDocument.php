<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use Yinghua\Models\PublicContentType;
use Yinghua\Models\SearchType;
use Yinghua\Support\Uuid;

/** 全站搜尋的索引文件，只取公開白名單欄位，不複製整份資料或帳號資訊。 */
readonly class SearchDocument
{
    public function __construct(
        public SearchType $type,
        public string $id,
        public string $title,
        public string $summary,
        public string $text,
        public string $slug,
        public string $url,
        public string $thumbnail,
        public string $thumbnailAlt,
    ) {}

    /** @param array<string,mixed> $snapshot
     * @return list<self>
     */
    public static function project(array $snapshot): array
    {
        $documents = [];
        foreach (PublicContentType::cases() as $contentType) {
            foreach (PublicCollection::items($snapshot, $contentType->collectionKey()) as $item) {
                // 過期或尚未開始的優惠不進搜尋，避免搜到無法使用的優惠。
                if ($contentType === PublicContentType::Offer && ($item['active'] ?? false) !== true) {
                    continue;
                }
                $get = static fn(string $key): string => is_string($item[$key] ?? null) ? $item[$key] : '';
                $slug = $get('slug');
                $url = $get('url') ?: $contentType->canonicalUrl($item);
                $title = $get($contentType->titleKey());
                $summary = self::plain($get('summary') ?: ($get('synopsis') ?: ($get('description') ?: $get('discount'))));
                $merchant = $item['merchant'] ?? [];
                $merchantName = is_array($merchant) && is_string($merchant['name'] ?? null) ? $merchant['name'] : '';
                $text = implode(' ', [$title,$summary,self::plain($get('body')),$get('genre'),$get('category'),$get('district'),
                    $get('address'),$merchantName,$get('discount'),$get('terms')]);
                if ($url !== $contentType->canonicalUrl($item)
                    || !preg_match('~^/(?:places|films|news|offers)/[a-z0-9-]+\.html$|^/museum\.html$~D', $url)
                    || !Uuid::isValid($get('id'))) {
                    throw new \UnexpectedValueException('Invalid public search document.');
                }
                $documents[] = new self(
                    $contentType->searchType($item),
                    $get('id'),
                    $title,
                    mb_substr($summary, 0, 4000),
                    $text,
                    $slug,
                    $url,
                    $get('image'),
                    $get('imageAlt') ?: $title,
                );
            }
        }
        return $documents;
    }

    private static function plain(string $text): string
    {
        $text = preg_replace('~</(?:p|h[1-6]|li|div)>|<br\s*/?>~iu', ' ', $text) ?? '';
        return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8')) ?? '');
    }
}
