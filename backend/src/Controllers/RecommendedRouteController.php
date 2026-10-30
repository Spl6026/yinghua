<?php

declare(strict_types=1);

namespace Yinghua\Controllers;

use Yinghua\DTO\{AdminContentQuery, Input};
use Yinghua\Http\{ApiContext, Response};
use Yinghua\Services\{PublicRecommendedRoutes, RecommendedRoutes};

class RecommendedRouteController
{
    public function __construct(private readonly RecommendedRoutes $service, private readonly PublicRecommendedRoutes $publicRoutes) {}

    public function listing(ApiContext $context): Response
    {
        $r = $context->request;
        return new Response(200, $this->service->listing($context->actor(), new AdminContentQuery($r->queryText('q'), $r->queryText('status'), '', '', '', $r->queryInteger('page', 1, 100000), $r->queryInteger('pageSize', 20, 100))));
    }

    public function candidates(ApiContext $context): Response
    {
        return new Response(200, $this->service->candidates($context->actor(), $context->request->queryText('q'), $context->request->queryInteger('page', 1, 100000)));
    }

    public function get(ApiContext $context): Response
    {
        return new Response(200, ['record' => $this->service->get($context->actor(), $context->id())->toArray()]);
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

    public function publishedOne(ApiContext $context): Response
    {
        return new Response(200, $this->publicRoutes->get($context->id()));
    }

    public function published(ApiContext $context): Response
    {
        $r = $context->request;
        return new Response(200, $this->publicRoutes->listing($r->queryText('theme'), $r->queryInteger('page', 1, 100000), $r->queryInteger('pageSize', 20, 100)));
    }
}
