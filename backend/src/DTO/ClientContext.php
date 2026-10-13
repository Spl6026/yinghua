<?php

declare(strict_types=1);

namespace Yinghua\DTO;

readonly class ClientContext
{
    public function __construct(
        public string $anonymousId,
        public string $ipHash,
        public string $userAgentHash,
        public string $requestId,
    ) {}
}
