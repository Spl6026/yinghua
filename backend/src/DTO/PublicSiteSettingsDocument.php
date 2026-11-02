<?php

declare(strict_types=1);

namespace Yinghua\DTO;

readonly class PublicSiteSettingsDocument
{
    public function __construct(
        public int $version,
        public SeoDefaults $defaults,
        public string $generation,
        public GeneralSettingsDocument $general = new GeneralSettingsDocument(),
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        $defaults = $this->defaults->toArray();
        unset($defaults['siteName'], $defaults['googleVerification'], $defaults['bingVerification']);
        return ['siteName' => $this->defaults->siteName, 'seoDefaults' => $defaults,
            'verification' => ['google' => $this->defaults->googleVerification,'bing' => $this->defaults->bingVerification],
            'general' => $this->general->values->toArray(),
            'version' => $this->version,'generation' => $this->generation];
    }
}
