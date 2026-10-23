<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use Yinghua\Models\SeoResource;

readonly class SeoContentTarget
{
    public function __construct(
        public SeoResource $type,
        public string $id,
        public string $title,
        public string $slug,
        public bool $published,
        public string $seoTitle,
        public string $seoDescription,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return ['type' => $this->type->value,'id' => $this->id,'title' => $this->title,'slug' => $this->slug,
            'published' => $this->published,'seoTitle' => $this->seoTitle,'seoDescription' => $this->seoDescription];
    }
}
