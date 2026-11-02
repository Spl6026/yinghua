<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use Yinghua\Exceptions\ApplicationError;

/** 加入或移除目前行程站點的輸入；version=0 代表會員還沒有目前行程。名稱、日期等編排走完整行程 API。 */
readonly class ItineraryItemInput
{
    public function __construct(public int $version) {}

    /** @param array<string,mixed> $values */
    public static function fromArray(array $values): self
    {
        (new Input($values))->only(['version']);
        $version = $values['version'] ?? null;
        if (!is_int($version) || $version < 0 || $version > 4294967295) {
            throw ApplicationError::invalid('version', '請提供有效行程版本。');
        }

        return new self($version);
    }
}
