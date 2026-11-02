<?php

declare(strict_types=1);

namespace Yinghua\Controllers;

use Yinghua\DTO\Input;
use Yinghua\Http\ApiContext;
use Yinghua\Http\Response;
use Yinghua\Policies\AttractionContentPolicy;
use Yinghua\Services\AttractionSummary;
use Yinghua\Services\Authorization;

class AttractionContentController
{
    public function __construct(private readonly AttractionSummary $summaries, private readonly Authorization $authorization) {}

    public function summary(ApiContext $context): Response
    {
        $input = new Input($context->request->query);
        $input->only([]);
        return new Response(200, $this->summaries->get($context->parameters['slug'] ?? '', $context->client)->toArray());
    }

    public function preview(ApiContext $context): Response
    {
        $this->authorization->requireAny($context->actor(), 'attractions', ['create', 'edit']);
        $input = new Input($context->request->json());
        $input->only(['summary', 'description', 'linked']);
        $content = AttractionContentPolicy::select(
            $input->optionalText('summary', 500),
            $input->optionalText('description', 20000),
            $input->boolean('linked'),
        );
        return new Response(200, ['summary' => $content->summary, 'description' => $content->description,
            'contentSource' => $content->sources()]);
    }
}
