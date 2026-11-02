<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\ContentStatus;

/** 推薦路線表單輸入；只有名稱與至少一個站點必填，時間、主題、說明都是選填。 */
readonly class RecommendedRouteInput
{
    /** @param list<RouteStopInput> $stops */
    public function __construct(
        public string $title,
        public string $theme,
        public string $summary,
        public ?int $durationMinutes,
        public int $sortOrder,
        public ContentStatus $status,
        public array $stops,
        public int $version,
    ) {}

    /** @param array<string,mixed> $values */
    public static function from(array $values, bool $creating): self
    {
        $input = new Input($values);
        $input->only(['title','theme','summary','durationMinutes','sortOrder','status','stops','version']);
        $duration = $values['durationMinutes'] ?? null;
        if ($duration !== null && (!is_int($duration) || $duration < 1 || $duration > 4294967295)) {
            throw ApplicationError::invalid('durationMinutes', '請填入有效的正整數分鐘，或留白。');
        }
        $order = $values['sortOrder'] ?? 0;
        if (!is_int($order) || $order < 0 || $order > 4294967295) {
            throw ApplicationError::invalid('sortOrder', '排序須為有效的非負整數。');
        }
        $status = ContentStatus::tryFrom($input->optionalText('status') ?: 'draft');
        if ($status === null) {
            throw ApplicationError::invalid('status', '請選擇有效發布狀態。');
        }
        $stops = [];
        $ids = [];
        foreach ($input->objects('stops') as $position => $value) {
            $stop = RouteStopInput::from($value);
            if (in_array($stop->attractionId, $ids, true)) {
                throw ApplicationError::invalid('stops', '同一景點不能重複加入。');
            }
            $ids[] = $stop->attractionId;
            $stops[] = $stop;
        }
        if ($stops === []) {
            throw ApplicationError::invalid('stops', '路線至少需要一個有效景點。');
        }
        return new self(
            $input->text('title', 1, 160),
            $input->optionalText('theme'),
            $input->optionalText('summary', 20000),
            $duration,
            $order,
            $status,
            $stops,
            $creating ? 0 : $input->positiveInt('version'),
        );
    }
}
