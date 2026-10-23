<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use Yinghua\Exceptions\ApplicationError;

/** [E-27] version=0 只用於尚無目前行程；此階段不接受名稱／日期等完整編排。 */
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
