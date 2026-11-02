<?php

declare(strict_types=1);

namespace Yinghua\Controllers;

use Yinghua\DTO\{AdminContentQuery, ContentRecordView, Input};
use Yinghua\Http\{ApiContext, Response};
use Yinghua\Services\AdminNewsManagement;

class AdminNewsController
{
    public function __construct(private readonly AdminNewsManagement $service) {}

    public function listing(ApiContext $context): Response
    {
        $r = $context->request;
        $query = new AdminContentQuery(
            $r->queryText('q'),
            $r->queryText('status'),
            $r->queryText('type'),
            $r->queryText('merchantId'),
            $r->queryText('active'),
            $r->queryInteger('page', 1, 100000),
            $r->queryInteger('pageSize', 20, 100),
        );
        return new Response(200, $this->service->listing($context->actor(), $query)->toArray());
    }

    public function get(ApiContext $context): Response
    {
        return new Response(200, ['record' => (new ContentRecordView($this->service->get($context->actor(), $context->id())))->toArray()]);
    }


    public function create(ApiContext $context): Response
    {
        $result = $this->service->save($context->actor(), null, $context->request->json(), $context->client);
        return new Response($result->synchronized ? 201 : 202, $result->toArray());
    }

    public function update(ApiContext $context): Response
    {
        $result = $this->service->save($context->actor(), $context->id(), $context->request->json(), $context->client);
        return new Response($result->synchronized ? 200 : 202, $result->toArray());
    }

    public function delete(ApiContext $context): Response
    {
        $input = new Input($context->request->json());
        $input->only(['version']);
        $result = $this->service->delete($context->actor(), $context->id(), $input->positiveInt('version'), $context->client);
        return new Response($result->synchronized ? 200 : 202, $result->toArray());
    }

}
