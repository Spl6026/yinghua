<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use Yinghua\Exceptions\ApplicationError;

/** 某一天開始的一段營業時間（台北時間），最長 24 小時，可跨到隔天。 */
readonly class OpeningPeriod
{
    private function __construct(public string $opens, public string $closes, public bool $nextDay) {}

    public static function fromValue(mixed $value): self
    {
        if (!is_array($value) || array_diff(array_keys($value), ['opens','closes','nextDay']) !== []) {
            throw ApplicationError::invalid('openingHours', '營業時段格式無效。');
        }
        $opens = $value['opens'] ?? null;
        $closes = $value['closes'] ?? null;
        $nextDay = $value['nextDay'] ?? null;
        foreach ([$opens,$closes] as $time) {
            if (!is_string($time) || !preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/D', $time)) {
                throw ApplicationError::invalid('openingHours', '請以 HH:mm 填寫有效的開始及結束時間。');
            }
        }
        if (!is_string($opens) || !is_string($closes) || !is_bool($nextDay)) {
            throw ApplicationError::invalid('openingHours', '請明確指定結束時間是否在次日。');
        }
        $period = new self($opens, $closes, $nextDay);
        if ($period->end() <= $period->start() || $period->end() - $period->start() > 1440) {
            throw ApplicationError::invalid('openingHours', '每個營業時段須大於零且不超過 24 小時；跨日請勾選次日。');
        }
        return $period;
    }

    public function start(): int
    {
        return ((int) substr($this->opens, 0, 2)) * 60 + (int) substr($this->opens, 3, 2);
    }

    public function end(): int
    {
        return ((int) substr($this->closes, 0, 2)) * 60 + (int) substr($this->closes, 3, 2) + ($this->nextDay ? 1440 : 0);
    }

    /** @return array{opens:string,closes:string,nextDay:bool} */
    public function toArray(): array
    {
        return ['opens' => $this->opens,'closes' => $this->closes,'nextDay' => $this->nextDay];
    }

    public function description(): string
    {
        return $this->opens . '–' . ($this->nextDay ? '次日 ' : '') . $this->closes;
    }
}
