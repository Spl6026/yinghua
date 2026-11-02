<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\NewsType;

/** 消息表單輸入；只有標題與分類必填，活動期間可以留空。 */
readonly class NewsInput
{
    /** @param array<string,string|int|null> $fields */
    public function __construct(public array $fields, public ?int $version) {}

    /** @param array<string,mixed> $values */
    public static function fromArray(array $values, bool $creating, int $now): self
    {
        $input = new Input($values);
        $input->only([...ContentFields::keys(),'type','summary','body','eventStartAt','eventEndAt','eventLabel','eventLocation']);
        $type = NewsType::tryFrom($input->text('type')) ?? throw ApplicationError::invalid('type', '分類只允許 event／city。');
        $fields = [...ContentFields::shared($values, $creating, $now), 'type' => $type->value,
            'summary' => $input->optionalText('summary', 500), 'body' => $input->optionalText('body', 20000),
            'event_label' => $input->optionalText('eventLabel'), 'event_location' => $input->optionalText('eventLocation'),
            'event_starts_at' => ContentFields::date($input->optionalText('eventStartAt', 30), 'eventStartAt', true),
            'event_ends_at' => ContentFields::date($input->optionalText('eventEndAt', 30), 'eventEndAt', true)];
        if ($fields['event_starts_at'] !== null && $fields['event_ends_at'] !== null
            && $fields['event_ends_at'] < $fields['event_starts_at']) {
            throw ApplicationError::invalid('eventEndAt', '結束時間不可早於開始時間。');
        }
        return new self($fields, $creating || !array_key_exists('version', $values) ? null : $input->positiveInt('version'));
    }
}
