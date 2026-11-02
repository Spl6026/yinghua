<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Throwable;
use Yinghua\Config;
use Yinghua\Contracts\DatabaseHealthProbe;
use Yinghua\DTO\HealthReport;

/** 核心服務的 readiness 檢查；通過不代表郵件、Google 或公開發布都正常。 */
readonly class ApplicationHealth
{
    public function __construct(private Config $config, private DatabaseHealthProbe $database) {}

    public function inspect(): HealthReport
    {
        $version = 'unavailable';
        try {
            $version = $this->config->applicationVersion();
            $this->config->secret();
            $this->config->origin();

            return new HealthReport($this->database->ready(), $version);
        } catch (Throwable $error) {
            error_log('health_exception=' . $error::class);

            return new HealthReport(false, $version);
        }
    }
}
