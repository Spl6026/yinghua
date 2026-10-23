<?php

declare(strict_types=1);

namespace Yinghua\Controllers;

use Yinghua\DTO\FavoriteStatusInput;
use Yinghua\DTO\Input;
use Yinghua\DTO\MemberPageInput;
use Yinghua\Http\ApiContext;
use Yinghua\Http\Response;
use Yinghua\Services\MemberFavorites;

class FavoriteController
{
    public function __construct(private readonly MemberFavorites $service) {}

    public function listing(ApiContext $context): Response
    {
        return new Response(200, $this->service->listing($context->actor(), MemberPageInput::fromArray($context->request->query))->toArray());
    }

    public function status(ApiContext $context): Response
    {
        return new Response(200, ['statuses' => $this->service->status($context->actor(), FavoriteStatusInput::fromArray($context->request->query))]);
    }

    public function add(ApiContext $context): Response
    {
        (new Input($context->request->json()))->only([]);
        $result = $this->service->add($context->actor(), FavoriteStatusInput::id($context->parameters['attractionId'] ?? ''));

        return new Response($result->created ? 201 : 200, $result->toArray());
    }

    public function remove(ApiContext $context): Response
    {
        (new Input($context->request->json()))->only([]);
        $this->service->remove($context->actor(), FavoriteStatusInput::id($context->parameters['attractionId'] ?? ''));

        return new Response(204);
    }
}
