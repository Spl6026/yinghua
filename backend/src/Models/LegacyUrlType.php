<?php

declare(strict_types=1);

namespace Yinghua\Models;

/**
 * 四種公開內容在舊網址轉向時各自不同的設定。
 * 轉向流程本身只有一份（LegacyUrlResolver），差異集中寫在這裡，新增內容類型時不必再複製整段比對邏輯。
 */
enum LegacyUrlType: string
{
    case Attraction = 'attraction';
    case Film = 'film';
    case News = 'news';
    case Offer = 'offer';

    /** 發布快照中存放已發布項目的鍵。 */
    public function collectionKey(): string
    {
        return match ($this) {
            self::Attraction => 'items',
            self::Film => 'films',
            self::News => 'news',
            self::Offer => 'offers',
        };
    }

    /** 發布快照中「舊路徑 → 目前網址」對照表的鍵。 */
    public function aliasKey(): string
    {
        return match ($this) {
            self::Attraction => 'aliases',
            self::Film => 'filmAliases',
            self::News => 'newsAliases',
            self::Offer => 'offerAliases',
        };
    }

    /** 原型時期以 ?id= 參數顯示詳情的共用頁面。 */
    public function legacyPage(): string
    {
        return match ($this) {
            self::Attraction => '/place-detail.html',
            self::Film => '/film-detail.html',
            self::News => '/news-detail.html',
            self::Offer => '/offer-detail.html',
        };
    }

    /** 轉向目標必須是這類內容自己的公開網址，避免對照表資料異常時把人導到別的地方。 */
    public function canonicalPattern(): string
    {
        return match ($this) {
            self::Attraction => '~^/(?:museum\.html|places/[a-z0-9-]+\.html)$~D',
            self::Film => '~^/films/[a-z0-9-]+\.html$~D',
            self::News => '~^/news/[a-z0-9-]+\.html$~D',
            self::Offer => '~^/offers/[a-z0-9-]+\.html$~D',
        };
    }

    public function notFoundMessage(): string
    {
        return match ($this) {
            self::Attraction => '找不到此景點網址。',
            self::Film => '找不到此電影網址。',
            self::News => '找不到此消息網址。',
            self::Offer => '找不到此優惠網址。',
        };
    }

    /**
     * 把舊頁面 ?id= 的值換成要和 legacyId 比對的編號。
     * 原型的優惠頁用 0、1、2 當索引，對應到匯入時保留的 101–103，舊連結才能轉到正確的優惠。
     */
    public function legacyId(string $id): string
    {
        if ($this === self::Offer) {
            return ['0' => '101','1' => '102','2' => '103'][$id] ?? $id;
        }

        return $id;
    }

    /**
     * 少數舊網址放在別的內容類型底下，要先查那一類的對照表。
     * /news/coffee-walk.html 在原型裡其實是優惠頁，所以轉到對應的優惠，而不是硬當成城市消息文章。
     */
    public function movedTo(string $path): ?self
    {
        return $this === self::News && $path === '/news/coffee-walk.html' ? self::Offer : null;
    }
}
