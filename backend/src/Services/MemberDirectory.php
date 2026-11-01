<?php

declare(strict_types=1);

namespace Yinghua\Services;

use Yinghua\Models\Account;
use Yinghua\Repositories\MemberDirectoryRepository;

class MemberDirectory
{
    public function __construct(private readonly MemberDirectoryRepository $members, private readonly Authorization $authorization) {}

    /** @return array{items:list<array<string,string>>,total:int,page:int,pageSize:int} */
    public function listing(Account $actor, string $query, int $page): array
    {
        $this->authorization->require($actor, 'members', 'view');
        return $this->members->listing($query, $page);
    }
}
