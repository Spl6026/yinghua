<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use DateTimeImmutable;
use DateTimeZone;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Support\Uuid;

/** 一個放映場次的輸入；開始時間精確到分鐘，不收結束時間或場地。 */
readonly class ScreeningInput
{
    public function __construct(public ?string $id, public DateTimeImmutable $startsAt, public string $ticketUrl) {}

    /** @param array<string,mixed> $values */
    public static function fromArray(array $values): self
    {
        $input = new Input($values);
        $input->only(['id', 'startsAt', 'ticketUrl']);
        $id = ($values['id'] ?? null) === null ? null : $input->text('id', 36, 36);
        if ($id !== null && !Uuid::isValid($id)) {
            throw ApplicationError::invalid('id', '無效的場次識別。');
        }
        $text = $input->text('startsAt', 20, 30);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:sP', $text);
        if ($date === false || $date->format('Y-m-d\TH:i:sP') !== $text || $date->format('s') !== '00') {
            throw ApplicationError::invalid('startsAt', '請提供有效放映日期與開始時間（分鐘精度，含時區）。');
        }
        $utc = $date->setTimezone(new DateTimeZone('UTC'));
        if ($utc->format('Y') < '1000' || $utc->format('Y') > '9999') {
            throw ApplicationError::invalid('startsAt', '日期超出支援範圍。');
        }

        return new self($id, $utc, ContentUrl::validate($input->optionalText('ticketUrl', 2000), 'ticketUrl'));
    }
}
