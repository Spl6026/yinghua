<?php

declare(strict_types=1);

use Yinghua\Controllers\ApiControllers;
use Yinghua\Http\Route;
use Yinghua\Http\Router;
use Yinghua\Models\IdentityKind;

/**
 * 全站唯一的 API 路由表：只宣告 HTTP 方法、路徑、身分類型與所需權限，業務邏輯都在 Controller 之後。
 * Router 採「第一個符合者勝出」，所以順序本身就是規則：固定路徑（resolve-url、merchants、samples、current 等）
 * 必須寫在同一前綴的 {slug}／{id} 之前，否則會被泛用參數吃掉。調整順序前先確認沒有路徑因此被攔走。
 */
return static function (Router $router, ApiControllers $c): void {
    $admin = IdentityKind::Admin;
    $member = IdentityKind::Member;

    // 景點介紹的摘要補位：預覽給後台編輯用，Google 原文即時取得、不另存資料表。
    $router->add(new Route('POST', '/api/v1/admin/attractions/content-preview', $admin, $c->attractionContent->preview(...), true));
    $router->add(new Route('GET', '/api/v1/attractions/{slug}/google-summary', $member, $c->attractionContent->summary(...)));

    // 會員與管理員各有一組登入相關路徑；退出模擬、登出與查詢自己不受唯讀模擬限制。
    foreach (IdentityKind::cases() as $kind) {
        $prefix = $kind === IdentityKind::Admin ? '/api/v1/admin/auth/' : '/api/v1/auth/';
        $router->add(new Route('GET', $prefix . 'csrf', $kind, $c->auth->csrf(...), allowPreviewControl: true));
        $router->add(new Route('POST', $prefix . 'login', $kind, $c->auth->login(...)));
        $router->add(new Route('POST', $prefix . 'logout', $kind, $c->auth->logout(...), true, allowPreviewControl: true));
        $router->add(new Route('GET', $prefix . 'me', $kind, $c->auth->me(...), true, allowPreviewControl: true));
        $router->add(new Route('POST', $prefix . 'forgot-password', $kind, $c->auth->forgotPassword(...)));
        $router->add(new Route('POST', $prefix . 'reset-password', $kind, $c->auth->resetPassword(...)));
        $router->add(new Route('POST', $prefix . 'change-password', $kind, $c->auth->changePassword(...), true));
    }
    $router->add(new Route('GET', '/api/v1/auth/captcha', $member, $c->auth->captcha(...)));
    $router->add(new Route('POST', '/api/v1/auth/captcha/email', $member, $c->auth->emailCaptcha(...)));
    $router->add(new Route('POST', '/api/v1/auth/register', $member, $c->auth->register(...)));
    $router->add(new Route('GET', '/api/v1/admin/auth/preview', $admin, $c->auth->previewTargets(...), true, allowPreviewControl: true));
    $router->add(new Route('POST', '/api/v1/admin/auth/preview', $admin, $c->auth->startPreview(...), true, allowPreviewControl: true));
    $router->add(new Route('DELETE', '/api/v1/admin/auth/preview', $admin, $c->auth->stopPreview(...), true, allowPreviewControl: true));

    // 管理員帳號與權限。
    $router->add(new Route('GET', '/api/v1/admin/admins', $admin, $c->admins->listing(...), true, 'admins', 'view'));
    $router->add(new Route('GET', '/api/v1/admin/admins/{id}', $admin, $c->admins->get(...), true, 'admins', 'view'));
    $router->add(new Route('POST', '/api/v1/admin/admins', $admin, $c->admins->create(...), true, 'admins', 'create'));
    $router->add(new Route('PUT', '/api/v1/admin/admins/{id}', $admin, $c->admins->update(...), true, 'admins', 'edit'));
    $router->add(new Route('DELETE', '/api/v1/admin/admins/{id}', $admin, $c->admins->delete(...), true, 'admins', 'delete'));
    $router->add(new Route('GET', '/api/v1/admin/roles', $admin, $c->permissions->roles(...), true));
    $router->add(new Route('GET', '/api/v1/admin/permissions/catalog', $admin, $c->permissions->catalog(...), true, 'permissions', 'view'));
    $router->add(new Route('GET', '/api/v1/admin/admins/{id}/permissions', $admin, $c->permissions->matrix(...), true, 'permissions', 'view'));
    $router->add(new Route('PUT', '/api/v1/admin/admins/{id}/permissions', $admin, $c->permissions->saveMatrix(...), true, 'permissions', 'edit'));

    // 景點與電影管理；samples 與 publish-retry 是固定路徑，要排在 {id} 之前。
    $router->add(new Route('GET', '/api/v1/admin/attractions', $admin, $c->attractions->listing(...), true, 'attractions', 'view'));
    $router->add(new Route('GET', '/api/v1/admin/attractions/samples', $admin, $c->attractions->samples(...), true, 'attractions', 'create'));
    $router->add(new Route('GET', '/api/v1/admin/attractions/{id}', $admin, $c->attractions->get(...), true, 'attractions', 'view'));
    $router->add(new Route('POST', '/api/v1/admin/attractions', $admin, $c->attractions->create(...), true, 'attractions', 'create'));
    $router->add(new Route('PUT', '/api/v1/admin/attractions/{id}', $admin, $c->attractions->update(...), true, 'attractions', 'edit'));
    $router->add(new Route('DELETE', '/api/v1/admin/attractions/{id}', $admin, $c->attractions->delete(...), true, 'attractions', 'delete'));
    $router->add(new Route('POST', '/api/v1/admin/attractions/publish-retry', $admin, $c->attractions->retry(...), true, 'attractions', 'edit'));
    $router->add(new Route('GET', '/api/v1/admin/films', $admin, $c->filmAdmin->listing(...), true, 'films', 'view'));
    $router->add(new Route('GET', '/api/v1/admin/films/{id}', $admin, $c->filmAdmin->get(...), true, 'films', 'view'));
    $router->add(new Route('POST', '/api/v1/admin/films', $admin, $c->filmAdmin->create(...), true, 'films', 'create'));
    $router->add(new Route('PUT', '/api/v1/admin/films/{id}', $admin, $c->filmAdmin->update(...), true, 'films', 'edit'));
    $router->add(new Route('DELETE', '/api/v1/admin/films/{id}', $admin, $c->filmAdmin->delete(...), true, 'films', 'delete'));
    $router->add(new Route('POST', '/api/v1/admin/films/publish-retry', $admin, $c->filmAdmin->retry(...), true, 'films', 'edit'));

    // 媒體與地圖；media/access 供 Nginx 內部子請求判斷圖片能否讀取，路由本身不要求 session。
    $router->add(new Route('POST', '/api/v1/admin/media', $admin, $c->media->upload(...), true));
    $router->add(new Route('GET', '/api/v1/admin/media', $admin, $c->media->listing(...), true));
    $router->add(new Route('GET', '/api/v1/admin/media/access', $admin, $c->media->access(...)));
    $router->add(new Route('POST', '/api/v1/admin/places/resolve-map-url', $admin, $c->maps->resolve(...), true));

    // 公開內容；每一類的 resolve-url 都要排在 {slug} 之前，否則詳情頁網址轉向會被當成找不到的 slug。
    $router->add(new Route('GET', '/api/v1/publication/access', $member, $c->publicAttractions->access(...)));
    $router->add(new Route('GET', '/api/v1/films/resolve-url', $member, $c->films->resolve(...)));
    $router->add(new Route('GET', '/api/v1/films', $member, $c->films->listing(...)));
    $router->add(new Route('GET', '/api/v1/films/{slug}', $member, $c->films->get(...)));
    $router->add(new Route('GET', '/api/v1/attractions/resolve-url', $member, $c->publicAttractions->resolve(...)));
    $router->add(new Route('GET', '/api/v1/attractions', $member, $c->publicAttractions->listing(...)));
    $router->add(new Route('GET', '/api/v1/attractions/{slug}', $member, $c->publicAttractions->get(...)));
    $router->add(new Route('GET', '/api/v1/museum', $member, $c->publicAttractions->museum(...)));
    $router->add(new Route('GET', '/api/v1/home', $member, $c->home->get(...)));
    $router->add(new Route('GET', '/api/v1/news/resolve-url', $member, $c->news->resolve(...)));
    $router->add(new Route('GET', '/api/v1/news', $member, $c->news->listing(...)));
    $router->add(new Route('GET', '/api/v1/news/{slug}', $member, $c->news->get(...)));
    $router->add(new Route('GET', '/api/v1/offers/resolve-url', $member, $c->offers->resolve(...)));
    $router->add(new Route('GET', '/api/v1/offers', $member, $c->offers->listing(...)));
    $router->add(new Route('GET', '/api/v1/offers/{slug}', $member, $c->offers->get(...)));

    // 消息管理。
    $router->add(new Route('GET', '/api/v1/admin/news', $admin, $c->newsAdmin->listing(...), true, 'news', 'view'));
    $router->add(new Route('POST', '/api/v1/admin/news', $admin, $c->newsAdmin->create(...), true, 'news', 'create'));
    $router->add(new Route('GET', '/api/v1/admin/news/{id}', $admin, $c->newsAdmin->get(...), true));
    $router->add(new Route('PUT', '/api/v1/admin/news/{id}', $admin, $c->newsAdmin->update(...), true, 'news', 'edit'));
    $router->add(new Route('DELETE', '/api/v1/admin/news/{id}', $admin, $c->newsAdmin->delete(...), true, 'news', 'delete'));

    // 優惠與店家分開管理；merchants 是固定路徑，要排在優惠 {id} 之前。
    $router->add(new Route('GET', '/api/v1/admin/offers/merchants', $admin, $c->merchants->listing(...), true));
    $router->add(new Route('POST', '/api/v1/admin/offers/merchants', $admin, $c->merchants->create(...), true, 'offers', 'create'));
    $router->add(new Route('PUT', '/api/v1/admin/offers/merchants/{id}', $admin, $c->merchants->update(...), true, 'offers', 'edit'));
    $router->add(new Route('GET', '/api/v1/admin/offers', $admin, $c->offerAdmin->listing(...), true, 'offers', 'view'));
    $router->add(new Route('POST', '/api/v1/admin/offers', $admin, $c->offerAdmin->create(...), true, 'offers', 'create'));
    $router->add(new Route('GET', '/api/v1/admin/offers/{id}', $admin, $c->offerAdmin->get(...), true));
    $router->add(new Route('PUT', '/api/v1/admin/offers/{id}', $admin, $c->offerAdmin->update(...), true, 'offers', 'edit'));
    $router->add(new Route('DELETE', '/api/v1/admin/offers/{id}', $admin, $c->offerAdmin->delete(...), true, 'offers', 'delete'));

    // 推薦路線：公開列表與單筆預覽，以及後台管理；attractions 候選清單排在 {id} 之前。
    $router->add(new Route('GET', '/api/v1/routes', $member, $c->recommendedRoutes->published(...)));
    $router->add(new Route('GET', '/api/v1/routes/{id}', $member, $c->recommendedRoutes->publishedOne(...)));
    $router->add(new Route('GET', '/api/v1/admin/routes/attractions', $admin, $c->recommendedRoutes->candidates(...), true));
    $router->add(new Route('GET', '/api/v1/admin/routes', $admin, $c->recommendedRoutes->listing(...), true, 'routes', 'view'));
    $router->add(new Route('POST', '/api/v1/admin/routes', $admin, $c->recommendedRoutes->create(...), true, 'routes', 'create'));
    $router->add(new Route('GET', '/api/v1/admin/routes/{id}', $admin, $c->recommendedRoutes->get(...), true));
    $router->add(new Route('PUT', '/api/v1/admin/routes/{id}', $admin, $c->recommendedRoutes->update(...), true, 'routes', 'edit'));
    $router->add(new Route('DELETE', '/api/v1/admin/routes/{id}', $admin, $c->recommendedRoutes->delete(...), true, 'routes', 'delete'));

    // 四類詳情頁的前後篇。
    foreach (['attractions','films','news','offers'] as $topic) {
        $router->add(new Route('GET', '/api/v1/' . $topic . '/{slug}/neighbors', $member, $c->neighbors->{$topic}(...)));
    }

    // 收藏與目前行程都是會員本人的資料，只認會員 session，不套用管理員的權限設定；status 要排在 {attractionId} 之前。
    $router->add(new Route('GET', '/api/v1/me/favorites', $member, $c->favorites->listing(...), true));
    $router->add(new Route('GET', '/api/v1/me/favorites/status', $member, $c->favorites->status(...), true));
    $router->add(new Route('PUT', '/api/v1/me/favorites/{attractionId}', $member, $c->favorites->add(...), true));
    $router->add(new Route('DELETE', '/api/v1/me/favorites/{attractionId}', $member, $c->favorites->remove(...), true));
    $router->add(new Route('GET', '/api/v1/me/itineraries/current', $member, $c->currentItinerary->listing(...), true));
    $router->add(new Route('PUT', '/api/v1/me/itineraries/current/items/{attractionId}', $member, $c->currentItinerary->add(...), true));
    $router->add(new Route('DELETE', '/api/v1/me/itineraries/current/items/{attractionId}', $member, $c->currentItinerary->remove(...), true));

    // 會員的多份行程；必須排在上面的 current 固定路徑之後，避免泛用 {id} 攔走 current。
    $trips = '/api/v1/me/itineraries';
    $router->add(new Route('GET', $trips, $member, $c->itineraries->listing(...), true));
    $router->add(new Route('POST', $trips, $member, $c->itineraries->create(...), true));
    $router->add(new Route('GET', $trips . '/{id}', $member, $c->itineraries->get(...), true));
    $router->add(new Route('PUT', $trips . '/{id}', $member, $c->itineraries->update(...), true));
    $router->add(new Route('DELETE', $trips . '/{id}', $member, $c->itineraries->delete(...), true));
    $router->add(new Route('PUT', $trips . '/{id}/current', $member, $c->itineraries->select(...), true));
    $router->add(new Route('POST', $trips . '/{id}/routes', $member, $c->itineraries->apply(...), true));

    // SEO 設定沿用一般管理 API 的授權與唯讀模擬檢查。
    $router->add(new Route('GET', '/api/v1/admin/settings/seo', $admin, $c->seo->read(...), true, 'settings', 'view'));
    $router->add(new Route('PUT', '/api/v1/admin/settings/seo', $admin, $c->seo->save(...), true, 'settings', 'edit'));
    $router->add(new Route('GET', '/api/v1/site-settings/public', $member, $c->siteSettings->get(...)));

    // 全站搜尋、會員名錄、一般設定與營運概況。
    $router->add(new Route('GET', '/api/v1/search', $member, $c->search->listing(...)));
    $router->add(new Route('GET', '/api/v1/admin/members', $admin, $c->members->listing(...), true, 'members', 'view'));
    $router->add(new Route('GET', '/api/v1/admin/settings', $admin, $c->settings->read(...), true, 'settings', 'view'));
    $router->add(new Route('PUT', '/api/v1/admin/settings', $admin, $c->settings->save(...), true, 'settings', 'edit'));
    $router->add(new Route('GET', '/api/v1/admin/dashboard', $admin, $c->operations->read(...), true, 'dashboard', 'view'));
    $router->add(new Route('GET', '/api/v1/admin/audit-logs', $admin, $c->operations->audits(...), true, 'dashboard', 'view'));
};
