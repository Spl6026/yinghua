<?php

declare(strict_types=1);

namespace Yinghua\Controllers;

use Yinghua\DTO\{AdminContentQuery};
use Yinghua\Http\{ApiContext, Response};
use Yinghua\Services\OfferMerchants;

class OfferMerchantController
{
    public function __construct(private readonly OfferMerchants $service) {}

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

}
