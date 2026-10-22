# 公開消息、優惠與首頁：程式閱讀指南

更新：2026-10-22。**5A 資料層與 5B 消息公開／CLI 已接入；優惠公開與首頁整合尚未完成。** 完整範圍與 S／D／U／E 來源見 [階段五計畫](../PHASE-5-PLAN.md)。

## 目前資料流

輸入 → NewsInput／OfferInput／MerchantInput → 明確欄位 → 各 Repository → 同一 PDO 交易。後續管理 Service、匯入與公開輸出會接在這層上；Repository 不呼叫 Service 或產生 HTML。

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
| [RichTextSanitizer.php](../../backend/src/Contracts/RichTextSanitizer.php)／[HtmlPurifierSanitizer.php](../../backend/src/Adapters/HtmlPurifierSanitizer.php) | HTML Purifier 4.19.1 Adapter；保留安全正文格式，移除危險標記／屬性；媒體引用仍待寫入 Service 接入 |
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
| [PublicNewsController.php](../../backend/src/Controllers/PublicNewsController.php)／[PublicNews.php](../../backend/src/Services/PublicNews.php)／[NewsQuery.php](../../backend/src/DTO/NewsQuery.php)／[PublicCollection.php](../../backend/src/DTO/PublicCollection.php) | 三個 GET，分類／關鍵字／分頁、基本詳情與轉址；只讀同一成功 generation |
| [ContentPublication.php](../../backend/src/Services/ContentPublication.php)／[NodeContentRenderer.php](../../backend/src/Adapters/NodeContentRenderer.php)／[MediaAccess.php](../../backend/src/Services/MediaAccess.php) | 消息／alias 納入共同 generation，正文與封面圖片同樣有公開引用保護；模板及共用 view 變動納入版本 |
| [content-view.js](../../frontend/assets/js/core/content-view.js)／[news-view.js](../../frontend/assets/js/core/news-view.js) | HTML 編碼、台北日期、圖片替代、消息卡／推薦／基本閱讀與分頁；Node 與瀏覽器共用 |
| [public-news.mjs](../../frontend/scripts/public-news.mjs)／[publish.mjs](../../frontend/scripts/publish.mjs)／[Nginx](../../docker/nginx/default.conf) | 每個分類所有頁與 canonical 先生成，query 白名單映射；內部分頁產物不可直接繞過 gate |
| [news-api.js](../../frontend/assets/js/api/news-api.js)／[pages/news.js](../../frontend/assets/js/pages/news.js)／[public.js](../../frontend/assets/js/public.js)／[public-content.css](../../frontend/assets/css/public-content.css) | 保留原型選單／推薦＋全篇版型，換頁、歷史返回、錯誤重試、焦點與圖片失敗；禁止展示資料覆寫已接入消息 |

新增 GET `/api/v1/news`、`/news/{slug}`、`/news/resolve-url`（皆有 /api/v1 前綴），目前共 57 操作；契約見 [openapi.json](../../backend/resources/openapi.json)。列表有 featured 供原型上方推薦，同一分類的全部文章仍能在下方分頁讀取。草稿、未到發布時間、下架不回傳，無效分類 422；舊 slug 301 只指站內已公開目標。原型 legacy_id 示範匯入仍待後續受控 demo 命令接入，不把陣列位置當正式 ID。

CLI exit：0 成功／預覽／unchanged，2 驗證失敗，3 衝突，4 saved_publication_pending，1 非預期錯誤。exit 4 表示資料已存，重試既有 content:publish，不重新新增；更新中的 API／HTML gate 不提供舊內容冒充最新。

5B 已執行：消息 CLI→真實 MariaDB→HTML／HTTP 共 40 項，獨立 JSON Schema 驗證 12 個 API 回應；包含深分頁無 JS 內容、safe body、整批 rollback、重跑、版本衝突、隱藏草稿／未到期發布、下架後 alias、renderer 故障回 exit 4 及重試。fixture UUID 最後清理。Node 新增兩項真實發布／編碼／跨年檢查通過；PHP 60 項／247 斷言通過。這些尚非整個階段或全站畫面驗收。

介面程式依 web-design-guidelines 檢查本次新增 view／CSS／控制項，維持 label、真實分頁連結、鍵盤與 live status；完整桌面／手機瀏覽器比較留階段五整合驗收。security-audit 已讀主文件並檢查正文、URL、路徑、參數化 SQL、模組媒體及 CLI 權限；Windows 防毒隔離了該 skill 的 file-upload-security.md 範例參考檔，未停用防毒或把刪除併入專案功能。
