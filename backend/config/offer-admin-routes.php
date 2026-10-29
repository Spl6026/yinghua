<?php

declare(strict_types=1);

use Yinghua\Http\{Router, Route};
use Yinghua\Models\IdentityKind;
use Yinghua\Controllers\{AdminOfferController, OfferMerchantController};

return static function (Router $router, AdminOfferController $offers, OfferMerchantController $merchants): void {
    $kind = IdentityKind::Admin;
    $router->add(new Route('GET', '/api/v1/admin/offers/merchants', $kind, $merchants->listing(...), true));
    $router->add(new Route('POST', '/api/v1/admin/offers/merchants', $kind, $merchants->create(...), true, 'offers', 'create'));
    $router->add(new Route('PUT', '/api/v1/admin/offers/merchants/{id}', $kind, $merchants->update(...), true, 'offers', 'edit'));
    $router->add(new Route('GET', '/api/v1/admin/offers', $kind, $offers->listing(...), true, 'offers', 'view'));
    $router->add(new Route('POST', '/api/v1/admin/offers', $kind, $offers->create(...), true, 'offers', 'create'));
    $router->add(new Route('GET', '/api/v1/admin/offers/{id}', $kind, $offers->get(...), true));
    $router->add(new Route('PUT', '/api/v1/admin/offers/{id}', $kind, $offers->update(...), true, 'offers', 'edit'));
    $router->add(new Route('DELETE', '/api/v1/admin/offers/{id}', $kind, $offers->delete(...), true, 'offers', 'delete'));
};
