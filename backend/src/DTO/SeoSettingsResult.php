<?php

declare(strict_types=1);

namespace Yinghua\DTO;

readonly class SeoSettingsResult
{
    /** @param list<array<string,mixed>> $pages
     * @param list<SeoContentTarget> $targets
     */
    public function __construct(
        public SeoSettingsDocument $document,
        public bool $publicationReady,
        public array $pages = [],
        public array $targets = [],
        public bool $targetsTruncated = false,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [...$this->document->toArray(), 'publicationReady' => $this->publicationReady,'pages' => $this->pages,
            'targets' => array_map(static fn(SeoContentTarget $target): array => $target->toArray(), $this->targets),
            'targetsTruncated' => $this->targetsTruncated];
    }
}
