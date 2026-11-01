<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\Exceptions\ApplicationError;
use Yinghua\Models\Account;
use Yinghua\Repositories\OperationsRepository;

/** [E-36] 儀表板權限不等於內容權限；隱藏模組連統計也不查。 */
class OperationsOverview
{
    public function __construct(private readonly OperationsRepository $records, private readonly Authorization $authorization) {}

    /** @return array{counts:array<string,int>,recent:list<array{module:string,title:string,status:string,updatedAt:string}>,auditAvailable:bool} */
    public function read(Account $actor): array
    {
        $fresh = $this->authorization->require($actor, 'dashboard', 'view');
        $counts = [];
        $recent = [];
        foreach ($this->authorization->permissions($fresh) as $permission) {
            if (!$permission['allowed'] || $permission['action'] !== 'view' || !in_array($permission['module'], ['attractions', 'films', 'news', 'offers', 'members'], true)) {
                continue;
            }
            $module = $permission['module'];
            $counts[$module] = $this->records->count($module);
            $recent = [...$recent, ...$this->records->recent($module)];
        }
        usort($recent, static fn(array $a, array $b): int => strcmp($b['updatedAt'], $a['updatedAt']) ?: strcmp($a['module'] . $a['title'], $b['module'] . $b['title']));
        return ['counts' => $counts, 'recent' => array_slice($recent, 0, 5), 'auditAvailable' => $fresh->isRoot];
    }

    /** @return array{items:list<array<string,string>>,total:int,page:int,pageSize:int} */
    public function audits(Account $actor, int $page): array
    {
        $fresh = $this->authorization->require($actor, 'dashboard', 'view');
        if (!$fresh->isRoot) {
            throw ApplicationError::forbidden();
        }
        return $this->records->audits($page);
    }
}
