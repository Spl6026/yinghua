<?php

declare(strict_types=1);

namespace Yinghua\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yinghua\DTO\GeneralSettings;
use Yinghua\DTO\GeneralSettingsInput;
use Yinghua\Exceptions\ApplicationError;

class GeneralSettingsTest extends TestCase
{
    public function testPolicyTextChangesInvalidateConsentEvenIfLabelsDoNotChange(): void
    {
        $first = new GeneralSettings(termsVersion: 'v1', termsText: 'original', privacyText: 'privacy');
        $second = new GeneralSettings(termsVersion: 'v1', termsText: 'changed', privacyText: 'privacy');
        $third = new GeneralSettings(termsVersion: 'v1', termsText: 'original', privacyText: 'changed');
        self::assertNotSame($first->consentVersion('fallback'), $second->consentVersion('fallback'));
        self::assertNotSame($first->consentVersion('fallback'), $third->consentVersion('fallback'));
        self::assertSame($first->consentVersion('fallback'), (new GeneralSettings(contactEmail: 'other@example.test', termsVersion: 'v1', termsText: 'original', privacyText: 'privacy'))->consentVersion('fallback'));
        self::assertSame('fallback', (new GeneralSettings())->consentVersion('fallback'));
        self::assertSame(71, strlen($first->consentVersion('fallback')));
    }

    public function testBlankBusinessFieldsRemainValid(): void
    {
        $input = GeneralSettingsInput::fromArray(['version' => 0, 'seoVersion' => 0, 'siteName' => '', 'description' => '', 'values' => []]);
        self::assertSame('', $input->siteName);
        self::assertSame('', $input->values->termsText);
    }

    public function testUnknownSettingsKeyIsRejected(): void
    {
        $this->expectException(ApplicationError::class);
        GeneralSettings::fromArray(['databasePassword' => 'never']);
    }

}
