<?php

declare(strict_types=1);

namespace Yinghua\Controllers;

use Yinghua\Http\ApiContext;
use Yinghua\Http\Response;
use Yinghua\Services\OperationsOverview;

class OperationsController
{
    public function __construct(private readonly OperationsOverview $service) {}

    public function read(ApiContext $context): Response
    {
        $data = $this->service->read($context->actor());
        return new Response(200, [...$data, 'counts' => (object) $data['counts']]);
    }

    public function audits(ApiContext $context): Response
    {
        return new Response(200, $this->service->audits($context->actor(), $context->request->queryInteger('page', 1, 100000)));
    }
}
