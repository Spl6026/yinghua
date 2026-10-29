<?php

declare(strict_types=1);

use Yinghua\Http\{Router, Route};
use Yinghua\Models\IdentityKind;
use Yinghua\Controllers\AdminNewsController;

return static function (Router $router, AdminNewsController $news): void {
    $kind = IdentityKind::Admin;
    $router->add(new Route('GET', '/api/v1/admin/news', $kind, $news->listing(...), true, 'news', 'view'));
    $router->add(new Route('POST', '/api/v1/admin/news', $kind, $news->create(...), true, 'news', 'create'));
    $router->add(new Route('GET', '/api/v1/admin/news/{id}', $kind, $news->get(...), true));
    $router->add(new Route('PUT', '/api/v1/admin/news/{id}', $kind, $news->update(...), true, 'news', 'edit'));
    $router->add(new Route('DELETE', '/api/v1/admin/news/{id}', $kind, $news->delete(...), true, 'news', 'delete'));
};
