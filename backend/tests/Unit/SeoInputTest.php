<?php

declare(strict_types=1);

namespace Yinghua\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yinghua\DTO\SeoDefaults;
use Yinghua\DTO\SeoSettingsInput;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Policies\SeoTargetPolicy;

class SeoInputTest extends TestCase
{
    public function testEmptyOptionalValuesAndClearInheritAreAccepted(): void
    {
        $input = SeoSettingsInput::fromArray(['version' => 0, 'defaults' => (new SeoDefaults(siteName: '', canonicalOrigin: ''))->toArray(),
            'overrides' => [['target' => ['type' => 'page','path' => '/food.html'], 'title' => '', 'keywords' => '']]], new SeoTargetPolicy(['/food.html']));
        self::assertSame('', $input->defaults->siteName);
        self::assertNull($input->overrides[0]->title);
        self::assertSame('', $input->overrides[0]->keywords);
    }

    public function testSuggestedLengthDoesNotBecomeAHardBusinessLimit(): void
    {
        $defaults = (new SeoDefaults())->toArray();
        $defaults['description'] = str_repeat('景點介紹', 40);
        self::assertSame($defaults, SeoDefaults::fromArray($defaults)->toArray());
    }

    public function testUnsafeOriginsTokensTargetsAndPartialDocumentsAreRejected(): void
    {
        $defaults = (new SeoDefaults())->toArray();
        foreach ([['canonicalOrigin' => 'http://example.test'], ['canonicalOrigin' => 'https://u:p@example.test'],
            ['canonicalOrigin' => 'https://example.test/path'], ['googleVerification' => '<meta content="token">'], ['robots' => 'index,anything']] as $change) {
            try {
                SeoDefaults::fromArray([...$defaults, ...$change]);
                self::fail('Unsafe defaults accepted.');
            } catch (ApplicationError $error) {
                self::assertNotEmpty($error->getMessage());
            }
        }
        foreach ([[], ['version' => 0,'defaults' => $defaults,'overrides' => [['target' => ['type' => 'page','path' => '/../../secret']]]],
            ['version' => 0,'defaults' => $defaults,'overrides' => [['target' => ['type' => 'page','path' => '/food.html'],'canonicalPath' => '/index.html']]]] as $values) {
            try {
                SeoSettingsInput::fromArray($values, new SeoTargetPolicy(['/food.html']));
                self::fail('Unsafe target or partial document accepted.');
            } catch (ApplicationError $error) {
                self::assertNotEmpty($error->getMessage());
            }
        }
    }
}
