<?php

declare(strict_types=1);

namespace Yinghua\Controllers;

use Yinghua\DTO\SeoSettingsInput;
use Yinghua\Http\ApiContext;
use Yinghua\Http\Response;
use Yinghua\Policies\SeoTargetPolicy;
use Yinghua\Services\SeoSettings;

class SeoSettingsController
{
    public function __construct(private readonly SeoSettings $service, private readonly SeoTargetPolicy $targets) {}

    public function read(ApiContext $context): Response
    {
        return new Response(200, $this->service->read($context->actor(), $context->request->queryText('q', '', 80))->toArray());
    }

    public function save(ApiContext $context): Response
    {
        return new Response(
            200,
            $this->service->save(
                $context->actor(),
                SeoSettingsInput::fromArray($context->request->json(), $this->targets),
                $context->client,
            )->toArray(),
        );
    }
}
