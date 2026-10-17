<?php

declare(strict_types=1);

use Yinghua\Controllers\AdminController;
use Yinghua\Controllers\AuthController;
use Yinghua\Controllers\PermissionController;
use Yinghua\Controllers\PublicAttractionController;
use Yinghua\Http\Route;
use Yinghua\Http\Router;
use Yinghua\Models\IdentityKind;

/** [S-016／17／26／27；S：功能規格 6/13/19/20；E-18] 路由只宣告 HTTP、身分與授權。 */
return static function (Router $router, AuthController $auth, AdminController $admins, PermissionController $permissions, PublicAttractionController $publicAttractions): void {
    foreach (IdentityKind::cases() as $kind) {
        $prefix = $kind === IdentityKind::Admin ? '/api/v1/admin/auth/' : '/api/v1/auth/';
        $router->add(new Route('GET', $prefix . 'csrf', $kind, $auth->csrf(...), allowPreviewControl: true));
        $router->add(new Route('POST', $prefix . 'login', $kind, $auth->login(...)));
        $router->add(new Route('POST', $prefix . 'logout', $kind, $auth->logout(...), true, allowPasswordChange: true, allowPreviewControl: true));
        $router->add(new Route('GET', $prefix . 'me', $kind, $auth->me(...), true, allowPasswordChange: true, allowPreviewControl: true));
        $router->add(new Route('POST', $prefix . 'forgot-password', $kind, $auth->forgotPassword(...)));
        $router->add(new Route('POST', $prefix . 'reset-password', $kind, $auth->resetPassword(...)));
        $router->add(new Route('POST', $prefix . 'change-password', $kind, $auth->changePassword(...), true, allowPasswordChange: true));
    }
    $router->add(new Route('GET', '/api/v1/auth/captcha', IdentityKind::Member, $auth->captcha(...)));
    $router->add(new Route('POST', '/api/v1/auth/captcha/email', IdentityKind::Member, $auth->emailCaptcha(...)));
    $router->add(new Route('POST', '/api/v1/auth/register', IdentityKind::Member, $auth->register(...)));
    $kind = IdentityKind::Admin;
    $router->add(new Route('GET', '/api/v1/admin/auth/preview', $kind, $auth->previewTargets(...), true, allowPreviewControl: true));
    $router->add(new Route('POST', '/api/v1/admin/auth/preview', $kind, $auth->startPreview(...), true, allowPreviewControl: true));
    $router->add(new Route('DELETE', '/api/v1/admin/auth/preview', $kind, $auth->stopPreview(...), true, allowPreviewControl: true));
    $router->add(new Route('GET', '/api/v1/admin/admins', $kind, $admins->listing(...), true, 'admins', 'view'));
    $router->add(new Route('GET', '/api/v1/admin/admins/{id}', $kind, $admins->get(...), true, 'admins', 'view'));
    $router->add(new Route('POST', '/api/v1/admin/admins', $kind, $admins->create(...), true, 'admins', 'create'));
    $router->add(new Route('PUT', '/api/v1/admin/admins/{id}', $kind, $admins->update(...), true, 'admins', 'edit'));
    $router->add(new Route('DELETE', '/api/v1/admin/admins/{id}', $kind, $admins->delete(...), true, 'admins', 'delete'));
    $router->add(new Route('GET', '/api/v1/admin/roles', $kind, $permissions->roles(...), true));
    $router->add(new Route('GET', '/api/v1/admin/permissions/catalog', $kind, $permissions->catalog(...), true, 'permissions', 'view'));
    $router->add(new Route('GET', '/api/v1/admin/admins/{id}/permissions', $kind, $permissions->matrix(...), true, 'permissions', 'view'));
    $router->add(new Route('PUT', '/api/v1/admin/admins/{id}/permissions', $kind, $permissions->saveMatrix(...), true, 'permissions', 'edit'));
    $kind = IdentityKind::Member;
    $router->add(new Route('GET', '/api/v1/publication/access', $kind, $publicAttractions->access(...)));
    $router->add(new Route('GET', '/api/v1/attractions/resolve-url', $kind, $publicAttractions->resolve(...)));
    $router->add(new Route('GET', '/api/v1/attractions', $kind, $publicAttractions->listing(...)));
    $router->add(new Route('GET', '/api/v1/attractions/{slug}', $kind, $publicAttractions->get(...)));
    $router->add(new Route('GET', '/api/v1/museum', $kind, $publicAttractions->museum(...)));
};
