# SEO 設定與 HTML 發布閱讀指南

更新：2026-10-24。來源：功能規格 E30–L32、SEO規格 5–9／15–17、資料表建議 C24–D25；具體設計為 E-28。使用者已授權階段七，**7A 資料層及 7B 管理設定 API／表單已建立，公開 SEO 同版發布及整階段驗收繼續**。下方帶日期／子步驟的結果保留當時範圍。

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

## 7B 管理設定與原型表單

GET／PUT `/api/v1/admin/settings/seo` 已加入 [seo-routes.php](../../backend/config/seo-routes.php)，合計 70 個操作。GET 需 settings.view，PUT 需 settings.edit／CSRF；共用 Kernel 管理 session 與唯讀模擬保護。

[SeoSettingsController](../../backend/src/Controllers/SeoSettingsController.php) 轉輸入／回應，[SeoSettings](../../backend/src/Services/SeoSettings.php) 取得既有發布鎖，在同一 transaction 重查權限、鎖文件版本、保存 defaults／overrides／audit。版本衝突回 409；audit 失敗整組回滾。稽核只存版本／狀態，不存 token 或正文。[SeoSettingsResult](../../backend/src/DTO/SeoSettingsResult.php) 明列文件及 publicationReady。此時公開產頁整合仍在 7C，因此保存不冒稱已完成網站 SEO 更新。

[SeoContentRepository](../../backend/src/Repositories/SeoContentRepository.php) 以 enum 映射固定表／欄、prepared query 搜尋選項及批次目標解析，不逐筆 N+1。GET 的 q 最多 80 字，每種類型先回 50 筆及截斷提示，可縮小搜尋。未授權內容 view 的管理員只見已公開內容；讀不到的草稿覆寫不輸出，完整文件更新仍保留它們。軟刪／不存在的孤立覆寫不再公開，後續保存可清除無效引用，不占用有效目標空間。

[seo-settings.js](../../frontend/assets/js/pages/seo-settings.js) 管版本、互斥、未保存修改、409／欄位錯誤及發布提示；[seo-fields.js](../../frontend/assets/js/components/seo-fields.js) 共用標籤／hint／欄位，[seo-settings-api.js](../../frontend/assets/js/api/seo-settings-api.js) 只包 API。frontend settings.html 保留原型面板、grid、逐頁 details 與底部保存；[phase7.css](../../frontend/assets/css/phase7.css) 只補必要換行與手機／焦點樣式。空 title／description 回繼承；明確清空 keywords 使用獨立勾選，避免空值歧義。搜尋選項不重置未保存欄位，欄位錯誤會先展開 details。

主庫在本機忽略的 `artifacts/phase7-before-main-migration.sql` 備份後套用 migration，主／測試庫均 33 表／18 份，未清資料／volume。尚無公開 settings GET，固定頁仍為 65；本子步驟未新增 Docker 服務或環境變數。

```powershell
python backend/tests/seo_settings_integration.py --dependency-path artifacts/audit-jsonschema
docker compose run --rm --no-deps frontend node --test tests/seo-settings-page.test.mjs
```

7B 實測：74 項檢查／32 回應契約，包括真會員不能代替管理員、view-only、撤權、CSRF、唯讀模擬、版本並行、audit 故障與草稿隔離／隱藏覆寫保留。自有帳號停用／session 清理，設定快照恢復，正常 audit 保留。表單 5 項行為測試通過，PHPStan10／PHPat 通過，240 檔格式 dry-run 零修正。最初測試工具誤將既有權限 PUT 的 204 當 200，以及少提供測試景點的 SQL 空欄，已修正工具並重驗；沒有改應用程式放寬要求或掩蓋失敗。
