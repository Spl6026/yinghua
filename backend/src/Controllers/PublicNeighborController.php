<?php

declare(strict_types=1);

namespace Yinghua\Controllers;

use Yinghua\Http\{ApiContext, Response};
use Yinghua\Services\PublicNeighbors;

class PublicNeighborController
{
    public function __construct(private readonly PublicNeighbors $service) {}

    public function attractions(ApiContext $context): Response
    {
        return $this->get($context, 'items');
    }

    public function films(ApiContext $context): Response
    {
        return $this->get($context, 'films');
    }

    public function news(ApiContext $context): Response
    {
        return $this->get($context, 'news');
    }

    public function offers(ApiContext $context): Response
    {
        return $this->get($context, 'offers');
    }

    private function get(ApiContext $context, string $topic): Response
    {
        return new Response(200, $this->service->get($topic, $context->parameters['slug'] ?? ''));
    }
}
