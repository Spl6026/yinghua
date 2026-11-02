<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\DTO\NewsQuery;
use Yinghua\DTO\PublicCollection;
use Yinghua\Exceptions\ApplicationError;

class PublicNews
{
    public function __construct(private readonly ContentPublication $publication) {}

    /** @return array<string,mixed> */
    public function listing(NewsQuery $query): array
    {
        $snapshot = $this->publication->read();
        $items = array_values(array_filter(PublicCollection::items($snapshot, 'news'), static fn(array $item): bool
            => ($query->type === '' || ($item['type'] ?? null) === $query->type)
            && ($query->q === ''
                || mb_stripos(implode(' ', array_filter([$item['title'] ?? '',
                    $item['summary'] ?? ''], is_string(...))), $query->q) !== false)));
        return [...PublicCollection::page($items, $query->page, $query->pageSize, $snapshot),'featured' => $items[0] ?? null];
    }

    /** @return array<string,mixed> */
    public function get(string $slug): array
    {
        $snapshot = $this->publication->read();
        foreach (PublicCollection::items($snapshot, 'news') as $item) {
            if (($item['slug'] ?? null) === $slug) {
                return ['article' => $item,'generation' => $snapshot['generation']];
            }
        }
        throw new ApplicationError('NOT_FOUND', '找不到此公開消息。');
    }

    public function resolve(string $original): string
    {
        $snapshot = $this->publication->read();
        $path = parse_url($original, PHP_URL_PATH);
        // 這是唯一跨主題的舊網址：它原本其實是優惠頁，所以轉到對應的優惠，而不是硬當成城市消息文章。
        if ($path === '/news/coffee-walk.html') {
            $offerAliases = $snapshot['offerAliases'] ?? [];
            $offer = is_array($offerAliases) ? ($offerAliases[$path] ?? null) : null;
            if (is_string($offer) && preg_match('~^/offers/[a-z0-9-]+\.html$~D', $offer)) {
                return $offer;
            }
        }
        $aliases = $snapshot['newsAliases'] ?? [];
        $target = is_array($aliases) && is_string($path) ? ($aliases[$path] ?? null) : null;
        if ($path === '/news-detail.html') {
            $queryString = parse_url($original, PHP_URL_QUERY);
            parse_str(is_string($queryString) ? $queryString : '', $query);
            foreach (PublicCollection::items($snapshot, 'news') as $item) {
                $id = $query['id'] ?? null;
                if (is_string($id)
                    && (($item['id'] ?? null) === $id || (is_int($item['legacyId'] ?? null) && (string) $item['legacyId'] === $id))) {
                    $target = $item['url'] ?? null;
                    break;
                }
            }
        }
        if (!is_string($target) || $target === $path || !preg_match('~^/news/[a-z0-9-]+\.html$~D', $target)) {
            throw new ApplicationError('NOT_FOUND', '找不到此消息網址。');
        }
        return $target;
    }
}
