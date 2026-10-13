<?php

declare(strict_types=1);

namespace Yinghua\Controllers;

use Yinghua\DTO\RoleTemplate;
use Yinghua\Http\ApiContext;
use Yinghua\Http\Response;
use Yinghua\Services\RoleTemplates;

/** [E-01／S-027] 權限與角色 API 的 HTTP 邊界；業務與交易留在各 Service。 */
class PermissionController
{
    public function __construct(private readonly RoleTemplates $roles) {}

    public function roles(ApiContext $context): Response
    {
        return new Response(200, ['roles' => array_map(static fn(RoleTemplate $role): array => $role->toArray(), $this->roles->listing($context->actor()))]);
    }
}
