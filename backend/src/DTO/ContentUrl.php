<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use Yinghua\Exceptions\ApplicationError;

/** 只驗證外部連結的格式，伺服器不會去下載這些網址。 */
readonly class ContentUrl
{
    public static function validate(string $value, string $field): string
    {
        if ($value !== '' && (filter_var($value, FILTER_VALIDATE_URL) === false
            || !in_array(parse_url($value, PHP_URL_SCHEME), ['http', 'https'], true)
            || parse_url($value, PHP_URL_USER) !== null || parse_url($value, PHP_URL_PASS) !== null
            || preg_match('/[\x00-\x20\x7f]/', $value))) {
            throw ApplicationError::invalid($field, '請輸入不含登入資訊的 HTTP 或 HTTPS 網址。');
        }

        return $value;
    }
}
