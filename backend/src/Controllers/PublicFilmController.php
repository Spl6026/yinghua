<?php

declare(strict_types=1);

namespace Yinghua\Controllers;

use Yinghua\Contracts\Clock;
use Yinghua\DTO\FilmQuery;
use Yinghua\Http\ApiContext;
use Yinghua\Http\Response;
use Yinghua\Models\DetailUrlType;
use Yinghua\Services\DetailUrlResolver;
use Yinghua\Services\PublicFilms;

class PublicFilmController
{
    public function __construct(
        private readonly PublicFilms $service,
        private readonly Clock $clock,
        private readonly DetailUrlResolver $detailUrls,
    ) {}

    public function listing(ApiContext $context): Response
    {
        $r = $context->request;
        $query = FilmQuery::fromValues(
            $r->queryText('week'),
            $r->queryText('from'),
            $r->queryText('to'),
            $r->page(),
            $r->pageSize(),
            $this->clock->now(),
        );

        return new Response(200, $this->service->listing($query));
    }

    public function get(ApiContext $context): Response
    {
        return new Response(200, $this->service->get($context->parameters['slug'] ?? ''));
    }

    public function resolve(ApiContext $context): Response
    {
        return new Response(301, [], [], ['Location' => $this->detailUrls->resolve(DetailUrlType::Film, $context->request->headers['x-original-uri'] ?? '')]);
    }
}
