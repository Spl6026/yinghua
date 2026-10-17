<?php

declare(strict_types=1);

namespace Yinghua\DTO;

readonly class RemoteResponse
{
    /** @param array<string,string> $headers */
    public function __construct(public int $status, public string $body, public array $headers) {}
}
