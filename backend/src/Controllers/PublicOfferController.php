<?php

declare(strict_types=1);

namespace Yinghua\Controllers;

use Yinghua\DTO\OfferQuery;
use Yinghua\Exceptions\ApplicationError;
use Yinghua\Http\ApiContext;
use Yinghua\Http\Response;
use Yinghua\Models\DetailUrlType;
use Yinghua\Services\DetailUrlResolver;
use Yinghua\Services\PublicOffers;

class PublicOfferController
{
    public function __construct(private readonly PublicOffers $service, private readonly DetailUrlResolver $detailUrls) {}

    public function listing(ApiContext $context): Response
    {
        $r = $context->request;
        $active = $r->queryText('active', 'true');
        if (!in_array($active, ['true','false'], true)) {
            throw ApplicationError::invalid('active', 'active 須為 true／false。');
        }
        return new Response(200, $this->service->listing(new OfferQuery(
            $active === 'true',
            $r->queryText('q', '', 255),
            $r->queryText('district'),
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
            $original = '/offer-detail.html?id=' . rawurlencode($context->request->queryText('id'));
        }
        return new Response(301, [], [], ['Location' => $this->detailUrls->resolve(DetailUrlType::Offer, $original)]);
    }
}
