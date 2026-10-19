<?php

declare(strict_types=1);

namespace Yinghua\Controllers;

use Yinghua\DTO\FilmInput;
use Yinghua\DTO\Input;
use Yinghua\Http\ApiContext;
use Yinghua\Http\Response;
use Yinghua\Services\FilmManagement;

class FilmController
{
    public function __construct(private readonly FilmManagement $service) {}

    public function listing(ApiContext $context): Response
    {
        $request = $context->request;
        return new Response(200, $this->service->listing($context->actor(), $request->queryText('q'), $request->queryText('status'), $request->queryInteger('page', 1, 100000), $request->queryInteger('pageSize', 20, 100))->toArray());
    }

    public function get(ApiContext $context): Response
    {
        return new Response(200, ['film' => $this->service->get($context->actor(), $context->id())->toArray()]);
    }

    public function create(ApiContext $context): Response
    {
        $result = $this->service->save($context->actor(), null, FilmInput::fromArray($context->request->json(), true), $context->client);
        return new Response($result->synchronized ? 201 : 202, $result->toArray());
    }

    public function update(ApiContext $context): Response
    {
        $result = $this->service->save($context->actor(), $context->id(), FilmInput::fromArray($context->request->json(), false), $context->client);
        return new Response($result->synchronized ? 200 : 202, $result->toArray());
    }

    public function delete(ApiContext $context): Response
    {
        $input = new Input($context->request->json());
        $input->only(['version']);
        $result = $this->service->delete($context->actor(), $context->id(), $input->positiveInt('version'), $context->client);
        return new Response($result->synchronized ? 200 : 202, $result->toArray());
    }

    public function retry(ApiContext $context): Response
    {
        $success = $this->service->retry($context->actor());
        return new Response($success ? 200 : 503, ['publication' => ['status' => $success ? 'succeeded' : 'failed'], 'message' => $success ? '公開頁面已同步。' : '發布仍未完成，請稍後重試。']);
    }
}
