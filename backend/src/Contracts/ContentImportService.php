<?php

declare(strict_types=1);

namespace Yinghua\Contracts;

use Yinghua\DTO\ClientContext;
use Yinghua\DTO\ImportItem;
use Yinghua\DTO\PreparedImport;
use Yinghua\Models\Account;

/** CLI 匯入與管理介面共用的內容操作；批次匯入的交易由呼叫端負責。 */
interface ContentImportService
{
    public function prepare(Account $actor, ImportItem $item, bool $lock): PreparedImport;

    public function save(Account $actor, PreparedImport $change, ClientContext $client): void;
}
