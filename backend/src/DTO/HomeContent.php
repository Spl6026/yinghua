<?php

declare(strict_types=1);

namespace Yinghua\DTO;

/** [E-26] 同一公開投影中的首頁欄位；沒有額外首頁資料表或設定管理。 */
readonly class HomeContent
{
    /** @param array<string,mixed>|null $museum
     * @param array<string,mixed>|null $featured
     * @param list<array<string,mixed>> $films
     * @param array<string,string> $period
     * @param list<array<string,mixed>> $news
     */
    public function __construct(
        public ?array $museum,
        public ?array $featured,
        public array $films,
        public int $filmTotal,
        public array $period,
        public array $news,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return ['museum' => $this->museum,'featured' => $this->featured,'films' => $this->films,
            'filmTotal' => $this->filmTotal,'period' => $this->period,'news' => $this->news];
    }
}
