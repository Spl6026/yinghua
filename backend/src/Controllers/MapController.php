<?php

declare(strict_types=1);

namespace Yinghua\Controllers;

use Yinghua\DTO\Input;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Http\ApiContext;
use Yinghua\Http\Response;
use Yinghua\Services\MapResolver;

class MapController
{
    public function __construct(private readonly MapResolver $service) {}

    public function resolve(ApiContext $context): Response
    {
        $input = new Input($context->request->json());
        $input->only(['mapUrl','action']);
        $action = $input->text('action');
        if (!in_array($action, ['create','edit'], true)) {
            throw ApplicationError::invalid('action', '請指定新增或編輯用途。');
        }

        return new Response(200, $this->service->resolve($context->actor(), $input->text('mapUrl', 1, 2000), $action));
    }
}
