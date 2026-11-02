<?php

declare(strict_types=1);

namespace Yinghua\DTO;

/** 單一管理員的權限矩陣，帶版本號以偵測同時編輯。 */
readonly class PermissionMatrix
{
    /** @param list<array{module:string,action:string,allowed:bool}> $permissions */
    public function __construct(public string $adminId, public int $version, public bool $isRoot, public array $permissions) {}

    /** @return array{adminId:string,version:int,isRoot:bool,permissions:list<array{module:string,action:string,allowed:bool}>} */
    public function toArray(): array
    {
        return ['adminId' => $this->adminId, 'version' => $this->version, 'isRoot' => $this->isRoot, 'permissions' => $this->permissions];
    }
}
