<?php

declare(strict_types=1);

namespace Yinghua\Models;

/**
 * 四種公開內容（景點、電影、消息、優惠）各自不同的規則：快照鍵、網址、標題欄位、分組與搜尋分類。
 * 網址轉向、上一篇／下一篇與搜尋索引的流程都只有一份，差異集中寫在這裡（Strategy），
 * 新增內容類型時補上這裡的 case，各流程以 cases() 逐一處理，不必再複製字串清單。
 */
enum PublicContentType: string
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

    /** 發布快照中「先前的網址代稱 → 目前網址」對照表的鍵。 */
    public function aliasKey(): string
    {
        return match ($this) {
            self::Attraction => 'aliases',
            self::Film => 'filmAliases',
            self::News => 'newsAliases',
            self::Offer => 'offerAliases',
        };
    }

    /** 原型以 ?id= 參數開啟詳情的共用頁面；規格列出這些頁面，所以仍要能轉到正式詳情頁。 */
    public function idDetailPage(): string
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
     * 把以編號開啟的詳情頁 ?id= 值換成要和 prototypeId 比對的原型編號。
     * 原型的優惠頁用 0、1、2 當索引，對應到匯入時保留的 101–103，原型的連結才能轉到正確的優惠。
     */
    public function prototypeId(string $id): string
    {
        if ($this === self::Offer) {
            return ['0' => '101','1' => '102','2' => '103'][$id] ?? $id;
        }

        return $id;
    }

    /**
     * 少數原型網址放在別的內容類型底下，要先查那一類的對照表。
     * /news/coffee-walk.html 在原型裡其實是優惠頁，所以轉到對應的優惠，而不是硬當成城市消息文章。
     */
    public function movedTo(string $path): ?self
    {
        return $this === self::News && $path === '/news/coffee-walk.html' ? self::Offer : null;
    }

    /** API 路徑與公開詳情頁使用的目錄名稱。 */
    public function pathSegment(): string
    {
        return match ($this) {
            self::Attraction => 'places',
            self::Film => 'films',
            self::News => 'news',
            self::Offer => 'offers',
        };
    }

    /**
     * 依代稱算出的正式公開網址；博物館是唯一的固定頁面。
     *
     * @param array<string,mixed> $item
     */
    public function canonicalUrl(array $item): string
    {
        if ($this === self::Attraction && ($item['isMuseum'] ?? false) === true) {
            return '/museum.html';
        }
        $slug = is_string($item['slug'] ?? null) ? $item['slug'] : '';

        return '/' . $this->pathSegment() . '/' . $slug . '.html';
    }

    /**
     * 快照中項目的公開網址。景點依代稱計算；其他內容使用發布時寫入的網址，缺少時回傳 null。
     *
     * @param array<string,mixed> $item
     */
    public function publishedUrl(array $item): ?string
    {
        if ($this === self::Attraction) {
            return $this->canonicalUrl($item);
        }

        return is_string($item['url'] ?? null) ? $item['url'] : null;
    }

    /** 標題欄位：景點用名稱，其他內容用標題。 */
    public function titleKey(): string
    {
        return $this === self::Attraction ? 'name' : 'title';
    }

    /** 上一篇／下一篇只在同一組內切換：景點依分類、消息依類型，電影與優惠不分組。 */
    public function neighborGroupKey(): ?string
    {
        return match ($this) {
            self::Attraction => 'category',
            self::News => 'type',
            self::Film, self::Offer => null,
        };
    }

    /**
     * 搜尋結果的分組；美食是從景點中依分類另外分出的一組。
     *
     * @param array<string,mixed> $item
     */
    public function searchType(array $item): SearchType
    {
        return match ($this) {
            self::Attraction => ($item['category'] ?? '') === '美食' ? SearchType::Food : SearchType::Place,
            self::Film => SearchType::Film,
            self::News => SearchType::News,
            self::Offer => SearchType::Offer,
        };
    }
}
