<?php

declare(strict_types=1);

namespace Yinghua\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yinghua\DTO\FilmInput;
use Yinghua\DTO\ScreeningInput;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Policies\ScreeningStartPolicy;

class FilmInputTest extends TestCase
{
    public function testOnlyPrototypeTitleIsRequiredForADraft(): void
    {
        $input = FilmInput::fromArray(['title' => '電影'], true);
        self::assertSame('draft', $input->fields['status']);
        self::assertSame([], $input->screenings);
        self::assertNull($input->fields['poster_media_id']);
        self::assertSame('', $input->fields['synopsis']);
    }

    public function testOmittedChildrenAndPosterMetadataArePreservedOnUpdate(): void
    {
        $input = FilmInput::fromArray(['title' => '電影','version' => 1], false);
        self::assertNull($input->screenings);
        self::assertArrayNotHasKey('poster_media_id', $input->fields);
        self::assertArrayNotHasKey('publish_at', $input->fields);
        self::assertSame([], FilmInput::fromArray(['title' => '電影','version' => 1,'screenings' => []], false)->screenings);
    }

    public function testInvalidCalendarDateIsNotNormalized(): void
    {
        $this->expectException(ApplicationError::class);
        ScreeningInput::fromArray(['startsAt' => '2026-02-30T14:00:00+08:00']);
    }

    public function testPublicationDateCannotOverflowMariaDbAfterTimezoneConversion(): void
    {
        $this->expectException(ApplicationError::class);
        FilmInput::fromArray(['title' => '電影','publishAt' => '9999-12-31T23:00:00-12:00'], true);
    }

    public function testDifferentOffsetsCannotBypassDuplicateStarts(): void
    {
        $a = ScreeningInput::fromArray(['startsAt' => '2026-10-19T14:00:00+08:00']);
        $b = ScreeningInput::fromArray(['startsAt' => '2026-10-19T06:00:00+00:00']);
        $this->expectException(ApplicationError::class);
        (new ScreeningStartPolicy())->assertDistinct([$a, $b]);
    }

    public function testDifferentStartsDoNotRequireAnEndOrDuration(): void
    {
        $a = ScreeningInput::fromArray(['startsAt' => '2026-10-19T14:00:00+08:00']);
        $b = ScreeningInput::fromArray(['startsAt' => '2026-10-19T14:30:00+08:00']);
        (new ScreeningStartPolicy())->assertDistinct([$a, $b]);
        self::assertSame('2026-10-19 06:30:00', $b->startsAt->format('Y-m-d H:i:s'));
    }

    public function testEndAndVenueAreNotSilentlyAccepted(): void
    {
        $this->expectException(ApplicationError::class);
        ScreeningInput::fromArray(['startsAt' => '2026-10-19T14:00:00+08:00','endsAt' => '2026-10-19T16:00:00+08:00']);
    }

    public function testUnsafePosterUrlIsRejected(): void
    {
        $this->expectException(ApplicationError::class);
        FilmInput::fromArray(['title' => '電影','posterUrl' => 'javascript:alert(1)'], true);
    }

    public function testNestedFieldErrorsPointToTheCorrectScreening(): void
    {
        try {
            FilmInput::fromArray(['title' => '電影','screenings' => [['startsAt' => 'not-a-date']]], true);
            self::fail('Invalid screening accepted.');
        } catch (ApplicationError $error) {
            self::assertArrayHasKey('screenings.0.startsAt', $error->fieldErrors);
        }
    }
}
