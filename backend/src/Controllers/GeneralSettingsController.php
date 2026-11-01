<?php

declare(strict_types=1);

namespace Yinghua\Controllers;

use Yinghua\DTO\GeneralSettingsInput;
use Yinghua\Http\ApiContext;
use Yinghua\Http\Response;
use Yinghua\Services\GeneralSiteSettings;

class GeneralSettingsController
{
    public function __construct(private readonly GeneralSiteSettings $service) {}

    public function read(ApiContext $context): Response
    {
        return new Response(200, $this->service->read($context->actor()));
    }

    public function save(ApiContext $context): Response
    {
        return new Response(200, $this->service->save($context->actor(), GeneralSettingsInput::fromArray($context->request->json()), $context->client));
    }
}
