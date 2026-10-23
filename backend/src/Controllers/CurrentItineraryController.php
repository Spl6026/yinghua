<?php

declare(strict_types=1);

namespace Yinghua\Controllers;

use Yinghua\DTO\FavoriteStatusInput;
use Yinghua\DTO\ItineraryItemInput;
use Yinghua\DTO\MemberPageInput;
use Yinghua\Http\ApiContext;
use Yinghua\Http\Response;
use Yinghua\Services\CurrentItinerary;

class CurrentItineraryController
{
    public function __construct(private readonly CurrentItinerary $service) {}

    public function listing(ApiContext $context): Response
    {
        return new Response(200, $this->service->listing($context->actor(), MemberPageInput::fromArray($context->request->query, 50))->toArray());
    }

    public function add(ApiContext $context): Response
    {
        $result = $this->service->add($context->actor(), FavoriteStatusInput::id($context->parameters['attractionId'] ?? ''), ItineraryItemInput::fromArray($context->request->json()));

        return new Response($result->changed ? 201 : 200, $result->toArray());
    }

    public function remove(ApiContext $context): Response
    {
        $result = $this->service->remove($context->actor(), FavoriteStatusInput::id($context->parameters['attractionId'] ?? ''), ItineraryItemInput::fromArray($context->request->json()));

        return new Response(200, $result->toArray());
    }
}
