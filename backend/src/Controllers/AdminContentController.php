<?php

declare(strict_types=1);

namespace Yinghua\Controllers;

use Yinghua\DTO\{AdminContentQuery, ContentRecordView};
use Yinghua\Http\{ApiContext, Response};
use Yinghua\Services\AdminContentManagement;

/** 後台消息與優惠共用的 HTTP 介面；兩者各自的差異在注入的 AdminContentManagement。 */
class AdminContentController
{
    public function __construct(private readonly AdminContentManagement $service) {}

    public function listing(ApiContext $context): Response
    {
        $r = $context->request;
        $query = new AdminContentQuery(
            $r->queryText('q'),
            $r->queryText('status'),
            $r->queryText('type'),
            $r->queryText('merchantId'),
            $r->queryText('active'),
            $r->page(),
            $r->pageSize(),
        );
        return new Response(200, $this->service->listing($context->actor(), $query)->toArray());
    }

    public function get(ApiContext $context): Response
    {
        return new Response(200, ['record' => (new ContentRecordView($this->service->get($context->actor(), $context->id())))->toArray()]);
    }


    public function create(ApiContext $context): Response
    {
        $result = $this->service->save($context->actor(), null, $context->request->json(), $context->client);
        return Response::written($result->synchronized, $result->toArray(), created: true);
    }

    public function update(ApiContext $context): Response
    {
        $result = $this->service->save($context->actor(), $context->id(), $context->request->json(), $context->client);
        return Response::written($result->synchronized, $result->toArray());
    }

    public function delete(ApiContext $context): Response
    {
        $result = $this->service->delete($context->actor(), $context->id(), $context->request->versionOnly(), $context->client);
        return Response::written($result->synchronized, $result->toArray());
    }

}
