<?php

declare(strict_types=1);

namespace Yinghua\Controllers;

use Yinghua\Exceptions\ApplicationError;
use Yinghua\Exceptions\PublicationPending;
use Yinghua\Http\ApiContext;
use Yinghua\Http\Response;
use Yinghua\Models\PublicContentType;
use Yinghua\Services\ContentPublication;
use Yinghua\Services\DetailUrlResolver;
use Yinghua\Services\PublicAttractions;

class PublicAttractionController
{
    public function __construct(
        private readonly PublicAttractions $service,
        private readonly ContentPublication $publication,
        private readonly DetailUrlResolver $detailUrls,
    ) {}

    public function listing(ApiContext $context): Response
    {
        $r = $context->request;

        return new Response(200, $this->service->listing(
            $r->queryText('q'),
            $r->queryText('category'),
            $r->queryText('district'),
            $r->page(),
            $r->pageSize(12),
        ));
    }

    public function get(ApiContext $context): Response
    {
        return new Response(200, $this->service->get($context->parameters['slug'] ?? ''));
    }

    public function museum(ApiContext $context): Response
    {
        return new Response(200, $this->service->museum());
    }

    /** Nginx 的內部子請求只判斷發布版本，不輸出 HTML。 */
    public function access(ApiContext $context): Response
    {
        try {
            $this->publication->read();

            return new Response(204);
        } catch (ApplicationError $error) {
            if ($error instanceof PublicationPending) {
                error_log('request=' . $context->request->requestId . ' ' . $error->diagnostic());
            }
            return new Response(403);
        }
    }

    public function resolve(ApiContext $context): Response
    {
        $target = $this->detailUrls->resolve(PublicContentType::Attraction, $context->request->headers['x-original-uri'] ?? '');

        return new Response(301, [], [], ['Location' => $target]);
    }
}
