<?php

declare(strict_types=1);

namespace Yinghua\DTO;

readonly class HealthReport
{
    public function __construct(public bool $ready, public string $version) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return $this->ready ? ['status' => 'ok', 'version' => $this->version]
            : ['status' => 'unavailable', 'version' => $this->version, 'code' => 'SERVICE_UNAVAILABLE',
                'message' => '服務暫時無法使用。', 'fieldErrors' => (object) []];
    }
}
