<?php

declare(strict_types=1);

namespace Yinghua\DTO;

/** 首頁內容，從同一份公開快照挑出來；首頁沒有獨立的資料表或設定。 */
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
