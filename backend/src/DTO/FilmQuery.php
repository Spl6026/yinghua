<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use Yinghua\Exceptions\ApplicationError;

readonly class FilmQuery
{
    public function __construct(public ScreeningWindow $window, public int $page, public int $pageSize) {}

    public static function fromValues(string $week, string $from, string $to, int $page, int $size, int $now): self
    {
        if (!in_array($week, ['', 'current'], true) || ($week !== '' && ($from !== '' || $to !== ''))) {
            throw ApplicationError::invalid('week', '請選擇本週或指定日期區間。');
        }
        $window = $from !== '' || $to !== '' ? ScreeningWindow::dates($from, $to) : ScreeningWindow::current($now);

        return new self($window, $page, $size);
    }
}
