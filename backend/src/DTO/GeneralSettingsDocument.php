<?php

declare(strict_types=1);

namespace Yinghua\DTO;

readonly class GeneralSettingsDocument
{
    public function __construct(public int $version = 0, public GeneralSettings $values = new GeneralSettings(), public ?string $updatedAt = null) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return ['version' => $this->version, 'values' => $this->values->toArray(), 'updatedAt' => $this->updatedAt];
    }
}
