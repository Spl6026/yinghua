# 階段十二：結構化資料、爬蟲索引與P1整合

2026-10-31，使用者授權規劃及實作；分支`phase/12-seo-indexing`從階段十一合併後main建立。U-21：正式網域尚未決定，先完成本機；Search Console／Bing／分享平台正式驗證保留上線交接，不以localhost冒稱通過外部平台。

## 規格與範圍

| 原始Excel來源 | 本次設計 |
| --- | --- |
| 功能規格E31:G31/L31；SEO規格B13:F13 | 已發布HTML直接輸出合法、安全JSON-LD；WebSite／Organization及適用內容型別，只使用可見真實資料，無JS也可讀。 |
| 功能規格E33:G33/L33；SEO規格B14:F14 | robots排除admin，sitemap只列已發布可索引且canonical自指的200頁；發布、改slug、下架、SEO noindex變動自動更新。 |
| API草案C40:G42（D） | 根目錄純文字robots、XML sitemap／可選分片，沿用伺服器產頁；不是新增PHP HTML View。 |
| SEO規格B7:F11/B15:F17 | 沿用保護頁noindex、正式canonical、OG／Twitter及驗證token設定；無正式平台帳號不記成功。 |
| 功能規格J8/J10/J12/J14/J15/J22/J23/J24/J29/J31/J33 | 全部11列P1及其P0依賴，逐項記本機／Browser／正式交接的實際結果。 |

原型website只提供基礎WebSite JSON-LD與robots，不改動原型或新增可見改版。SEO欄位選填、發布限制沿既有Excel校正；不為Rich Results自行加作者、價格、評分、活動地址／結束時間必填，不補造不存在的資料。

## 12A：單一JSON-LD擁有者

前端純資料factory依頁面型別產graph，由既有seo-head統一移除舊原型JSON-LD後輸出一份。固定build只產安全shell，不把靜態示範內容當真實資料標記；正式公開graph由成功發布快照生成。私人、管理、noindex、未發布佔位頁不標記。

全站WebSite／Organization／WebPage；景點TouristAttraction，館舍Museum，美食與在地店家LocalBusiness；電影Movie；消息Article，活動資料足夠時Event、缺少開始或場地則Article；優惠Offer連結真實店家。用真正文摘要／海報／地址／發布日期，不以SEO覆寫或OG預設圖冒充正文、作者或評分。Event沒有結構化地址時不聲稱Google活動Rich Result資格；Offer沒有價格不造Product／price。

JSON序列化處理`<`、`>`、`&`及Unicode分隔符，抵抗script終止注入。圖片只使用同公開投影中可讀圖片，不能回填後台媒體或Google參考簡介。

## 12B：同版索引檔案

Publisher實際產生頁面時，使用同一resolveSeo決定canonical／robots，再收集可索引URL；不掃描dist或舊generation當來源。排除保護頁、API、查詢搜尋、草稿、下架、舊slug別名、內部_content_pages及未發布館舍佔位頁；列表合法分頁使用公開canonical query，不暴露物理檔案路徑。

每個generation包含robots、sitemap及必要分片，全部寫完才寫snapshot、原子切換current。Nginx沿publication gate供應；失敗／下架不同版不回舊索引。以已設定canonicalOrigin或APP_URL決定網址，不讀請求Host。正式HTTPS仍由既有SEO設定驗證；localhost是本機替代，不擅填正式域。

E-34：單檔最多50,000 URL及50MiB未壓縮XML，超量按有序URL分片；不得截斷。XML編碼與JSON獨立。lastmod只在有可信修改時間的內容詳情提供，取內容／已發布SEO設定實際修改時間，不用每次建置當更新時間；固定／集合頁無可靠時間則省略。changefreq／priority不捏造，非業務必填。

新增HTTP資源：`GET /robots.txt`、`GET /sitemap.xml`、必要時`GET /sitemap-{part}.xml`；既有104個PHP JSON API保持。OpenAPI列107個HTTP操作（104 API＋3爬蟲資源）；不把Nginx XML／文字端點記成PHP API。原68固定HTML、33表327欄19migration、.env／容器／依賴不新增。

PHP僅補renderer依賴指紋及公開SEO修改時間；Controller／Service／Repository分層保持。Node模組責任分為schema factory、索引純函式、既有publisher寫入／啟用；不引入通用模板框架或把SEO業務塞進Nginx。

## 12C：驗證與交付

真renderer測合法JSON、型別與正文一致、XSS、私頁／noindex／佔位、正式origin、XML query編碼、分片及失敗不啟用。真MariaDB／HTTP驗發布→修改→改slug301→下架404及索引同步、SEO noindex排除、錯誤發布503、匿名圖片、每個URL 200與canonical自指；用命名yinghua_test fixture並finally清理。

重跑PHP／PHPStan／PHPat、限定PER-CS、Rector只dry-run；全前端、P1消息／優惠／前後篇、路線及完整旅程與P0相關回歸。Browser驗代表頁的實際head、內容、路線與行程互動，沿用有效共用AA基線，不啟動主機NVDA。正式平台及第二實體裝置不冒稱實測。

按12A結構化資料、12B同版爬蟲索引、12C P1整合分功能提交，包含相應測試／契約／閱讀指南。每步更新PROJECT-GUIDE，不把計畫記成完成；自身修正歸回功能。最後備份、核對原始69檔／完整tree／舊API契約，merge commit回main並保留階段分支；P2階段十三不提前實作。

## 參考依據

設計沿用[Google結構化資料政策](https://developers.google.com/search/docs/appearance/structured-data/sd-policies)、[Article](https://developers.google.com/search/docs/appearance/structured-data/article)、[Event](https://developers.google.com/search/docs/appearance/structured-data/event)、[sitemap建立規範](https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap)與[robots用途](https://developers.google.com/search/docs/crawling-indexing/robots/intro)，以及[Schema.org Movie](https://schema.org/Movie)、[Offer](https://schema.org/Offer)等正式詞彙。遵循「與可見內容相符」「列canonical URL」「不可靠lastmod不填」；合法Schema.org不等於取得Google特定Rich Result或保證收錄。
