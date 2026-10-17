<?php

declare(strict_types=1);

namespace Yinghua\DTO;

/** [E-18] Repository 已驗證的景點快照；明確公開與管理輸出邊界。 */
readonly class Attraction
{
    /** @param array<string,mixed> $values */
    public function __construct(public string $id, public string $slug, public int $version, public string $status, public ?string $deletedAt, public array $values) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return $this->values;
    }

    /** @return array<string,mixed> */
    public function publicArray(): array
    {
        return array_diff_key($this->values, array_flip(['deletedAt','status','googlePlaceId','legacyImage']));
    }
}
