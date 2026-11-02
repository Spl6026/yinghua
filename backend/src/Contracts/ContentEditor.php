<?php

declare(strict_types=1);

namespace Yinghua\Contracts;

use Yinghua\DTO\ClientContext;
use Yinghua\DTO\PreparedContentChange;
use Yinghua\Models\Account;

/** 後台表單與 CLI 匯入共用的內容編輯：先驗證並準備變更，再在同一筆交易內寫入。 */
interface ContentEditor extends ContentImportService
{
    /** @param array<string,mixed> $values */
    public function prepareValues(Account $actor, string $id, array $values, bool $lock, ?bool $creating = null): PreparedContentChange;

    public function saveChange(Account $actor, PreparedContentChange $change, ClientContext $client): void;
}
