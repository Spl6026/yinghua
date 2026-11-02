<?php

declare(strict_types=1);

namespace Yinghua\Policies;

use Yinghua\DTO\Attraction;
use Yinghua\DTO\AttractionInput;
use Yinghua\Exceptions\ApplicationError;

/** 景點的名稱、地址或座標改了之後，管理員必須重新確認 Google 地點關聯，或解除關聯。 */
class GooglePlaceLinkPolicy
{
    public function assertConfirmed(?Attraction $before, AttractionInput $input): void
    {
        $id = $input->fields['google_place_id'];
        if (!is_string($id) || $id === '' || $input->googleLinkConfirmed) {
            return;
        }
        $changed = $before === null || ($before->values['googlePlaceId'] ?? null) !== $id;
        foreach (['name','address'] as $key) {
            $changed = $changed || ($before?->values[$key] ?? null) !== $input->fields[$key];
        }
        foreach (['latitude','longitude'] as $key) {
            $changed = $changed || $this->coordinate($before?->values[$key] ?? null) !== $this->coordinate($input->fields[$key]);
        }
        if ($changed) {
            throw ApplicationError::invalid('googlePlaceId', '地點資料已變更，請重新確認 Google 地點關聯，或解除關聯後儲存。');
        }
    }

    private function coordinate(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }
}
