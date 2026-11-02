<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\DTO\NewsQuery;
use Yinghua\DTO\PublicCollection;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Exceptions\ErrorCode;

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
        throw new ApplicationError(ErrorCode::NotFound, '找不到此公開消息。');
    }
}
