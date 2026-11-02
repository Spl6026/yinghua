<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\DTO\OfferQuery;
use Yinghua\DTO\PublicCollection;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Exceptions\ErrorCode;

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
                && ($query->q === ''
                    || mb_stripos(implode(' ', array_filter([$item['title'] ?? '',
                        $item['discount'] ?? '',
                        $item['description'] ?? '',
                        $merchant['name'] ?? ''], is_string(...))), $query->q) !== false);
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
        throw new ApplicationError(ErrorCode::NotFound, '找不到此公開優惠。');
    }
}
