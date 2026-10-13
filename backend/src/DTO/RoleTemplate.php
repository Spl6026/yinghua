<?php

declare(strict_types=1);

namespace Yinghua\DTO;

/** [E-01／E-10] 角色起始模板，不代表帳號即時的個人權限。 */
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
