# 結構化資料與爬蟲索引閱讀指南

更新：2026-10-31，階段十二。需求與取捨見[計畫](../PHASE-12-PLAN.md)，12C整合驗收另行交付，JSON-LD可先讀[工廠導讀](structured-data.md)。原始website不變，PHP維持API-only。

## 從一筆發布看架構

管理API保存內容 → 原Service／Repository交易 → `ContentPublication`收集已發布安全DTO → `NodeContentRenderer`傳快照給Node → `publish.mjs`產HTML、JSON-LD、robots、sitemap → 最後寫snapshot並原子切換current。Nginx先跑既有publication gate，再讀current檔案；不同版或發布失敗回503，不能只換XML卻留舊HTML。

`resolveSeo()`是標題、canonical與索引政策的唯一規則。`page()`只解析一次，交給head及索引模組共用；不讀目前瀏覽器Host、不掃dist中的示範頁、不把API的草稿資料或私人行程拿去產SEO。這延續既有Service Layer／Repository／DTO與純函式投影的架構，沒有新框架或PHP View。

## 程式與檔案用途

| 檔案（相對專案根目錄） | 職責 |
| --- | --- |
| frontend/assets/js/core/seo-resolver.js | 共用canonical／robots；新增保護頁及成功發布可索引判定，原SEO覆寫優先序保持。 |
| frontend/assets/js/core/structured-data.js、frontend/scripts/structured-data.mjs | Browser／Node共用白名單factory、安全JSON與可信時間解析；mjs為發布工具入口，不另複製規則。 |
| frontend/assets/js/core/list-seo.js | 分類／換頁／popstate更新同一份server graph的canonical、名稱與ID；noindex移除，不生成第二份。 |
| frontend/scripts/seo-head.mjs | 唯一head擁有者，移除原型與舊JSON-LD，公開成功頁只產一份graph；build／私頁不保留示範JSON-LD。 |
| frontend/scripts/crawler-index.mjs | 從已產生頁面收canonical、詳情lastmod；XML編碼、去重排序、URL／容量分片與robots純函式。 |
| frontend/scripts/publish.mjs | 組裝每頁context及meta、收集真實產頁結果、寫完HTML／爬蟲檔才啟用generation。 |
| frontend/scripts/build.mjs | 安全固定shell；robots的真sitemap網址由成功發布提供，build不寫過時固定值。 |
| backend/src/Adapters/NodeContentRenderer.php | 指紋納入schema發布入口、共用factory及索引模組；改模組會使generation重建。 |
| backend/src/DTO/PublishedRecord.php、Offer.php | 新增內部可選updatedAt，publicArray不會因此多傳欄位；既有構造呼叫相容。 |
| backend/src/Repositories/NewsRepository.php、OfferRepository.php | 從原SQL映射真修改時間；優惠同時考量已顯示的店家資料修改，不新增N+1查詢。 |
| backend/src/Services/ContentPublication.php | 只對已發布消息／優惠產私有indexModifiedAt映射；其他公開JSON API輸出不變。 |
| backend/src/Services/SeoPublication.php | 快照加入已發布SEO文件修改時間；公開site-settings仍由既有DTO白名單輸出。 |
| docker/nginx/default.conf | robots／根sitemap／正整數分片共用canonical來源與publication gate，明確文字／XML MIME；不執行業務查詢。 |
| backend/resources/openapi.json | 新三個Nginx爬蟲GET契約；104 PHP API＋3資源＝107 HTTP操作。 |
| frontend/tests/structured-data.test.mjs、crawler-index.test.mjs、public-list-seo.test.mjs | 白名單、真假內容、私頁、XSS、日期、XML、renderer失敗、50,000容量／分片，以及真列表控制器分類／頁碼／返回同步graph。 |
| backend/tests/schema_vocabulary.py | 用官方Schema.org詞彙獨立檢查本次graph的類別、domain／range、引用與日期／URL。不是Google Rich Results引擎。 |
| backend/tests/phase12_integration.py | 真HTTP／MariaDB驗證內容、索引、lastmod、改名301、下架404、失敗503、復原與原SEO資料復原；可留下自有Browser內容。 |
| backend/tests/phase12_published_inventory.py | 不修改資料、無JS，從實際sitemap逐頁檢查200／canonical／OG／Twitter與官方詞彙；適用清理後或正式交接。 |

## JSON-LD如何取資料

公開頁有WebSite／Organization／WebPage；詳情mainEntity才使用實際主題資料。館舍Museum、景點TouristAttraction、美食／在地店家LocalBusiness、電影Movie、城市消息Article、適用活動Event、優惠Offer。`name`及`description`取真實名稱／介紹／摘要，`image`取頁面顯示的公開圖，不把SEO摘要、OG預設圖或Google參考簡介當正文。

Event須有有效開始時間及場地名稱，缺少時使用Article。場地只有名稱就不捏造地址；這種Event可能不符合Google專用Rich Result的必要地址資料，不等同Schema.org語法錯誤，不因此阻擋業務發布。Offer只標真實優惠、店家及期間；沒有價格就不輸出price／虛構Product，沒有庫存或評分就不填。電影放映場次不是電影最初上映日期，也不猜導演、片長或年份。

私人、管理、搜尋、noindex及未發布館舍佔位頁沒有JSON-LD。字串經JSON序列化再編碼script終止字元，不把任何使用者資料直接串成script。源自功能規格E31:G31/L31及SEO規格B13:F13；具體factory、fallback及安全做法是E-34。

## 爬蟲與時間

`/robots.txt`排除`/admin/`並指向設定canonical origin的`/sitemap.xml`。私人API的授權和頁面noindex仍有效；robots不代替權限，也不以Disallow遮住本來應讓爬蟲看見的noindex頁。

sitemap只收本次成功產出的可索引頁。列表分頁以公開query URL表示，XML將`&`編為`&amp;`，不公開`/_content_pages/`實體檔案、舊slug alias、草稿或會員資料。先做去重及穩定排序；超過50,000筆或50MiB時產`sitemapindex`及`sitemap-1.xml`等分片，完整保留URL而非截斷。

詳情lastmod取內容與已發布SEO設定的實際修改時間。消息／優惠的時間藏在snapshot的indexModifiedAt，不更動公開JSON契約；優惠考量店家更新。固定頁、集合頁或無可靠時間省略lastmod；不以build、排程tick、放映日或優惠截止日冒充修改日。changefreq／priority亦不造值。來源為E33:G33/L33、SEO B14:F14及API草案40–41（D）；具體容量與分片是E-34技術邊界。

## 環境、備份與操作

維持PHP 8.5／MariaDB12.3／Docker、68固定HTML／33表327欄／19migration，沒有新環境變數、服務或依賴。正式網域未定（U-21），本機origin是替代，不能因此記外部Search Console／Bing或社群驗證成功。

現有備份保留完整DB（含site_settings、content_seo與內容）／媒體及release；HTML、JSON-LD、robots與sitemap都是可重建產物。復原流程的`content:publish`會一起重建新索引，不另備份一份容易失同步的靜態sitemap。正式release需包含新增Node模組和Nginx設定，不能只上傳dist。既有操作工具及正式驗證界線見[運維指南](operations.md)與[P0操作](../P0-OPERATIONS.md)。

```powershell
docker compose run --rm --no-deps frontend
docker compose run --rm --no-deps frontend node --test tests/*.test.mjs
docker compose exec -T app-test /var/www/tools/vendor/bin/phpunit -c phpunit.xml
docker compose exec -T app-test /var/www/tools/vendor/bin/phpstan analyse -c phpstan.neon --no-progress
docker compose exec -T -u www-data app-test php bin/console.php content:publish
New-Item -ItemType Directory -Force artifacts/phase12
Invoke-WebRequest https://schema.org/version/latest/schemaorg-current-https.jsonld -OutFile artifacts/phase12/schemaorg-current-https.jsonld
python -X utf8 backend/tests/phase11_fixture.py
python -X utf8 backend/tests/phase12_integration.py --schema-vocabulary artifacts/phase12/schemaorg-current-https.jsonld
python -X utf8 backend/tests/phase11_fixture.py --cleanup
python -X utf8 backend/tests/phase12_published_inventory.py --schema-vocabulary artifacts/phase12/schemaorg-current-https.jsonld --output artifacts/phase12/published-inventory.json
```

Schema.org詞彙來源為[官方完整JSON-LD](https://schema.org/version/latest/schemaorg-current-https.jsonld)，下載到忽略的artifacts作獨立測試輸入，不是應用程式執行依賴。更改Nginx來源設定後依既有流程產生設定、`nginx -t`再reload；正式TLS配方沿用相同模板。工具健康或Schema.org詞彙檢查不等於Google收錄／Rich Result／分享預覽已通過。
