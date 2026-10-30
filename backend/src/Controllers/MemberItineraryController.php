<?php

declare(strict_types=1);

namespace Yinghua\Controllers;

use Yinghua\DTO\{Input, TripInput};
use Yinghua\Http\{ApiContext, Response};
use Yinghua\Services\MemberItineraries;

class MemberItineraryController
{
    public function __construct(private readonly MemberItineraries $service) {}

    public function listing(ApiContext $context): Response
    {
        return new Response(200, $this->service->listing($context->actor(), $context->request->queryInteger('page', 1, 100000), $context->request->queryInteger('pageSize', 20, 100)));
    }

    public function get(ApiContext $context): Response
    {
        return new Response(200, ['itinerary' => $this->service->get($context->actor(), $context->id())->toArray()]);
    }

    public function create(ApiContext $context): Response
    {
        $result = $this->service->create($context->actor(), TripInput::from($context->request->json(), true));
        return new Response($result->created ? 201 : 200, ['itinerary' => $result->itinerary->toArray()]);
    }

    public function update(ApiContext $context): Response
    {
        return new Response(200, ['itinerary' => $this->service->save($context->actor(), $context->id(), TripInput::from($context->request->json(), false))->toArray()]);
    }

    public function select(ApiContext $context): Response
    {
        $input = new Input($context->request->json());
        $input->only(['version']);
        return new Response(200, ['itinerary' => $this->service->select($context->actor(), $context->id(), $input->positiveInt('version'))->toArray()]);
    }

    public function delete(ApiContext $context): Response
    {
        $input = new Input($context->request->json());
        $input->only(['version']);
        $this->service->delete($context->actor(), $context->id(), $input->positiveInt('version'));
        return new Response(204);
    }

    public function apply(ApiContext $context): Response
    {
        $values = $context->request->json();
        $input = new Input($values);
        $input->only(['version','routeId']);
        return new Response(200, ['itinerary' => $this->service->apply($context->actor(), $context->id(), $input->text('routeId', 36, 36), $input->positiveInt('version'))->toArray()]);
    }
}
