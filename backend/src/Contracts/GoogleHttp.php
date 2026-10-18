<?php

declare(strict_types=1);

namespace Yinghua\Contracts;

use Yinghua\DTO\RemoteResponse;

interface GoogleHttp
{
    /** @param array<string,string> $headers */
    public function request(string $url, array $headers = [], ?string $body = null, bool $api = false): RemoteResponse;
}
