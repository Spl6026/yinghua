<?php

declare(strict_types=1);

namespace Yinghua\Controllers;

use Yinghua\DTO\SearchQuery;
use Yinghua\Http\{ApiContext, Response};
use Yinghua\Services\PublicSearch;

class SearchController
{
    public function __construct(private readonly PublicSearch $service) {}

    public function listing(ApiContext $context): Response
    {
        $r = $context->request;
        try {
            $q = trim($r->queryText('q', '', 255));
        } catch (\Yinghua\Exceptions\ApplicationError) {
            throw \Yinghua\Exceptions\ApplicationError::invalid('q', '搜尋字詞必須是文字，最多255字。');
        }
        return new Response(
            200,
            $this->service->listing(new SearchQuery(
                $q,
                $r->queryText('type'),
                $r->queryInteger('page', 1, 100000),
                $r->queryInteger('pageSize', 20, 100),
            )),
        );
    }
}
