<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\DTO\PublicCollection;
use Yinghua\DTO\FilmQuery;
use Yinghua\Exceptions\ApplicationError;

/** 公開電影查詢；API 與靜態 HTML 讀的是同一份成功發布的快照。 */
class PublicFilms
{
    public function __construct(private readonly ContentPublication $publication) {}

    /** @return array<string,mixed> */
    public function listing(FilmQuery $query): array
    {
        $snapshot = $this->publication->read();
        $items = \Yinghua\Policies\WeeklyFilmSelection::select(PublicCollection::items($snapshot, 'films'), $query->window);

        return ['items' => array_slice($items, ($query->page - 1) * $query->pageSize, $query->pageSize),
            'total' => count($items),'page' => $query->page,'pageSize' => $query->pageSize,
            'period' => $query->window->toArray(),'generation' => $snapshot['generation']];
    }

    /** @return array<string,mixed> */
    public function get(string $slug): array
    {
        $snapshot = $this->publication->read();
        foreach (PublicCollection::items($snapshot, 'films') as $item) {
            if (($item['slug'] ?? null) === $slug) {
                return ['film' => $item,'generation' => $snapshot['generation']];
            }
        }
        throw new ApplicationError('NOT_FOUND', '找不到此公開電影。');
    }

    public function resolve(string $original): string
    {
        $snapshot = $this->publication->read();
        $path = parse_url($original, PHP_URL_PATH);
        $aliases = $snapshot['filmAliases'] ?? [];
        $target = is_string($path) && is_array($aliases) ? ($aliases[$path] ?? null) : null;
        if ($path === '/film-detail.html') {
            $queryString = parse_url($original, PHP_URL_QUERY);
            parse_str(is_string($queryString) ? $queryString : '', $query);
            $id = $query['id'] ?? null;
            if (is_string($id)) {
                foreach (PublicCollection::items($snapshot, 'films') as $item) {
                    $legacy = $item['legacyId'] ?? null;
                    if (($item['id'] ?? null) === $id || (is_int($legacy) && (string) $legacy === $id)) {
                        $target = $item['url'] ?? null;
                        break;
                    }
                }
            }
        }
        if (!is_string($target) || $target === $path || !preg_match('~^/films/[a-z0-9-]+\.html$~D', $target)) {
            throw new ApplicationError('NOT_FOUND', '找不到此電影網址。');
        }

        return $target;
    }
}
