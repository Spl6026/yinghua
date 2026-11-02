<?php

declare(strict_types=1);

namespace Yinghua\Controllers;

use Yinghua\DTO\{AdminContentQuery};
use Yinghua\Http\{ApiContext, Response};
use Yinghua\Services\{PublicRecommendedRoutes, RecommendedRoutes};

class RecommendedRouteController
{
    public function __construct(private readonly RecommendedRoutes $service, private readonly PublicRecommendedRoutes $publicRoutes) {}

    public function listing(ApiContext $context): Response
    {
        $r = $context->request;
        return new Response(
            200,
            $this->service->listing(
                $context->actor(),
                new AdminContentQuery(
                    $r->queryText('q'),
                    $r->queryText('status'),
                    '',
                    '',
                    '',
                    $r->page(),
                    $r->pageSize(),
                ),
            ),
        );
    }

    public function candidates(ApiContext $context): Response
    {
        return new Response(
            200,
            $this->service->candidates(
                $context->actor(),
                $context->request->queryText('q'),
                $context->request->page(),
            ),
        );
    }

    public function get(ApiContext $context): Response
    {
        return new Response(200, ['record' => $this->service->get($context->actor(), $context->id())->toArray()]);
    }

    public function create(ApiContext $context): Response
    {
        $result = $this->service->save($context->actor(), null, $context->request->json(), $context->client);
        return Response::written($result->synchronized, $result->toArray(), created: true);
    }

    public function update(ApiContext $context): Response
    {
        $result = $this->service->save($context->actor(), $context->id(), $context->request->json(), $context->client);
        return Response::written($result->synchronized, $result->toArray());
    }

    public function delete(ApiContext $context): Response
    {
        $result = $this->service->delete($context->actor(), $context->id(), $context->request->versionOnly(), $context->client);
        return Response::written($result->synchronized, $result->toArray());
    }

    public function publishedOne(ApiContext $context): Response
    {
        return new Response(200, $this->publicRoutes->get($context->id()));
    }

    public function published(ApiContext $context): Response
    {
        $r = $context->request;
        return new Response(
            200,
            $this->publicRoutes->listing(
                $r->queryText('theme'),
                $r->page(),
                $r->pageSize(),
            ),
        );
    }
}
