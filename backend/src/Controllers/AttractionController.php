<?php

declare(strict_types=1);

namespace Yinghua\Controllers;

use Yinghua\DTO\AttractionInput;
use Yinghua\Http\ApiContext;
use Yinghua\Http\Response;
use Yinghua\Services\AttractionManagement;

class AttractionController
{
    public function __construct(private readonly AttractionManagement $service) {}

    public function listing(ApiContext $context): Response
    {
        $r = $context->request;

        return new Response(200, $this->service->listing(
            $context->actor(),
            $r->queryText('q'),
            $r->queryText('status'),
            $r->page(),
            $r->pageSize(),
        )->toArray());
    }

    public function samples(ApiContext $context): Response
    {
        return new Response(200, ['items' => $this->service->samples($context->actor())]);
    }

    public function get(ApiContext $context): Response
    {
        return new Response(200, ['attraction' => $this->service->get($context->actor(), $context->id())->toArray()]);
    }

    public function create(ApiContext $context): Response
    {
        $result = $this->service->save(
            $context->actor(),
            null,
            AttractionInput::fromArray($context->request->json(), true),
            $context->client,
        );

        return Response::written($result->synchronized, $result->toArray(), created: true);
    }

    public function update(ApiContext $context): Response
    {
        $result = $this->service->save(
            $context->actor(),
            $context->id(),
            AttractionInput::fromArray($context->request->json(), false),
            $context->client,
        );

        return Response::written($result->synchronized, $result->toArray());
    }

    public function delete(ApiContext $context): Response
    {
        $result = $this->service->delete($context->actor(), $context->id(), $context->request->versionOnly(), $context->client);

        return Response::written($result->synchronized, $result->toArray());
    }

    public function retry(ApiContext $context): Response
    {
        $success = $this->service->retry($context->actor());

        return new Response($success ? 200 : 503, ['publication' => ['status' => $success ? 'succeeded' : 'failed'],
            'message' => $success ? '公開頁面已同步。' : '發布仍未完成，請稍後重試。']);
    }
}
