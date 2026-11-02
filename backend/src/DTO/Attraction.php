<?php

declare(strict_types=1);

namespace Yinghua\DTO;

/** Repository 讀出並驗證過的景點資料，公開與管理輸出各自有明確的欄位範圍。 */
readonly class Attraction
{
    /** @param array<string,mixed> $values */
    public function __construct(
        public string $id,
        public string $slug,
        public int $version,
        public string $status,
        public ?string $deletedAt,
        public array $values,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return $this->values;
    }

    /** 範例試填只帶基本資料，不複製識別、發布狀態、圖片授權或 Google 關聯。
     * @return array<string,mixed>
     */
    public function sampleArray(): array
    {
        return array_intersect_key($this->values, array_flip(['id','name','category','district','typeLabel',
            'summary','description','hours','openingHours','address','phone','latitude','longitude','transport',
            'websiteUrl','mapUrl','recommendationLabel']));
    }

    /** @return array<string,mixed> */
    public function publicArray(): array
    {
        $summary = is_string($this->values['summary'] ?? null) ? $this->values['summary'] : '';
        $description = is_string($this->values['description'] ?? null) ? $this->values['description'] : '';
        $content = \Yinghua\Policies\AttractionContentPolicy::select($summary, $description, !empty($this->values['googlePlaceId']));
        return [...array_diff_key($this->values, array_flip(['deletedAt','status','googlePlaceId','legacyImage'])),
            'summary' => $content->summary, 'description' => $content->description,
            'authoredContent' => ['summary' => $summary, 'description' => $description], 'contentSource' => $content->sources()];
    }
}
