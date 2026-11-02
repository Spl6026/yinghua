<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\Config;
use Yinghua\Contracts\ContentRenderer;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Repositories\GeneralSettingsRepository;

/** 註冊時只接受已實際發布的政策版本；發布失敗不會影響既有會員登入。 */
class PublishedPolicies
{
    public function __construct(
        private readonly GeneralSettingsRepository $settings,
        private readonly ContentRenderer $renderer,
        private readonly Config $config,
    ) {}

    public function configurationVersion(): string
    {
        $snapshot = $this->renderer->current();
        $version = $snapshot['policyVersion'] ?? null;
        return is_string($version) ? $version : $this->config->termsVersion();
    }

    public function current(bool $lock = false): string
    {
        $document = $this->settings->read($lock);
        $expected = $document->values->consentVersion($this->config->termsVersion());
        $snapshot = $this->renderer->current();
        $general = $snapshot['general'] ?? null;
        if ($document->version !== 0
            && (!is_array($general)
                || ($general['version'] ?? null) !== $document->version
                || ($snapshot['policyVersion'] ?? null) !== $expected)) {
            throw new ApplicationError('PUBLICATION_PENDING', '政策頁面正在更新，請稍後重試。', retryAfter: 5);
        }
        return $expected;
    }
}
