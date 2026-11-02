<?php

declare(strict_types=1);

namespace Yinghua\DTO;

/** 從 session 還原出的身分資料，不含明文憑證。 */
readonly class SessionIdentity
{
    public function __construct(public string $id, public string $ownerId, public ?string $previewAdminId, public SessionTiming $timing) {}
}
