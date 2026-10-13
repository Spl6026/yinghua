<?php

declare(strict_types=1);

namespace Yinghua\DTO;

/** [U-06] Session 的資料邊界；不含明文憑證。 */
readonly class SessionIdentity
{
    public function __construct(public string $id, public string $ownerId, public ?string $previewAdminId) {}
}
