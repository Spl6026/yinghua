<?php

declare(strict_types=1);

namespace Yinghua\Contracts;

use Yinghua\DTO\ClientContext;
use Yinghua\DTO\ImportItem;
use Yinghua\DTO\PreparedImport;
use Yinghua\Models\Account;

/** [E-26] CLI 與後續管理入口共用業務操作；批次交易由外層使用案例協調。 */
interface ContentImportService
{
    public function prepare(Account $actor, ImportItem $item, bool $lock): PreparedImport;

    public function save(Account $actor, PreparedImport $change, ClientContext $client): void;
}
