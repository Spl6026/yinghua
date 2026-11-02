<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\Support\Secrets;
use Yinghua\Contracts\Clock;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Repositories\RateLimitRepository;

class RateLimiter
{
    public function __construct(
        private readonly RateLimitRepository $repository,
        private readonly Secrets $secrets,
        private readonly Clock $clock,
    ) {}

    public function check(string $scope, string $identifier, int $maximum, int $seconds): void
    {
        $now = $this->clock->now();
        $start = intdiv($now, $seconds) * $seconds;
        if ($this->repository->hit($scope, $this->secrets->keyed('rate', $identifier), $start, $start + $seconds) > $maximum) {
            throw new ApplicationError('RATE_LIMITED', '操作過於頻繁，請稍後再試。', [], $start + $seconds - $now);
        }
    }

    public function clear(string $scope, string $identifier): void
    {
        $this->repository->clear($scope, $this->secrets->keyed('rate', $identifier));
    }
}
