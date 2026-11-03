<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use Yinghua\Exceptions\ErrorCode;

readonly class HealthReport
{
    public function __construct(public bool $ready, public string $version) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return $this->ready ? ['status' => 'ok', 'version' => $this->version]
            : ['status' => 'unavailable', 'version' => $this->version, 'code' => ErrorCode::ServiceUnavailable->value,
                'message' => '服務暫時無法使用。', 'fieldErrors' => (object) []];
    }
}
