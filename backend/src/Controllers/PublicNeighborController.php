<?php

declare(strict_types=1);

namespace Yinghua\Controllers;

use Yinghua\Http\{ApiContext, Response};
use Yinghua\Models\PublicContentType;
use Yinghua\Services\PublicNeighbors;

class PublicNeighborController
{
    public function __construct(private readonly PublicNeighbors $service) {}

    public function attractions(ApiContext $context): Response
    {
        return $this->get($context, PublicContentType::Attraction);
    }

    public function films(ApiContext $context): Response
    {
        return $this->get($context, PublicContentType::Film);
    }

    public function news(ApiContext $context): Response
    {
        return $this->get($context, PublicContentType::News);
    }

    public function offers(ApiContext $context): Response
    {
        return $this->get($context, PublicContentType::Offer);
    }

    private function get(ApiContext $context, PublicContentType $type): Response
    {
        return new Response(200, $this->service->get($type, $context->parameters['slug'] ?? ''));
    }
}
