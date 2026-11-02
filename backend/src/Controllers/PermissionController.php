<?php

declare(strict_types=1);

namespace Yinghua\Controllers;

use Yinghua\DTO\PermissionMatrixInput;
use Yinghua\DTO\RoleTemplate;
use Yinghua\Http\ApiContext;
use Yinghua\Http\Response;
use Yinghua\Services\PermissionManagement;
use Yinghua\Services\RoleTemplates;

/** 權限與角色 API 的 HTTP 入口；業務規則與交易都交給 Service。 */
class PermissionController
{
    public function __construct(private readonly PermissionManagement $permissions, private readonly RoleTemplates $roles) {}

    public function roles(ApiContext $context): Response
    {
        return new Response(
            200,
            ['roles' => array_map(static fn(RoleTemplate $role): array => $role->toArray(), $this->roles->listing($context->actor()))],
        );
    }

    public function catalog(ApiContext $context): Response
    {
        return new Response(200, $this->permissions->catalog($context->actor())->toArray());
    }

    public function matrix(ApiContext $context): Response
    {
        return new Response(200, $this->permissions->matrix($context->actor(), $context->id())->toArray());
    }

    public function saveMatrix(ApiContext $context): Response
    {
        $this->permissions->saveMatrix(
            $context->actor(),
            $context->id(),
            PermissionMatrixInput::fromArray($context->request->json()),
            $context->client,
        );

        return new Response(204);
    }
}
