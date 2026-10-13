<?php

declare(strict_types=1);

namespace Yinghua\DTO;

/** [E-01／E-13] 矩陣欄位與最小選單摘要；不包含 Email 或登入憑證。 */
readonly class PermissionCatalog
{
    /** @param list<array{module:string,action:string}> $permissions
     * @param list<array{id:string,account:string,displayName:string,isRoot:bool}> $targets
     */
    public function __construct(public array $permissions, public array $targets) {}

    /** @return array{permissions:list<array{module:string,action:string}>,targets:list<array{id:string,account:string,displayName:string,isRoot:bool}>} */
    public function toArray(): array
    {
        return ['permissions' => $this->permissions, 'targets' => $this->targets];
    }
}
