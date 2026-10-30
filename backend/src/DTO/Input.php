<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use Yinghua\Exceptions\ApplicationError;

/** [E-01] JSON 的型別、白名單與長度在邊界集中驗證。 */
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

    /** [U-12] 文件未指定非空的文字，省略或空白均保存空字串。 */
    public function optionalText(string $key, int $max = 255): string
    {
        return array_key_exists($key, $this->values) ? $this->text($key, 0, $max) : '';
    }

    /** [U-13／原型] 共用八字密碼邊界；單一初始密碼不要求不存在的確認欄。 */
    public function password(): string
    {
        return $this->text('password', 8, 128, false);
    }

    /** [U-15] 註冊、重設及更改密碼必須再次輸入，且逐字一致；不修剪密碼。 */
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
