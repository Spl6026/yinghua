<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\DTO\PublicCollection;
use Yinghua\Exceptions\ApplicationError;

/** [S H8／H10／H12／H29；E-31] 不即時查DB，避免鄰居與HTML跨版。 */
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
            $url = $topic === 'items' ? (($item['isMuseum'] ?? false) === true ? '/museum.html' : '/places/' . $slug . '.html') : ($item['url'] ?? null);
            $neighbors = is_array($map) && is_string($url) ? ($map[$url] ?? null) : null;
            if (!is_array($neighbors)) {
                throw new \UnexpectedValueException('Neighbor projection unavailable.');
            }
            return ['previous' => $neighbors['previous'] ?? null,'next' => $neighbors['next'] ?? null,'generation' => $snapshot['generation']];
        }
        throw new ApplicationError('NOT_FOUND', '找不到此公開內容。');
    }
}
