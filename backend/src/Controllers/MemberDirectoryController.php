<?php

declare(strict_types=1);

namespace Yinghua\Controllers;

use Yinghua\Http\ApiContext;
use Yinghua\Http\Response;
use Yinghua\Services\MemberDirectory;

class MemberDirectoryController
{
    public function __construct(private readonly MemberDirectory $service) {}

    public function listing(ApiContext $context): Response
    {
        return new Response(
            200,
            $this->service->listing(
                $context->actor(),
                $context->request->queryText('q', '', 80),
                $context->request->queryInteger('page', 1, 100000),
            ),
        );
    }
}
