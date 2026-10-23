# SEO 設定與 HTML 發布閱讀指南

更新：2026-10-24。來源：功能規格 E30–L32、SEO規格 5–9／15–17、資料表建議 C24–D25；具體設計為 E-28。使用者已授權階段七，**目前完成 7A 共用資料層，尚無可用 SEO API 或設定表單**；後續交付隨功能更新，不把預留能力當成果。

## 7A 設定文件與覆寫資料層

[017_site_seo.sql](../../backend/database/migrations/017_site_seo.sql) 新建 site_settings／content_seo。第一張表只由目前 Repository 處理 `seo` 文件，JSON 保存全站預設與整份文件版本；第二張表保存逐頁／範本／單筆內容覆寫，唯一鍵分別約束內容目標及路徑。UUID 或路徑擇一是 E-28 對明確目標的技術設計；沒有增加 title／description 的業務必填要求。

[SeoDefaults](../../backend/src/DTO/SeoDefaults.php)、[SeoOverride](../../backend/src/DTO/SeoOverride.php)、[SeoSettingsDocument](../../backend/src/DTO/SeoSettingsDocument.php) 是明確型別的值；[SeoSettingsInput](../../backend/src/DTO/SeoSettingsInput.php) 驗證完整版本文件、null 繼承及欄位錯誤。160 字標題、500 字摘要、1000 字 keywords、2000 字圖片 URL 與最多 100 筆覆寫為 E-28 的資源上限，不是 Excel 建議的 30／70–100 字硬限制。空 title／description 覆寫回繼承，keywords 空字串可以明確清除。

[SeoResource](../../backend/src/Models/SeoResource.php)／[SeoRobots](../../backend/src/Models/SeoRobots.php) 限定已交付內容及三種索引值；[SeoUrlPolicy](../../backend/src/Policies/SeoUrlPolicy.php) 驗證 HTTPS origin／圖片及合法站內 canonical，[SeoTargetPolicy](../../backend/src/Policies/SeoTargetPolicy.php) 只接受已知頁面／範本與真 UUID。token 只收純 token，不接受整段 HTML，程式不發出外部抓圖請求。

[SeoCatalog](../../backend/src/Adapters/SeoCatalog.php) 讀取 [共用頁面 catalog](../../frontend/resources/seo-pages.json)，讓 PHP 輸入與 Node 後續產頁共享明確路徑與預設內容；不執行任意 regex 或檔案目錄掃描作為使用者篩選。

[SiteSettingsRepository](../../backend/src/Repositories/SiteSettingsRepository.php) 集中設定列與 FOR UPDATE；[ContentSeoRepository](../../backend/src/Repositories/ContentSeoRepository.php) 批次讀寫整份覆寫。兩者都不自行 commit，預計由 Service 的同一交易協調，避免只更新一半。現有 attractions／films／news_posts／offers 的 SEO 欄位未搬移或刪除。

測試庫已套用本 migration，為 33 表／18 份；主庫此時仍是 31 表／17 份。本階段沒有新增服務、port、套件、金鑰或環境變數。後續實作到發布流程前，不啟用新公開 API。

```powershell
docker compose exec -T app-test php bin/console.php db:migrate
docker compose exec -T app-test php tests/seo_settings_persistence.php
python backend/tests/seo_settings_schema.py
docker compose exec -T app php /var/www/tools/vendor/bin/phpunit --filter SeoInputTest
```

實測：新空庫／保留原資料升級均 33 表／18 migration，可重跑，舊會員／景點／收藏／行程站點保留，臨時 schema 清理。資料層 14 項包含唯一／目標／JSON／版本約束與 PK／唯一查詢 EXPLAIN，完成後全 rollback。輸入 PHPUnit 3 項／12 斷言通過。這些結果不等於管理 API、HTML 或瀏覽器通過。
