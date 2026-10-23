<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use Yinghua\Models\SeoResource;

readonly class SeoOverride
{
    public function __construct(
        public SeoResource $type,
        public string $target,
        public ?string $title = null,
        public ?string $description = null,
        public ?string $keywords = null,
        public ?string $canonicalPath = null,
        public ?string $robots = null,
        public ?string $ogImageUrl = null,
    ) {}

    public function key(): string
    {
        return $this->type->value . ':' . $this->target;
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return ['target' => ['type' => $this->type->value, $this->type === SeoResource::Page ? 'path' : 'id' => $this->target],
            'title' => $this->title,'description' => $this->description,'keywords' => $this->keywords,
            'canonicalPath' => $this->canonicalPath,'robots' => $this->robots,'ogImageUrl' => $this->ogImageUrl];
    }
}
