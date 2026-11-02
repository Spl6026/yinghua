<?php

declare(strict_types=1);

namespace Yinghua\Controllers;

use Yinghua\DTO\AdminWriteInput;
use Yinghua\DTO\Input;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Http\ApiContext;
use Yinghua\Http\Response;
use Yinghua\Services\AdminManagement;

class AdminController
{
    public function __construct(private readonly AdminManagement $service) {}

    public function listing(ApiContext $context): Response
    {
        $status = $context->request->queryText('status');
        if (!in_array($status, ['', 'active', 'disabled'], true)) {
            throw ApplicationError::invalid('status', '不支援的篩選狀態。');
        }

        return new Response(200, $this->service->listing(
            $context->actor(),
            $context->request->queryText('q'),
            $status,
            $context->request->queryInteger('page', 1, 100000),
            $context->request->queryInteger('pageSize', 20, 100),
        )->toArray());
    }

    public function get(ApiContext $context): Response
    {
        return new Response(200, ['admin' => $this->service->get($context->actor(), $context->id())->toArray()]);
    }

    public function create(ApiContext $context): Response
    {
        $created = $this->service->create($context->actor(), AdminWriteInput::fromArray($context->request->json(), true), $context->client);

        return new Response(201, ['admin' => $created->toArray()]);
    }

    public function update(ApiContext $context): Response
    {
        $updated = $this->service->update(
            $context->actor(),
            $context->id(),
            AdminWriteInput::fromArray($context->request->json(), false),
            $context->client,
        );

        return new Response(200, ['admin' => $updated->toArray()]);
    }

    public function delete(ApiContext $context): Response
    {
        $values = $context->request->json();
        $input = new Input($values);
        $input->only(['version']);
        $this->service->delete(
            $context->actor(),
            $context->id(),
            array_key_exists('version', $values) ? $input->positiveInt('version') : null,
            $context->client,
        );

        return new Response(204);
    }
}
