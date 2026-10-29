<?php

declare(strict_types=1);

namespace Yinghua\Policies;

use Yinghua\DTO\{ContentNeighbor, ContentNeighbors, PublicCollection};

/** [S G8／L10／G29；E-31] 只對成功投影的白名單選鄰居；發布與公開API共用結果。 */
final class NeighborSelection
{
    /** @param array<string,mixed> $snapshot
     * @return array<string,array{previous:array{slug:string,url:string,title:string,image:string,alt:string}|null,next:array{slug:string,url:string,title:string,image:string,alt:string}|null}>
     */
    public static function project(array $snapshot): array
    {
        $result = [];
        foreach (['items','films','news','offers'] as $topic) {
            $groups = [];
            foreach (PublicCollection::items($snapshot, $topic) as $item) {
                if ($topic === 'items') {
                    $slug = is_string($item['slug'] ?? null) ? $item['slug'] : '';
                    $item['url'] = ($item['isMuseum'] ?? false) === true ? '/museum.html' : '/places/' . $slug . '.html';
                }
                $key = $topic === 'items' ? 'category' : ($topic === 'news' ? 'type' : '');
                $group = $key === '' ? '' : (is_string($item[$key] ?? null) ? $item[$key] : '');
                $groups[$group][] = $item;
            }
            foreach ($groups as $items) {
                usort($items, static function (array $a, array $b): int {
                    $dateA = is_string($a['publishAt'] ?? null) ? $a['publishAt'] : '';
                    $dateB = is_string($b['publishAt'] ?? null) ? $b['publishAt'] : '';
                    $idA = is_string($a['id'] ?? null) ? $a['id'] : '';
                    $idB = is_string($b['id'] ?? null) ? $b['id'] : '';
                    return strcmp($dateB, $dateA) ?: strcmp($idA, $idB);
                });
                foreach ($items as $index => $item) {
                    $url = $item['url'] ?? null;
                    if (!is_string($url)) {
                        throw new \UnexpectedValueException('Public detail URL required.');
                    }
                    $result[$url] = (new ContentNeighbors(
                        $index > 0 ? ContentNeighbor::fromArray($items[$index - 1]) : null,
                        $index + 1 < count($items) ? ContentNeighbor::fromArray($items[$index + 1]) : null,
                    ))->toArray();
                }
            }
        }
        ksort($result);
        return $result;
    }
}
