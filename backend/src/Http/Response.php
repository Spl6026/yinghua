<?php

declare(strict_types=1);

namespace Yinghua\Http;

use Closure;
use Yinghua\Support\Json;

readonly class Response
{
    /** @param array<string,mixed> $payload
     * @param list<Cookie> $cookies
     * @param array<string,string> $headers
     * @param list<Closure():void> $afterSend
     */
    public function __construct(
        public int $status,
        public array $payload = [],
        public array $cookies = [],
        public array $headers = [],
        public array $afterSend = [],
    ) {}

    /**
     * 寫入類 API 的回應：資料已保存但公開頁尚未同步時回 202，讓前端提示稍後重試發布；
     * 否則新增回 201、修改與刪除回 200。
     *
     * @param array<string,mixed> $payload
     */
    public static function written(bool $synchronized, array $payload, bool $created = false): self
    {
        return new self($synchronized ? ($created ? 201 : 200) : 202, $payload);
    }

    public function withCookie(Cookie $cookie): self
    {
        return new self($this->status, $this->payload, [...$this->cookies, $cookie], $this->headers, $this->afterSend);
    }

    /** @param array<string,string> $headers */
    public function withHeaders(array $headers): self
    {
        return new self($this->status, $this->payload, $this->cookies, [...$this->headers, ...$headers], $this->afterSend);
    }

    public function emit(string $requestId, bool $head = false): void
    {
        http_response_code($this->status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        header('X-Request-Id: ' . $requestId);
        header('X-Content-Type-Options: nosniff');
        foreach ($this->headers as $name => $value) {
            header($name . ': ' . $value);
        }
        foreach ($this->cookies as $cookie) {
            $cookie->emit();
        }
        if (!$head && $this->status !== 204) {
            echo Json::encode([...$this->payload, 'requestId' => $requestId]);
        }
        if ($this->afterSend !== []) {
            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            foreach ($this->afterSend as $callback) {
                $callback();
            }
        }
    }
}
