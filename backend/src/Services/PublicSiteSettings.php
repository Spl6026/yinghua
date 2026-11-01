<?php

declare(strict_types=1);

namespace Yinghua\Services;

use UnexpectedValueException;
use Yinghua\DTO\PublicSiteSettingsDocument;
use Yinghua\DTO\SeoDefaults;
use Yinghua\DTO\SeoSettingsInput;

class PublicSiteSettings
{
    public function __construct(private readonly ContentPublication $publication) {}

    public function get(): PublicSiteSettingsDocument
    {
        $snapshot = $this->publication->read();
        $seo = $snapshot['seo'] ?? null;
        if (!is_array($seo) || !is_int($seo['version'] ?? null) || !is_string($snapshot['generation'] ?? null)) {
            throw new UnexpectedValueException('Public SEO projection missing.');
        }
        $defaults = SeoDefaults::fromArray(SeoSettingsInput::object($seo['defaults'] ?? null, 'defaults'));
        $general = $snapshot['general'] ?? null;
        $document = new \Yinghua\DTO\GeneralSettingsDocument();
        if (is_array($general) && is_int($general['version'] ?? null)) {
            $document = new \Yinghua\DTO\GeneralSettingsDocument($general['version'], \Yinghua\DTO\GeneralSettings::fromArray(SeoSettingsInput::object($general['values'] ?? null, 'values')));
        }
        return new PublicSiteSettingsDocument($seo['version'], $defaults, $snapshot['generation'], $document);
    }
}
