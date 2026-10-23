<?php

declare(strict_types=1);

use Yinghua\Controllers\SeoSettingsController;
use Yinghua\Controllers\PublicSiteSettingsController;
use Yinghua\Http\Route;
use Yinghua\Http\Router;
use Yinghua\Models\IdentityKind;

/** [S：H32；D：API C39；E-28] 共用管理授權／唯讀模擬管線。 */
return static function (Router $router, SeoSettingsController $seo, PublicSiteSettingsController $public): void {
    $router->add(new Route('GET', '/api/v1/admin/settings/seo', IdentityKind::Admin, $seo->read(...), true, 'settings', 'view'));
    $router->add(new Route('PUT', '/api/v1/admin/settings/seo', IdentityKind::Admin, $seo->save(...), true, 'settings', 'edit'));
    $router->add(new Route('GET', '/api/v1/site-settings/public', IdentityKind::Member, $public->get(...)));
};
