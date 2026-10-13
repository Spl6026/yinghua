<?php

declare(strict_types=1);

namespace Yinghua\Http;

use JsonException;
use Yinghua\Exceptions\ApplicationError;

readonly class Request
{
    /** @param array<string,mixed> $query
     * @param array<string,string> $headers
     * @param array<string,string> $cookies
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $query,
        public array $headers,
        public array $cookies,
        public string $body,
        public string $ip,
        public string $userAgent,
        public string $requestId,
    ) {}

    public static function capture(): self
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (is_string($key) && is_string($value) && str_starts_with($key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($key, 5)))] = $value;
            }
        }
        $headers['content-type'] = self::server('CONTENT_TYPE');
        $uri = self::server('REQUEST_URI', '/');
        $path = parse_url($uri, PHP_URL_PATH);
        if (!is_string($path) || preg_match('~[\\\\\x00-\x1f]|%(?:00|2f|5c)~i', $path)) {
            throw new ApplicationError('BAD_REQUEST', '無效網址。');
        }
        $path = rawurldecode($path);
        if (preg_match('~(?:^|/)\.{1,2}(?:/|$)~', $path)) {
            throw new ApplicationError('BAD_REQUEST', '無效網址。');
        }
        $cookies = [];
        foreach ($_COOKIE as $name => $value) {
            if (is_string($name) && is_string($value)) {
                $cookies[$name] = $value;
            }
        }
        $query = [];
        foreach ($_GET as $name => $value) {
            if (is_string($name)) {
                $query[$name] = $value;
            }
        }
        $body = file_get_contents('php://input', false, null, 0, 65537);
        if ($body === false || strlen($body) > 65536) {
            throw new ApplicationError('PAYLOAD_TOO_LARGE', '請求內容過大。');
        }

        return new self(
            strtoupper(self::server('REQUEST_METHOD', 'GET')),
            $path,
            $query,
            $headers,
            $cookies,
            $body,
            self::server('REMOTE_ADDR'),
            self::server('HTTP_USER_AGENT'),
            bin2hex(random_bytes(16)),
        );
    }

    /** @return array<string,mixed> */
    public function json(): array
    {
        if ($this->body === '') {
            return [];
        }
        if (strtolower(trim(explode(';', $this->headers['content-type'] ?? '')[0])) !== 'application/json') {
            throw new ApplicationError('UNSUPPORTED_MEDIA_TYPE', '請使用 application/json。');
        }
        try {
            $value = json_decode($this->body, false, 32, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new ApplicationError('BAD_REQUEST', 'JSON 格式不正確。');
        }
        if (!$value instanceof \stdClass) {
            throw new ApplicationError('BAD_REQUEST', 'JSON 最外層須為物件。');
        }
        // 再以 associative 模式讀取，巢狀物件交給 DTO 的欄位驗證。
        $values = json_decode($this->body, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($values)) {
            throw new ApplicationError('BAD_REQUEST', 'JSON 格式不正確。');
        }
        $result = [];
        foreach ($values as $key => $entry) {
            // [E-01] PHP 會把數字字串 key 轉成 int；不能默默丟棄而繞過 DTO 欄位白名單。
            if (!is_string($key)) {
                throw ApplicationError::invalid((string) $key, '不允許的欄位。');
            }
            $result[$key] = $entry;
        }

        return $result;
    }

    public function queryText(string $name, string $default = '', int $maximum = 80): string
    {
        $value = $this->query[$name] ?? $default;
        if (!is_string($value) || !mb_check_encoding($value, 'UTF-8') || mb_strlen($value) > $maximum) {
            throw ApplicationError::invalid($name, '無效的查詢參數。');
        }

        return trim($value);
    }

    public function queryInteger(string $name, int $default, int $maximum): int
    {
        $value = $this->queryText($name, (string) $default, 8);
        if (!ctype_digit($value) || (int) $value < 1 || (int) $value > $maximum) {
            throw ApplicationError::invalid($name, '無效的分頁參數。');
        }

        return (int) $value;
    }

    private static function server(string $name, string $default = ''): string
    {
        $value = $_SERVER[$name] ?? $default;

        return is_string($value) ? $value : $default;
    }
}
