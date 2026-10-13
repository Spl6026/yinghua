<?php

declare(strict_types=1);

namespace Yinghua\Repositories;

class RateLimitRepository
{
    public function __construct(private readonly Database $database) {}

    public function hit(string $scope, string $key, int $start, int $expires): int
    {
        // [E-04] INSERT/UPDATE 在 MariaDB 原子累加，不使用單一 FPM process 的計數器。
        $parameters = ['scope' => $scope, 'key' => $key, 'start' => $start];
        $this->database->execute(
            'INSERT INTO auth_rate_limits (scope,bucket_key,window_start,expires_at) VALUES (:scope,:key,:start,:expires) ON DUPLICATE KEY UPDATE attempt_count=attempt_count+1',
            [...$parameters, 'expires' => gmdate('Y-m-d H:i:s', $expires)],
        );
        $row = $this->database->row('SELECT attempt_count FROM auth_rate_limits WHERE scope=:scope AND bucket_key=:key AND window_start=:start', $parameters);

        return (new Row($row ?? []))->integer('attempt_count');
    }

    public function clear(string $scope, string $key): void
    {
        $this->database->execute('DELETE FROM auth_rate_limits WHERE scope=:scope AND bucket_key=:key', ['scope' => $scope, 'key' => $key]);
    }
}
