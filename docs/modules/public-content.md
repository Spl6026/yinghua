# 公開消息、優惠與首頁：程式閱讀指南

更新：2026-10-22。**目前只完成 5A 資料層前置，公開 API、CLI 與畫面尚未接入。** 完整範圍與 S／D／U／E 來源見 [階段五計畫](../PHASE-5-PLAN.md)。

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
