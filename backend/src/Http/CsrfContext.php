<?php

declare(strict_types=1);

namespace Yinghua\Http;

readonly class CsrfContext
{
    public function __construct(public string $id, public string $cookieValue, public bool $newCookie) {}
}
