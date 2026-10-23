<?php

declare(strict_types=1);

namespace Yinghua\DTO;

readonly class SeoSettingsDocument
{
    /** @param list<SeoOverride> $overrides */
    public function __construct(public int $version, public SeoDefaults $defaults, public array $overrides = [], public ?string $updatedAt = null) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return ['version' => $this->version,'defaults' => $this->defaults->toArray(),
            'overrides' => array_map(static fn(SeoOverride $override): array => $override->toArray(), $this->overrides), 'updatedAt' => $this->updatedAt];
    }
}
