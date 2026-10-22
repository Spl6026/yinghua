<?php

declare(strict_types=1);

namespace Yinghua\Services;

class PublicHome
{
    public function __construct(private readonly ContentPublication $publication) {}

    /** @return array<string,mixed> */
    public function get(): array
    {
        // [E-26] 只讀一次，與 SSR 相同選取結果；不逐一呼叫多個主題 API。
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
