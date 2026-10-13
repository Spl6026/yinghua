<?php

declare(strict_types=1);

namespace Yinghua\DTO;

/** [E-01／S-027] 明確保存對象與權限版本，維持既有 JSON 契約。 */
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
