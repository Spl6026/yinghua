<?php

declare(strict_types=1);

namespace Yinghua\DTO;

/** 推薦路線的管理端資料；公開頁上的站點清單另外由發布快照產生。 */
readonly class RecommendedRoute implements \Yinghua\Contracts\VersionedContent
{
    /** @param list<array{attractionId:string,sortOrder:int,suggestedTime:?string,note:string,name:string,available:bool}> $stops */
    public function __construct(
        public string $id,
        public string $slug,
        public string $title,
        public string $theme,
        public string $summary,
        public ?int $durationMinutes,
        public int $sortOrder,
        public string $status,
        public int $version,
        public ?string $deletedAt,
        public array $stops,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return ['id' => $this->id,'slug' => $this->slug,'title' => $this->title,'theme' => $this->theme,
            'summary' => $this->summary,'durationMinutes' => $this->durationMinutes,'sortOrder' => $this->sortOrder,
            'status' => $this->status,'version' => $this->version,'stops' => $this->stops];
    }
}
