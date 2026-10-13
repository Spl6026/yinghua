<?php

declare(strict_types=1);

namespace Yinghua\DTO;

use Yinghua\Models\Account;

/** [U-06] 保留登入者與驗收對象；停用／刪除對象不能回退取得 root 權限。 */
readonly class AuthenticatedIdentity
{
    public function __construct(public Account $owner, public ?Account $preview, public ?string $previewAdminId) {}

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
