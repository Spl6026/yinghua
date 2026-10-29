<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Throwable;
use Yinghua\Config;
use Yinghua\Contracts\DatabaseHealthProbe;
use Yinghua\DTO\HealthReport;

/** [D API草案 C43:H43／E-29] 核心 readiness，不代表郵件、Google 或公開發布已完成。 */
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
