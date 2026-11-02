<?php

declare(strict_types=1);

namespace Yinghua\Controllers;

use Yinghua\DTO\NewsQuery;
use Yinghua\Http\ApiContext;
use Yinghua\Http\Response;
use Yinghua\Models\DetailUrlType;
use Yinghua\Services\DetailUrlResolver;
use Yinghua\Services\PublicNews;

class PublicNewsController
{
    public function __construct(private readonly PublicNews $service, private readonly DetailUrlResolver $detailUrls) {}

    public function listing(ApiContext $context): Response
    {
        $r = $context->request;
        return new Response(200, $this->service->listing(new NewsQuery(
            $r->queryText('type'),
            $r->queryText('q', '', 255),
            $r->page(),
            $r->pageSize(),
        )));
    }

    public function get(ApiContext $context): Response
    {
        return new Response(200, $this->service->get($context->parameters['slug'] ?? ''));
    }

    public function resolve(ApiContext $context): Response
    {
        $original = $context->request->headers['x-original-uri'] ?? $context->request->queryText('path', '', 1000);
        if ($original === '' && $context->request->queryText('id') !== '') {
            $original = '/news-detail.html?id=' . rawurlencode($context->request->queryText('id'));
        }
        return new Response(301, [], [], ['Location' => $this->detailUrls->resolve(DetailUrlType::News, $original)]);
    }
}
