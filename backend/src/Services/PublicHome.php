<?php

declare(strict_types=1);

namespace Yinghua\Services;

class PublicHome
{
    public function __construct(private readonly ContentPublication $publication) {}

    /** @return array<string,mixed> */
    public function get(): array
    {
        // 快照只讀一次，挑選結果與伺服器產生的首頁相同，不必逐一呼叫各主題的 API。
        $snapshot = $this->publication->read();
        $home = $snapshot['home'] ?? null;
        if (!is_array($home)) {
            throw new \UnexpectedValueException('Home projection missing.');
        }
        $result = [];
        foreach ($home as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }
        return [...$result,'generation' => $snapshot['generation']];
    }
}
