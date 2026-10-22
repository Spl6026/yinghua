<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\DTO\OfferQuery;
use Yinghua\DTO\PublicCollection;
use Yinghua\Exceptions\ApplicationError;

class PublicOffers
{
    public function __construct(private readonly ContentPublication $publication) {}

    /** @return array<string,mixed> */
    public function listing(OfferQuery $query): array
    {
        $snapshot = $this->publication->read();
        $items = array_values(array_filter(PublicCollection::items($snapshot, 'offers'), static function (array $item) use ($query): bool {
            $merchant = is_array($item['merchant'] ?? null) ? $item['merchant'] : [];
            return (!$query->active || ($item['active'] ?? false) === true)
                && ($query->district === '' || ($merchant['district'] ?? null) === $query->district)
                && ($query->q === '' || mb_stripos(implode(' ', array_filter([$item['title'] ?? '',$item['discount'] ?? '',$item['description'] ?? '',$merchant['name'] ?? ''], is_string(...))), $query->q) !== false);
        }));
        return PublicCollection::page($items, $query->page, $query->pageSize, $snapshot);
    }

    /** @return array<string,mixed> */
    public function get(string $slug): array
    {
        $snapshot = $this->publication->read();
        foreach (PublicCollection::items($snapshot, 'offers') as $item) {
            if (($item['slug'] ?? null) === $slug) {
                return ['offer' => $item,'generation' => $snapshot['generation']];
            }
        }
        throw new ApplicationError('NOT_FOUND', '找不到此公開優惠。');
    }

    public function resolve(string $original): string
    {
        $snapshot = $this->publication->read();
        $path = parse_url($original, PHP_URL_PATH);
        $aliases = $snapshot['offerAliases'] ?? [];
        $target = is_array($aliases) && is_string($path) ? ($aliases[$path] ?? null) : null;
        if ($path === '/offer-detail.html') {
            $queryString = parse_url($original, PHP_URL_QUERY);
            parse_str(is_string($queryString) ? $queryString : '', $query);
            $id = $query['id'] ?? null;
            if (is_string($id)) {
                $legacy = ['0' => '101','1' => '102','2' => '103'][$id] ?? $id;
                foreach (PublicCollection::items($snapshot, 'offers') as $item) {
                    if (($item['id'] ?? null) === $id || (is_int($item['legacyId'] ?? null) && (string) $item['legacyId'] === $legacy)) {
                        $target = $item['url'] ?? null;
                        break;
                    }
                }
            }
        }
        if (!is_string($target) || $target === $path || !preg_match('~^/offers/[a-z0-9-]+\.html$~D', $target)) {
            throw new ApplicationError('NOT_FOUND', '找不到此優惠網址。');
        }
        return $target;
    }
}
