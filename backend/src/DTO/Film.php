<?php

declare(strict_types=1);

namespace Yinghua\DTO;

/** 電影資料；管理與公開輸出各自使用明確的欄位白名單。 */
readonly class Film
{
    /** @param array<string,mixed> $values
     * @param list<FilmScreening> $screenings
     */
    public function __construct(
        public string $id,
        public string $slug,
        public int $version,
        public string $status,
        public ?string $deletedAt,
        public array $values,
        public array $screenings,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [...$this->values,'screenings' => array_map(static fn(FilmScreening $item): array => $item->toArray(), $this->screenings)];
    }

    /** @return array<string,mixed> */
    public function publicArray(): array
    {
        return array_diff_key($this->toArray(), array_flip(['status','deletedAt','posterMediaId','posterSource','posterLicense']));
    }
}
