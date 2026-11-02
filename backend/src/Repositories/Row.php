<?php

declare(strict_types=1);

namespace Yinghua\Repositories;

use UnexpectedValueException;

/** PDO 回傳的是 mixed；在這裡先確認型別，再交給模型使用。 */
readonly class Row
{
    /** @param array<string, mixed> $values */
    public function __construct(private array $values) {}

    public function string(string $key): string
    {
        $value = $this->values[$key] ?? null;
        if (!is_string($value)) {
            throw new UnexpectedValueException('Unexpected database field: ' . $key);
        }

        return $value;
    }

    public function nullableString(string $key): ?string
    {
        return ($this->values[$key] ?? null) === null ? null : $this->string($key);
    }

    public function integer(string $key): int
    {
        $value = $this->values[$key] ?? null;
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }
        throw new UnexpectedValueException('Unexpected database integer: ' . $key);
    }
}
