<?php

declare(strict_types=1);

namespace Yinghua\Controllers;

use Yinghua\Exceptions\ApplicationError;
use Yinghua\Exceptions\PublicationPending;
use Yinghua\Http\ApiContext;
use Yinghua\Http\Response;
use Yinghua\Services\ContentPublication;
use Yinghua\Services\PublicAttractions;

class PublicAttractionController
{
    public function __construct(private readonly PublicAttractions $service, private readonly ContentPublication $publication) {}

    public function listing(ApiContext $context): Response
    {
        $r = $context->request;

        return new Response(200, $this->service->listing(
            $r->queryText('q'),
            $r->queryText('category'),
            $r->queryText('district'),
            $r->queryInteger('page', 1, 100000),
            $r->queryInteger('pageSize', 12, 100),
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
        $snapshot = $this->publication->read();
        $original = $context->request->headers['x-original-uri'] ?? '';
        $path = parse_url($original, PHP_URL_PATH);
        $aliases = $snapshot['aliases'] ?? [];
        $target = is_string($path) && is_array($aliases) ? ($aliases[$path] ?? null) : null;
        if ($path === '/place-detail.html') {
            $queryString = parse_url($original, PHP_URL_QUERY);
            parse_str(is_string($queryString) ? $queryString : '', $query);
            $id = $query['id'] ?? null;
            $items = $snapshot['items'] ?? [];
            if (is_string($id) && is_array($items)) {
                foreach ($items as $item) {
                    $legacy = is_array($item) ? ($item['legacyId'] ?? null) : null;
                    if (is_array($item) && (($item['id'] ?? null) === $id || (is_int($legacy) && (string) $legacy === $id))) {
                        $target = $item['url'] ?? null;
                        break;
                    }
                }
            }
        }
        if (!is_string($target) || $target === $path || !preg_match('~^/(?:museum\.html|places/[a-z0-9-]+\.html)$~D', $target)) {
            throw new ApplicationError('NOT_FOUND', '找不到此景點網址。');
        }

        return new Response(301, [], [], ['Location' => $target]);
    }
}
