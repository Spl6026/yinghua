<?php

declare(strict_types=1);

namespace Yinghua\Controllers;

use Yinghua\Http\ApiContext;
use Yinghua\Http\Response;
use Yinghua\Services\PublicHome;

class PublicHomeController
{
    public function __construct(private readonly PublicHome $service) {}

    public function get(ApiContext $context): Response
    {
        return new Response(200, $this->service->get());
    }
}
