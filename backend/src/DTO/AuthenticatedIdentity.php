<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use Yinghua\Models\Account;

/** 同時保存實際登入者與模擬檢視的對象；對象被停用或刪除時，不能退回使用登入者（root）的權限。 */
readonly class AuthenticatedIdentity
{
    public function __construct(
        public Account $owner,
        public ?Account $preview,
        public ?string $previewAdminId,
        public SessionTiming $timing,
    ) {}

    public function effective(): Account
    {
        return $this->preview ?? $this->owner;
    }

    public function isPreview(): bool
    {
        return $this->previewAdminId !== null;
    }

    public function previewAvailable(): bool
    {
        return !$this->isPreview() || ($this->preview !== null && $this->preview->isActive() && !$this->preview->isRoot);
    }
}
