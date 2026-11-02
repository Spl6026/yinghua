<?php

declare(strict_types=1);

namespace Yinghua\DTO;

/** 角色的起始權限模板；帳號實際擁有的權限以個人權限矩陣為準。 */
readonly class RoleTemplate
{
    /** @param list<array{module:string,action:string,allowed:bool}> $permissions */
    public function __construct(public string $id, public string $name, public array $permissions) {}

    /** @return array{id:string,name:string,permissions:list<array{module:string,action:string,allowed:bool}>} */
    public function toArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'permissions' => $this->permissions];
    }
}
