<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\DTO\PublicCollection;
use Yinghua\Exceptions\ApplicationError;

/** 詳情頁的上一篇／下一篇；從發布快照計算而不即時查資料庫，避免與已產生的 HTML 版本不一致。 */
class PublicNeighbors
{
    public function __construct(private readonly ContentPublication $publication) {}

    /** @return array<string,mixed> */
    public function get(string $topic, string $slug): array
    {
        if (!in_array($topic, ['items','films','news','offers'], true)) {
            throw new \LogicException('Unsupported public topic.');
        }
        $snapshot = $this->publication->read();
        foreach (PublicCollection::items($snapshot, $topic) as $item) {
            if (($item['slug'] ?? null) !== $slug) {
                continue;
            }
            $map = $snapshot['detailNeighbors'] ?? null;
            $url = $topic === 'items'
                ? (($item['isMuseum'] ?? false) === true ? '/museum.html' : '/places/' . $slug . '.html')
                : ($item['url'] ?? null);
            $neighbors = is_array($map) && is_string($url) ? ($map[$url] ?? null) : null;
            if (!is_array($neighbors)) {
                throw new \UnexpectedValueException('Neighbor projection unavailable.');
            }
            return ['previous' => $neighbors['previous'] ?? null,
                'next' => $neighbors['next'] ?? null,
                'generation' => $snapshot['generation']];
        }
        throw new ApplicationError('NOT_FOUND', '找不到此公開內容。');
    }
}
