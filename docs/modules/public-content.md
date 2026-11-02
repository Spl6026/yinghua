# 公開消息、優惠與首頁：程式閱讀指南

更新：2026-10-22。**5A–5E 完成本機實作與驗收：資料層、消息、優惠／店家／原型匯入及首頁。** 完整範圍與 S／D／U／E 來源見 [階段五計畫](../PHASE-5-PLAN.md)，最終結果見 [階段五驗收](../PHASE-5-ACCEPTANCE.md)。

## 目前資料流

CLI 輸入 → ImportEnvelope → 各 Management Service／DTO → Repository → 同一 PDO 交易 → ContentPublication → Node 前端生成 HTML。公開 Controller → PublicNews／PublicOffers → 同一成功快照；Repository 不呼叫 Service 或產生 HTML。

| 檔案 | 用途 |
| --- | --- |
| [012_merchants_profile.sql](../../backend/database/migrations/012_merchants_profile.sql) | 保留原店家連結與建立時間，補齊獨立 profile；只有原可公開店家先啟用 |
| [013_news_posts.sql](../../backend/database/migrations/013_news_posts.sql) | news_posts／news_aliases，分類白名單、可空活動時間與公開索引 |
| [014_offers.sql](../../backend/database/migrations/014_offers.sql) | offers／offer_aliases，店家 FK、條款／標題／優惠內容及可空日期檢查 |
| [ContentFields.php](../../backend/src/DTO/ContentFields.php) | 共用 UUID、slug、日期／時區、封面、發布及 SEO 格式 |
| [NewsInput.php](../../backend/src/DTO/NewsInput.php)／[OfferInput.php](../../backend/src/DTO/OfferInput.php)／[MerchantInput.php](../../backend/src/DTO/MerchantInput.php) | 主題欄位白名單與必填／選填；優惠條款依 Excel G23，其他依原型與計畫 |
| [NewsPost.php](../../backend/src/DTO/NewsPost.php)／[Offer.php](../../backend/src/DTO/Offer.php)／[PublishedRecord.php](../../backend/src/DTO/PublishedRecord.php)／[Merchant.php](../../backend/src/DTO/Merchant.php) | readonly 映射結果，固定識別／版本與公開欄位，不直接公開 DB row |
| [NewsType.php](../../backend/src/Models/NewsType.php) | event／city enum，沒有優惠類別 |
| [NewsRepository.php](../../backend/src/Repositories/NewsRepository.php)／[OfferRepository.php](../../backend/src/Repositories/OfferRepository.php) | 各自主題查詢、寫入、發布投影與歷史網址；公開店家停用時排除其優惠 |
| [MerchantRepository.php](../../backend/src/Repositories/MerchantRepository.php) | 獨立店家 profile 查找／保存及景點連結檢查 |
| [ContentMapping.php](../../backend/src/Repositories/ContentMapping.php) | 共用型別、日期及公開封面欄位映射，不接受外部表名／欄名 |
| [OfferValidity.php](../../backend/src/Policies/OfferValidity.php) | 台北日期的 upcoming／active／expired，截止日整天有效、空日期不限制該端 |
| [RichTextSanitizer.php](../../backend/src/Contracts/RichTextSanitizer.php)／[HtmlPurifierSanitizer.php](../../backend/src/Adapters/HtmlPurifierSanitizer.php) | HTML Purifier 4.19.1 Adapter；保留安全正文格式，移除危險標記／屬性；寫入 Service 接續驗證圖片 alt／模組引用 |
| [AttractionRepository.php](../../backend/src/Repositories/AttractionRepository.php)／[AttractionManagement.php](../../backend/src/Services/AttractionManagement.php) | 景點寫入相容新店家表，改分類保留有優惠的店家、刪除檢查優惠引用，錯誤提示含兩種引用 |

店家 profile 一次從原景點回填，之後景點改名不自動覆寫優惠聯絡資料。草稿景點首次建立 inactive profile，後續由受控維護明確啟用；不在公開查詢 JOIN 草稿景點。改回景點時，有優惠歷史的店家保留並解除連結；刪除景點仍檢查未刪優惠引用。

## 操作與已執行驗證

正常結構升級仍用 `docker compose exec -T app php bin/console.php db:migrate`；不得改已套用 migration。主庫本次升級前備份於被忽略的 `artifacts/backups/phase5-before-migrations-20261022.sql`。目前主／測試庫為 28 表、276 欄、十五份 migration；原始網站／Excel 未修改。

Docker PHP 8.5.12：60 項 PHPUnit／247 斷言通過，含選填欄位、優惠條款、截止日與安全正文；PHPStan level 10／PHPat 通過。`tests/public_content_persistence.php` 在 yinghua_test 交易中驗證 11 項並回滾；`tests/public_content_schema_upgrade.py` 新建隨機空庫／舊結構副本，驗證升級、重跑及原會員／景點／店家保留，最後只移除本程序建立的測試庫與授權。既有景點整合 65 項通過。尚未代表階段五功能驗收完成。

Rector 只執行 dry-run，既有簡化建議不批次套用。PHPStan 於安裝 vendor 後清除結果快取再分析。後續 CLI／公開頁接入時，會繼續更新本指南與真實驗收結果。

## 5B：消息與受控匯入

`content:import --module=news --file=/tmp/news.json` 預設 dry-run；`--apply` 才寫入，另支援明確 `--dry-run`。JSON 範本為 `{"schemaVersion":1,"items":[{"title":"消息標題","type":"city","status":"draft"}]}`，完整欄位見 [JSON schema](../../backend/resources/content-import.schema.json)。先用 `docker compose cp` 把 UTF-8 JSON 放入 app 的 /tmp，再以 www-data 執行 console.php；正式檔案放被 Git 忽略的位置。

新增未帶 id 時，dry-run 回傳 manifest；保存整份 manifest 物件再 apply，確保固定 UUID／發布時間。已存在資料的不同內容必須帶最新 version，相同正規化內容則可重跑 unchanged。省略的選填內容文字／封面／活動日期會按完整 replacement 清空；status／publishAt 省略保留，slug 留空沿用。沒有列在批次的資料不刪除。下架明確填 archived，不能以省略當刪除。

來源 G22 的正文安全由 Service 先淨化；改動回 `body_sanitized`，正文圖片須有 alt，本地圖片須屬 news。封面也驗所屬模組及有效 alt，不把「有圖時 alt 必填」擴成圖片必填。

媒體先以 `media:import --module=news --file=/tmp/image.png --alt=圖片說明` 匯入，或用已有管理媒體端點的 news 模組；取得 media.id 後填 coverMediaId。先匯入媒體與後續內容是兩筆操作，內容失敗時資產可留作未引用私有媒體。CLI 不下載外站圖片，外部 coverUrl 只存安全連結。

| 新增／修改檔案 | 資料流與用途 |
| --- | --- |
| [PublicContentCommands.php](../../backend/src/Adapters/PublicContentCommands.php)／[Composition.php](../../backend/src/Composition.php)／[console.php](../../backend/bin/console.php) | 只接受已能存取 Docker 的維運者；active 固定 admin 作 CLI 稽核，拒絕任意 --actor，輸入只讀 /tmp 真實檔案，回 JSON／requestId／exit code |
| [ImportEnvelope.php](../../backend/src/DTO/ImportEnvelope.php)／[ImportItem.php](../../backend/src/DTO/ImportItem.php)／[PreparedImport.php](../../backend/src/DTO/PreparedImport.php) | schemaVersion、2 MiB／500 筆、重複 ID／未知欄位、固定 manifest 與逐筆結果；這些上限是 E-26 |
| [ContentImportService.php](../../backend/src/Contracts/ContentImportService.php)／[ContentImporter.php](../../backend/src/Services/ContentImporter.php) | 先 prepare 全批，再同交易 save；提交後才同步 HTML；失敗不把已提交資料說成未保存 |
| [NewsManagement.php](../../backend/src/Services/NewsManagement.php)／[ImportVersion.php](../../backend/src/Policies/ImportVersion.php)／[ContentCover.php](../../backend/src/Policies/ContentCover.php)／[BodyImages.php](../../backend/src/Policies/BodyImages.php) | DTO、權限／版本、正文與媒體／alt、歷史網址占用、重跑、稽核；Controller 與 CLI 不重寫業務規則 |
| [PublicNewsController.php](../../backend/src/Controllers/PublicNewsController.php)／[PublicNews.php](../../backend/src/Services/PublicNews.php)／[NewsQuery.php](../../backend/src/DTO/NewsQuery.php)／[PublicCollection.php](../../backend/src/DTO/PublicCollection.php)／[DetailUrlResolver.php](../../backend/src/Services/DetailUrlResolver.php) | 三個 GET，分類／關鍵字／分頁、基本詳情與轉址（轉址由四類內容共用的 DetailUrlResolver 處理）；只讀同一成功 generation |
| [ContentPublication.php](../../backend/src/Services/ContentPublication.php)／[NodeContentRenderer.php](../../backend/src/Adapters/NodeContentRenderer.php)／[MediaAccess.php](../../backend/src/Services/MediaAccess.php) | 消息／alias 納入共同 generation，正文與封面圖片同樣有公開引用保護；模板及共用 view 變動納入版本 |
| [content-view.js](../../frontend/assets/js/core/content-view.js)／[news-view.js](../../frontend/assets/js/core/news-view.js) | HTML 編碼、台北日期、圖片替代、消息卡／推薦／基本閱讀與分頁；Node 與瀏覽器共用 |
| [public-news.mjs](../../frontend/scripts/public-news.mjs)／[publish.mjs](../../frontend/scripts/publish.mjs)／[Nginx](../../docker/nginx/default.conf) | 每個分類所有頁與 canonical 先生成，query 白名單映射；內部分頁產物不可直接繞過 gate |
| [news-api.js](../../frontend/assets/js/api/news-api.js)／[pages/news.js](../../frontend/assets/js/pages/news.js)／[public.js](../../frontend/assets/js/public.js)／[public-content.css](../../frontend/assets/css/public-content.css) | 保留原型選單／推薦＋全篇版型，換頁、歷史返回、錯誤重試、焦點與圖片失敗；禁止展示資料覆寫已接入消息 |

新增 GET `/api/v1/news`、`/news/{slug}`、`/news/resolve-url`（皆有 /api/v1 前綴），目前共 57 操作；契約見 [openapi.json](../../backend/resources/openapi.json)。列表有 featured 供原型上方推薦，同一分類的全部文章仍能在下方分頁讀取。草稿、未到發布時間、下架不回傳，無效分類 422；舊 slug 301 只指站內已公開目標。原型編號（prototype_id）示範匯入仍待後續受控 demo 命令接入，不把陣列位置當正式 ID。

CLI exit：0 成功／預覽／unchanged，2 驗證失敗，3 衝突，4 saved_publication_pending，1 非預期錯誤。exit 4 表示資料已存，重試既有 content:publish，不重新新增；更新中的 API／HTML gate 不提供舊內容冒充最新。

5B 已執行：消息 CLI→真實 MariaDB→HTML／HTTP 共 40 項，獨立 JSON Schema 驗證 12 個 API 回應；包含深分頁無 JS 內容、safe body、整批 rollback、重跑、版本衝突、隱藏草稿／未到期發布、下架後 alias、renderer 故障回 exit 4 及重試。fixture UUID 最後清理。Node 新增兩項真實發布／編碼／跨年檢查通過；PHP 60 項／247 斷言通過。這些尚非整個階段或全站畫面驗收。

介面程式依 web-design-guidelines 檢查本次新增 view／CSS／控制項，維持 label、真實分頁連結、鍵盤與 live status；完整桌面／手機瀏覽器比較留階段五整合驗收。security-audit 已讀主文件並檢查正文、URL、路徑、參數化 SQL、模組媒體及 CLI 權限；Windows 防毒隔離了該 skill 的 file-upload-security.md 範例參考檔，未停用防毒或把刪除併入專案功能。

## 5C：店家、優惠及原型示範匯入

`content:import --module=merchants|offers --file=/tmp/content.json` 使用同一 envelope／預覽、版本、交易與發布流程。店家最小資料為 name；attractionId 可空。優惠需 title、merchantId、discount、terms；日期、說明與圖片不強制填寫。terms 必須含實際非空白文字，不能只放空 HTML。店家必須存在，inactive 可維護優惠但暫不公開，dry-run 會提示。

公開 GET `/api/v1/offers`、`/offers/{slug}`、`/offers/resolve-url` 已加入，合計 60 操作。預設列表只含當日有效優惠；`active=false` 可查仍發布的已過期／尚未開始內容，仍排除草稿、未到發布時間、下架及停用店家。日期按台北日曆，截止日全天有效、空白起迄不限制該端。歷史詳情仍可讀，明確顯示未開始／已結束；原型優惠 article／卡片版型保留。

| 新增／修改檔案 | 用途 |
| --- | --- |
| [OfferManagement.php](../../backend/src/Services/OfferManagement.php)／[MerchantManagement.php](../../backend/src/Services/MerchantManagement.php) | 優惠／店家共用匯入介面，檢查權限、引用、slug、版本及稽核，不新增完整後台 CRUD |
| [PublicOfferController.php](../../backend/src/Controllers/PublicOfferController.php)／[PublicOffers.php](../../backend/src/Services/PublicOffers.php)／[OfferQuery.php](../../backend/src/DTO/OfferQuery.php) | 公開查詢、有效期／行政區／關鍵字／分頁、基本詳情；先前的網址代稱與以編號開啟的詳情頁由共用 DetailUrlResolver 處理 |
| [PublicContentSeeder.php](../../backend/src/Adapters/PublicContentSeeder.php)／[public-news-demo.json](../../backend/resources/public-news-demo.json)／[public-merchants-demo.json](../../backend/resources/public-merchants-demo.json)／[public-offers-demo.json](../../backend/resources/public-offers-demo.json) | 固定 UUID 原型示範，缺少才建立，已有內容／編輯／停用狀態保留；正式內容與圖權仍須確認 |
| [ImageOrigin.php](../../backend/src/Models/ImageOrigin.php)／[UploadedImage.php](../../backend/src/DTO/UploadedImage.php)／[LocalImageStorage.php](../../backend/src/Adapters/LocalImageStorage.php)／[MediaManagement.php](../../backend/src/Services/MediaManagement.php) | 明確分開 HTTP 真正上傳與 CLI /tmp 本機檔案；HTTP 不能指定來源，兩者共用格式、解碼重編碼、尺寸及 alt 驗證 |
| [offer-view.js](../../frontend/assets/js/core/offer-view.js)／[public-offers.mjs](../../frontend/scripts/public-offers.mjs) | 優惠卡、原型詳情殼、有效期提示及全部深頁生成 |
| [public-list-controller.js](../../frontend/assets/js/core/public-list-controller.js)／[offers-api.js](../../frontend/assets/js/api/offers-api.js)／[pages/offers.js](../../frontend/assets/js/pages/offers.js) | 消息與優惠共用 URL／history／競態／重試／焦點操作，主題 view 各自負責內容 |
| [offers_public_integration.py](../../backend/tests/offers_public_integration.py)／[public_content_clock.php](../../backend/tests/public_content_clock.php)／[public_demo_integration.py](../../backend/tests/public_demo_integration.py)／[offers-publication.test.mjs](../../frontend/tests/offers-publication.test.mjs) | 真實 DB／HTTP、跨日同版發布、原型示範保留與轉址、前端生成及下架檢查 |

初始化原型：`docker compose exec -T --user www-data app php bin/console.php content:seed-public-demo`。這是明確的本機示範，不會自動隨 migration、容器啟動或正式匯入執行。沿用原型四篇消息／三項優惠，原型明確列出的發布年採 2026；沒有猜活動時間或優惠發布時間。原店家可連結時沿用既有 profile，沒有時建立獨立店家；不覆寫後續維護。coffee-walk 舊消息網址只導向咖啡優惠，不冒充城市消息。完整後台管理與前後篇留階段九。

5C 已驗證：優惠整合 40 項／8 個獨立契約回應；跨台北午夜 12 項，含定時消息到點、優惠到期、切換前 gate 503、切換後 API／HTML 同版及無變更不重產；demo 10 項，包含已編輯內容保留及四種原網址。消息 40 項／12 個契約回歸通過；消息＋優惠 Node 共 4 項。PHPUnit 60 項／247 斷言、PHPStan／PHPat 通過，仍待整階段安全／UI／持久化驗收。

## 5D：首頁同版整合

新增 GET `/api/v1/home`，目前共 61 操作。首頁博物館與精選景點沿用既有選取，本週電影最多三部、不重複電影，依第一場排序，消息取發布時間排序前三篇。沒有資料顯示空區塊；沒有新增首頁表、設定後台或優惠卡片區。優惠保留原型首頁下方目錄入口。

| 檔案 | 用途 |
| --- | --- |
| [HomeContent.php](../../backend/src/DTO/HomeContent.php)／[HomeSelection.php](../../backend/src/Policies/HomeSelection.php) | typed 首頁資料與單一選取規則，含 museum／featured／films／filmTotal／period／news |
| [WeeklyFilmSelection.php](../../backend/src/Policies/WeeklyFilmSelection.php)／[PublicFilms.php](../../backend/src/Services/PublicFilms.php) | 首頁與電影 API 共用本週範圍、去重電影及場次排序 |
| [PublicHomeController.php](../../backend/src/Controllers/PublicHomeController.php)／[PublicHome.php](../../backend/src/Services/PublicHome.php) | HTTP 只回 JSON，Service 只讀一次成功快照，不依次查多個 API 拼湊不同版本 |
| [ContentPublication.php](../../backend/src/Services/ContentPublication.php)／[AttractionRepository.php](../../backend/src/Repositories/AttractionRepository.php)／[FilmRepository.php](../../backend/src/Repositories/FilmRepository.php) | 一次取得時刻，交給各公開列表及 aliases；產生 home 投影後再計算共同 generation |
| [home-view.js](../../frontend/assets/js/core/home-view.js)／[publish.mjs](../../frontend/scripts/publish.mjs)／[film-view.js](../../frontend/assets/js/core/film-view.js) | 原型首頁消息列、台北日期／星期、電影數量；SSR 直接使用 home 選取，嵌入相同 JSON 供核對，不再次抓資料覆寫 |
| [HomeSelectionTest.php](../../backend/tests/Unit/HomeSelectionTest.php)／[home-publication.test.mjs](../../frontend/tests/home-publication.test.mjs)／[home_public_integration.py](../../backend/tests/home_public_integration.py) | 選取／排序／邊界、跨年日期、空資料、真實 HTTP／HTML／API 同版及發布失敗後重試 |

原型 `.event-list` 三列保留。日期有活動開始時間時顯示活動日期；沒有時顯示發布日期，無障礙名稱會明確區分，沒有推測活動時間。消息排序由實際發布時間決定，所以不強制保持靜態原型文章順序。

5D 已驗證：首頁整合 21 項，含七個公開來源同版、前三筆消息／三部電影、直接 HTML、詳情連結、renderer 故障時 API／HTML 都 503、保存後重試及下架。PHPUnit 64 項／263 斷言、PHPStan／PHPat、格式檢查與兩項首頁 Node 檢查通過；跨日 12 項回歸通過。全階段桌面／手機、安全及容器重建驗收仍進行中。

## 5E：最終驗收與排查入口

前面 5A–5D 保留各功能交付當時的檢查；最終結果：PHPUnit 64 項／263 斷言、Node 101 項、PHPStan／PHPat／格式通過，應用及工具 Composer audit 無 advisories。Rector dry-run 有 29 檔建議（exit 2），檢視後未批次套用。消息／優惠／首頁整合分別 40／40／21 項，跨日與電影換週擴至 20 項；既有 21 組回歸的 788 個 HTTP 回應契約零差異。完整命令與畫面差異見驗收文件，不能以歷史數字代替本次結果。

| 檔案 | 用途 |
| --- | --- |
| [public_content_security.py](../../backend/tests/public_content_security.py) | 新模組 27 項 HTTP／真實 DB 越權及 CLI 邊界；本輪帳號經正常 API 軟刪／撤銷 session |
| [public_content_restart.py](../../backend/tests/public_content_restart.py) | 9 項隔離容器重建，驗證資料／圖片／HTML 位元組與 generation；保留 volumes／主庫 |
| [public_content_seed_fresh.py](../../backend/tests/public_content_seed_fresh.py) | 隨機空庫完整初始化的 10 項檢查，既有 profile 重用及 demo 不覆寫編輯／停用 |
| [public_content_queries.php](../../backend/tests/public_content_queries.php) | 實際發布 SQL 的唯讀 EXPLAIN／ANALYZE，不自動新增索引或重啟資料庫 |
| [public_content_browser_fixture.py](../../backend/tests/public_content_browser_fixture.py) | 隔離 21 篇消息／21 項優惠，用於深分頁、長字與缺圖；完成須 --cleanup |
| [ImageOriginTest.php](../../backend/tests/Unit/ImageOriginTest.php)／[PublicContentInputTest.php](../../backend/tests/Unit/PublicContentInputTest.php) | 上傳來源防偽、圖片內容、安全正文、欄位與日期規則 |
| [film_schema_upgrade.py](../../backend/tests/film_schema_upgrade.py) | 舊工具固定階段四 migration 集，繼續驗證當時 24 表／12 份；最新結構改由階段五工具驗證 |

桌面／手機實測 1440×900／390×844，包括原型對照、分類／URL／返回、深頁重整、圖片替代、長字、空資料、錯誤重試及首頁連結。原型 CSS 未改，消息卡片日期也沿用原型格式；資料排序、過期提示、基本詳情／前後篇界線明載於驗收，不宣稱所有內容與原型逐像素相同。缺少尺寸的外部正文圖片仍可能載入後改變段落高度；可提供 width／height，後端不為猜尺寸下載外站。

本機操作依賴 Docker 的同一份 .env，沒有新增變數。主站 localhost:8080，隔離測試 localhost:8081／yinghua_test；不要平行跑會重置測試 root 或注入發布故障的 suites。CLI／schema、主流程、全部新增檔案與來源均由本指南連結，未開始的完整管理、收藏／行程與設定維持原階段界線。
