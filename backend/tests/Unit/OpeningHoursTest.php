<?php

declare(strict_types=1);

namespace Yinghua\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Yinghua\Adapters\GoogleOpeningHours;
use Yinghua\DTO\AttractionInput;
use Yinghua\DTO\OpeningHours;
use Yinghua\Exceptions\ApplicationError;

class OpeningHoursTest extends TestCase
{
    /** @return array<string,mixed> */
    private function week(): array
    {
        return ['timezone' => 'Asia/Taipei','note' => '', 'days' => array_map(static fn(int $day): array => ['day' => $day,'status' => 'unknown','periods' => []], range(0, 6))];
    }

    public function testLunchBreakAndCrossDayRoundTrip(): void
    {
        $week = $this->week();
        $week['days'][1] = ['day' => 1,'status' => 'periods','periods' => [['opens' => '14:00','closes' => '18:00','nextDay' => false],['opens' => '09:00','closes' => '12:00','nextDay' => false]]];
        $week['days'][6] = ['day' => 6,'status' => 'periods','periods' => [['opens' => '22:00','closes' => '02:00','nextDay' => true]]];
        $hours = OpeningHours::fromValue($week);
        self::assertSame('09:00', $hours->toArray()['days'][1]['periods'][0]['opens']);
        self::assertSame('22:00–次日 02:00', $hours->days[6]->description());
        self::assertSame('每週時段不同，詳見介紹', $hours->summary());
        self::assertSame($hours->toArray(), OpeningHours::fromValue($hours->toArray())->toArray());
    }

    public function testUnknownClosedAndAllDayAreDistinct(): void
    {
        $unknown = OpeningHours::fromValue($this->week());
        self::assertSame('依店家公告', $unknown->summary());
        foreach (['closed' => '每日 公休','all_day' => '每日 全天開放'] as $status => $summary) {
            $week = $this->week();
            foreach ($week['days'] as &$day) {
                $day['status'] = $status;
            }
            unset($day);
            self::assertSame($summary, OpeningHours::fromValue($week)->summary());
        }
    }

    public function testInvalidShapesTimesAndOverlapsAreRejected(): void
    {
        $valid = $this->week();
        $invalid = [null,[],[...$valid,'timezone' => 'UTC'],[...$valid,'note' => str_repeat('字', 501)],[...$valid,'extra' => true]];
        $missing = $valid;
        array_pop($missing['days']);
        $invalid[] = $missing;
        $duplicate = $valid;
        $duplicate['days'][6]['day'] = 0;
        $invalid[] = $duplicate;
        foreach ([['opens' => '24:00','closes' => '02:00','nextDay' => true],['opens' => '22:00','closes' => '02:00','nextDay' => false],['opens' => '09:00','closes' => '10:00','nextDay' => true],['opens' => '09:00','closes' => '09:00','nextDay' => false],['opens' => '09:00','closes' => '10:00','nextDay' => 0]] as $period) {
            $week = $valid;
            $week['days'][1] = ['day' => 1,'status' => 'periods','periods' => [$period]];
            $invalid[] = $week;
        }
        $overlap = $valid;
        $overlap['days'][6] = ['day' => 6,'status' => 'periods','periods' => [['opens' => '22:00','closes' => '02:00','nextDay' => true]]];
        $overlap['days'][0] = ['day' => 0,'status' => 'periods','periods' => [['opens' => '01:00','closes' => '03:00','nextDay' => false]]];
        $invalid[] = $overlap;
        $hidden = $valid;
        $hidden['days'][0]['periods'] = [['opens' => '09:00','closes' => '10:00','nextDay' => false]];
        $invalid[] = $hidden;
        $empty = $valid;
        $empty['days'][0]['status'] = 'periods';
        $invalid[] = $empty;
        foreach ($invalid as $value) {
            try {
                OpeningHours::fromValue($value);
                self::fail('Invalid schedule accepted.');
            } catch (ApplicationError $error) {
                self::assertSame('openingHours', array_key_first($error->fieldErrors));
            }
        }
    }

    public function testProviderOrderAndLocalizationDoNotDetermineWeekdays(): void
    {
        $hours = (new GoogleOpeningHours())->convert(['weekdayDescriptions' => ['文字不能決定星期'], 'periods' => [
            ['open' => ['day' => 5,'hour' => 22], 'close' => ['day' => 6,'hour' => 2]],
            ['open' => ['day' => 1,'hour' => 9], 'close' => ['day' => 1,'hour' => 12]],
            ['open' => ['day' => 1,'hour' => 14], 'close' => ['day' => 1,'hour' => 18]],
        ]]);
        self::assertSame('09:00–12:00、14:00–18:00', $hours->days[1]->description());
        self::assertSame('22:00–次日 02:00', $hours->days[5]->description());
        self::assertSame('公休', $hours->days[0]->description());
        self::assertSame('', $hours->note);
    }

    public function testProviderAlwaysOpenAndMultiDayPeriods(): void
    {
        $adapter = new GoogleOpeningHours();
        self::assertSame('每日 全天開放', $adapter->convert(['periods' => [['open' => ['day' => 0,'hour' => 0,'minute' => 0]]]])->summary());
        $hours = $adapter->convert(['periods' => [['open' => ['day' => 1,'hour' => 10], 'close' => ['day' => 4,'hour' => 5]]]]);
        self::assertSame('全天開放', $hours->days[2]->description());
        self::assertSame('全天開放', $hours->days[3]->description());
        self::assertSame('00:00–05:00', $hours->days[4]->description());
    }

    public function testUnknownProviderDataDoesNotBecomeClosedOrLoseReferenceText(): void
    {
        $adapter = new GoogleOpeningHours();
        foreach ([null, ['weekdayDescriptions' => ['週一請洽店家']], ['periods' => [['open' => ['day' => 4]]],'weekdayDescriptions' => ['待核對']]] as $value) {
            $hours = $adapter->convert($value);
            self::assertSame('依店家公告', $hours->days[0]->description());
        }
        self::assertSame('週一請洽店家', $adapter->convert(['weekdayDescriptions' => ['週一請洽店家']])->note);
        self::assertSame('每日 公休', $adapter->convert(['periods' => []])->summary());
    }

    public function testAttractionWriteUsesValidatedScheduleSummary(): void
    {
        $input = AttractionInput::fromArray(['slug' => 'sample','name' => '景點','category' => '景點','district' => '北區','summary' => '摘要','description' => '正文','address' => '地址','status' => 'draft','hours' => '舊摘要','openingHours' => $this->week()], true);
        self::assertSame('依店家公告', $input->fields['hours']);
        self::assertSame($this->week(), json_decode($input->fields['opening_hours'], true, flags: JSON_THROW_ON_ERROR));
    }
}
