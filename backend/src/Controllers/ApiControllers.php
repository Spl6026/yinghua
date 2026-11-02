<?php

declare(strict_types=1);

namespace Yinghua\Controllers;

/**
 * Composition 組裝好的所有 API Controller，一次交給 config/routes.php 登記路由。
 * 這裡只負責傳遞，不建立物件也不含邏輯；同一個 Controller 實例會被多條路由共用。
 */
readonly class ApiControllers
{
    public function __construct(
        public AuthController $auth,
        public AdminController $admins,
        public PermissionController $permissions,
        public AttractionController $attractions,
        public AttractionContentController $attractionContent,
        public PublicAttractionController $publicAttractions,
        public MediaController $media,
        public MapController $maps,
        public PublicFilmController $films,
        public FilmController $filmAdmin,
        public PublicNewsController $news,
        public PublicOfferController $offers,
        public PublicHomeController $home,
        public AdminNewsController $newsAdmin,
        public AdminOfferController $offerAdmin,
        public OfferMerchantController $merchants,
        public RecommendedRouteController $recommendedRoutes,
        public PublicNeighborController $neighbors,
        public FavoriteController $favorites,
        public CurrentItineraryController $currentItinerary,
        public MemberItineraryController $itineraries,
        public SeoSettingsController $seo,
        public PublicSiteSettingsController $siteSettings,
        public SearchController $search,
        public MemberDirectoryController $members,
        public GeneralSettingsController $settings,
        public OperationsController $operations,
    ) {}
}
