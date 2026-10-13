<?php

declare(strict_types=1);

namespace Yinghua\Models;

readonly class ResetToken
{
    public function __construct(public string $id, public string $ownerId, public int $expiresAt, public bool $unavailable) {}
}
