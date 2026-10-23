<?php

declare(strict_types=1);

namespace Yinghua\Contracts;

/** [E-29] 核心 DB／migration 就緒探測，正式 PDO 與受控故障測試共用邊界。 */
interface DatabaseHealthProbe
{
    public function ready(): bool;
}
