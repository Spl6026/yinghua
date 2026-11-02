<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use Yinghua\Exceptions\ApplicationError;

/** 在 API 邊界集中檢查 JSON 欄位的型別、白名單與長度。 */
readonly class Input
{
    /** @param array<string, mixed> $values */
    public function __construct(private array $values) {}

    /** @param list<string> $keys */
    public function only(array $keys): void
    {
        foreach (array_keys($this->values) as $key) {
            if (!in_array($key, $keys, true)) {
                throw ApplicationError::invalid($key, '不允許的欄位。');
            }
        }
    }

    public function text(string $key, int $min = 1, int $max = 255, bool $trim = true): string
    {
        $value = $this->values[$key] ?? null;
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8')) {
            throw ApplicationError::invalid($key, '請輸入有效文字。');
        }
        $value = $trim ? trim($value) : $value;
        if (mb_strlen($value) < $min || mb_strlen($value) > $max || str_contains($value, "\0")) {
            throw ApplicationError::invalid($key, sprintf('長度須為 %d–%d 個字元。', $min, $max));
        }

        return $value;
    }

    public function email(): string
    {
        $email = mb_strtolower($this->text('email', 3, 254));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw ApplicationError::invalid('email', '請輸入有效電子郵件。');
        }

        return $email;
    }

    /** 選填文字：沒送或空白都存成空字串。 */
    public function optionalText(string $key, int $max = 255): string
    {
        return array_key_exists($key, $this->values) ? $this->text($key, 0, $max) : '';
    }

    /** 密碼至少 8 字；只有一個密碼欄位的情境（例如初始密碼）不要求確認欄。 */
    public function password(): string
    {
        return $this->text('password', 8, 128, false);
    }

    /** 註冊、重設與更改密碼時要再輸入一次且完全一致；密碼不做前後空白修剪。 */
    public function confirmedPassword(): string
    {
        $password = $this->password();
        if ($password !== $this->text('passwordConfirmation', 8, 128, false)) {
            throw ApplicationError::invalid('passwordConfirmation', '兩次密碼不一致。');
        }

        return $password;
    }

    public function positiveInt(string $key): int
    {
        $value = $this->values[$key] ?? null;
        if (!is_int($value) || $value < 1) {
            throw ApplicationError::invalid($key, '請提供有效版本。');
        }

        return $value;
    }

    public function boolean(string $key): bool
    {
        $value = $this->values[$key] ?? null;
        if (!is_bool($value)) {
            throw ApplicationError::invalid($key, '請提供布林值。');
        }

        return $value;
    }

    /** @return list<array<string, mixed>> */
    public function objects(string $key, int $maximum = 100): array
    {
        $values = $this->values[$key] ?? null;
        if (!is_array($values) || !array_is_list($values) || count($values) > $maximum) {
            throw ApplicationError::invalid($key, '請提供有效清單。');
        }
        $result = [];
        foreach ($values as $value) {
            if (!is_array($value)) {
                throw ApplicationError::invalid($key, '清單項目須為物件。');
            }
            $row = [];
            foreach ($value as $field => $entry) {
                if (!is_string($field)) {
                    throw ApplicationError::invalid($key, '清單項目須為物件。');
                }
                $row[$field] = $entry;
            }
            $result[] = $row;
        }

        return $result;
    }
}
