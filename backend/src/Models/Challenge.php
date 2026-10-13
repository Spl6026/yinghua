<?php

declare(strict_types=1);

namespace Yinghua\Models;

readonly class Challenge
{
    public function __construct(
        public string $id,
        public string $mode,
        public string $answerHmac,
        public string $bindingHash,
        public ?string $recipientHash,
        public int $attempts,
        public int $maxAttempts,
        public int $expiresAt,
        public bool $unavailable,
    ) {}
}
