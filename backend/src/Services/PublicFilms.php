<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\DTO\FilmQuery;
use Yinghua\Exceptions\ApplicationError;

/** [S：功能規格 7/21；E-25] API 與 HTML 都讀同一成功發布快照。 */
class PublicFilms
{
    public function __construct(private readonly ContentPublication $publication) {}

    /** @return array<string,mixed> */
    public function listing(FilmQuery $query): array
    {
        $snapshot = $this->publication->read();
        $items = \Yinghua\Policies\WeeklyFilmSelection::select($this->items($snapshot), $query->window);

        return ['items' => array_slice($items, ($query->page - 1) * $query->pageSize, $query->pageSize),
            'total' => count($items),'page' => $query->page,'pageSize' => $query->pageSize,
            'period' => $query->window->toArray(),'generation' => $snapshot['generation']];
    }

    /** @return array<string,mixed> */
    public function get(string $slug): array
    {
        $snapshot = $this->publication->read();
        foreach ($this->items($snapshot) as $item) {
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
                foreach ($this->items($snapshot) as $item) {
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

    /** @param array<string,mixed> $snapshot
     * @return list<array<string,mixed>>
     */
    private function items(array $snapshot): array
    {
        $rows = $snapshot['films'] ?? [];
        if (!is_array($rows)) {
            throw new \RuntimeException('Invalid film projection.');
        }
        $result = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                throw new \RuntimeException('Invalid film projection row.');
            }
            $item = [];
            foreach ($row as $key => $value) {
                if (is_string($key)) {
                    $item[$key] = $value;
                }
            }
            $result[] = $item;
        }

        return $result;
    }
}
