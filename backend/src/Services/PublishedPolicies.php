<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\Config;
use Yinghua\Contracts\PublishedSnapshot;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Repositories\GeneralSettingsRepository;
use Yinghua\Exceptions\ErrorCode;

/** 註冊時只接受已實際發布的政策版本；發布失敗不會影響既有會員登入。 */
class PublishedPolicies
{
    public function __construct(
        private readonly GeneralSettingsRepository $settings,
        private readonly PublishedSnapshot $published,
        private readonly Config $config,
    ) {}

    public function configurationVersion(): string
    {
        $snapshot = $this->published->current();
        $version = $snapshot['policyVersion'] ?? null;
        return is_string($version) ? $version : $this->config->termsVersion();
    }

    /**
     * 註冊頁顯示給會員看的條款版本名稱：後台有填就用該名稱，與條款頁一致；
     * 沒填時與同意紀錄的版本相同。同意紀錄仍保存 configurationVersion() 的內部代碼。
     */
    public function configurationLabel(): string
    {
        $general = $this->published->current()['general'] ?? null;
        $values = is_array($general) ? ($general['values'] ?? null) : null;
        $label = is_array($values) ? ($values['termsVersion'] ?? '') : '';

        return is_string($label) && $label !== '' ? $label : $this->configurationVersion();
    }

    public function current(bool $lock = false): string
    {
        $document = $this->settings->read($lock);
        $expected = $document->values->consentVersion($this->config->termsVersion());
        $snapshot = $this->published->current();
        $general = $snapshot['general'] ?? null;
        if ($document->version !== 0
            && (!is_array($general)
                || ($general['version'] ?? null) !== $document->version
                || ($snapshot['policyVersion'] ?? null) !== $expected)) {
            throw new ApplicationError(ErrorCode::PublicationPending, '政策頁面正在更新，請稍後重試。', retryAfter: 5);
        }
        return $expected;
    }
}
