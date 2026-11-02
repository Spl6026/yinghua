<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\Config;
use Yinghua\Contracts\ContentRenderer;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Repositories\GeneralSettingsRepository;

/** [S：G16/G28；E-36] 註冊只接受已實際發布的政策摘要，既有登入不受發布失敗阻斷。 */
class PublishedPolicies
{
    public function __construct(private readonly GeneralSettingsRepository $settings, private readonly ContentRenderer $renderer, private readonly Config $config) {}

    public function configurationVersion(): string
    {
        $snapshot = $this->renderer->current();
        $version = $snapshot['policyVersion'] ?? null;
        return is_string($version) ? $version : $this->config->string('TERMS_VERSION', '2026-11-02');
    }

    public function current(bool $lock = false): string
    {
        $document = $this->settings->read($lock);
        $expected = $document->values->consentVersion($this->config->string('TERMS_VERSION', '2026-11-02'));
        $snapshot = $this->renderer->current();
        $general = $snapshot['general'] ?? null;
        if ($document->version !== 0 && (!is_array($general) || ($general['version'] ?? null) !== $document->version || ($snapshot['policyVersion'] ?? null) !== $expected)) {
            throw new ApplicationError('PUBLICATION_PENDING', '政策頁面正在更新，請稍後重試。', retryAfter: 5);
        }
        return $expected;
    }
}
