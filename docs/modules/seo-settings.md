# SEO 設定與 HTML 發布閱讀指南

2026-10-31：階段十二已完成同成功generation的JSON-LD、robots與sitemap，沒有獨立索引資料表或備份。沿DB／媒體／release的content:publish可一起重建；原規則及正式平台交接維持。新檔案用途／命令見[索引指南](search-index.md)，實際結果見[階段十二驗收](../PHASE-12-ACCEPTANCE.md)。

## 2026-10-25 R03：列表切換沿用已發布 SEO

來源為功能規格 E30／E32／L32、工程 E-29。以前只有第一次產生 HTML 使用覆寫，分類／換頁卻自行寫入固定品牌標題。現在 [seo-head.mjs](../../frontend/scripts/seo-head.mjs) 在消息／優惠頁附 `public-list-seo` JSON：只含已發布預設、目前主題覆寫、該頁 catalog 及公開媒體白名單；字串的 `<`／`>`／`&` 編碼，其他內容覆寫不送出。

[list-seo.js](../../frontend/assets/js/core/list-seo.js) 讀取這份同版 context，分類／分頁／popstate 共用既有 `resolveSeo()`，再以 DOM 屬性更新 title、摘要、canonical、OG／Twitter。canonical 使用設定的正式 origin，不拼目前 localhost。沒有另外非同步抓一份可能不同版的設定，也不增加 API。

[public-list-controller.js](../../frontend/assets/js/core/public-list-controller.js) 若發現 API generation 已變，或舊頁缺 context，就改載新發布頁，避免舊設定搭配新內容。[news-view.js](../../frontend/assets/js/core/news-view.js) 的 `newsListTitle()`／`newsListDescription()` 由發布工具及瀏覽器共用；優惠也走同一控制器。首次 HTML 的完整無 JS head 保留。

[public-list-seo.test.mjs](../../frontend/tests/public-list-seo.test.mjs) 四項涵蓋 JSON 邊界、覆寫／品牌／正式 origin、第二頁、返回與換版，並執行真正列表控制器。全 Node 150 項及無 JS head 74 HTTP 通過；Brave 實際設定自訂標題／摘要／HTTPS canonical，分類、返回、手機 390px、重新整理均一致；測試後復原設定。

操作：後台「網站設定」→ 修改全站預設或展開各頁覆寫 → 儲存。空的標題／摘要覆寫回繼承，勾選清空關鍵字才明確不繼承；單筆內容先搜尋／選擇加入。409 保留輸入，確認後重讀；已保存待發布可稍後重讀或 CLI content:publish 復原。不要將 API Key 填入公開驗證 token。

本階段最後驗證命令：`python backend/tests/seo_settings_restart.py --dependency-path .cache/validation/python`、`python backend/tests/policy_pages_integration.py`、`python backend/tests/seo_head_inventory.py --dependency-path .cache/validation/python`。schema／管理／公開測試命令見各節；測試只用命名隔離環境。舊跨模組複驗用 phase6_regression.py，會員三組用 phase7_member_regression.py，後者不重試業務或網路錯誤。

更新：2026-10-24。來源：功能規格 E30–L32、SEO規格 5–9／15–17、資料表建議 C24–D25；具體設計為 E-28。使用者已授權階段七，**7A–7E 已完成本機範圍，實際結果與正式差項見 [階段七驗收](../PHASE-7-ACCEPTANCE.md)**。下方帶日期／子步驟的結果保留當時範圍。

## 7A 設定文件與覆寫資料層

[017_site_seo.sql](../../backend/database/migrations/017_site_seo.sql) 新建 site_settings／content_seo。第一張表只由目前 Repository 處理 `seo` 文件，JSON 保存全站預設與整份文件版本；第二張表保存逐頁／範本／單筆內容覆寫，唯一鍵分別約束內容目標及路徑。UUID 或路徑擇一是 E-28 對明確目標的技術設計；沒有增加 title／description 的業務必填要求。

[SeoDefaults](../../backend/src/DTO/SeoDefaults.php)、[SeoOverride](../../backend/src/DTO/SeoOverride.php)、[SeoSettingsDocument](../../backend/src/DTO/SeoSettingsDocument.php) 是明確型別的值；[SeoSettingsInput](../../backend/src/DTO/SeoSettingsInput.php) 驗證完整版本文件、null 繼承及欄位錯誤。160 字標題、500 字摘要、1000 字 keywords、2000 字圖片 URL 與最多 100 筆覆寫為 E-28 的資源上限，不是 Excel 建議的 30／70–100 字硬限制。空 title／description 覆寫回繼承，keywords 空字串可以明確清除。

[SeoResource](../../backend/src/Models/SeoResource.php)／[SeoRobots](../../backend/src/Models/SeoRobots.php) 限定已交付內容及三種索引值；[SeoUrlPolicy](../../backend/src/Policies/SeoUrlPolicy.php) 驗證 HTTPS origin／圖片及合法站內 canonical，[SeoTargetPolicy](../../backend/src/Policies/SeoTargetPolicy.php) 只接受已知頁面／範本與真 UUID。token 只收純 token，不接受整段 HTML，程式不發出外部抓圖請求。

[SeoCatalog](../../backend/src/Adapters/SeoCatalog.php) 讀取 [共用頁面 catalog](../../frontend/resources/seo-pages.json)，讓 PHP 輸入與 Node 後續產頁共享明確路徑與預設內容；不執行任意 regex 或檔案目錄掃描作為使用者篩選。

[SiteSettingsRepository](../../backend/src/Repositories/SiteSettingsRepository.php) 集中設定列與 FOR UPDATE；[ContentSeoRepository](../../backend/src/Repositories/ContentSeoRepository.php) 批次讀寫整份覆寫。兩者都不自行 commit，由 Service 的同一交易協調，避免只更新一半。現有 attractions／films／news_posts／offers 的 SEO 欄位未搬移或刪除。

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
python backend/tests/seo_settings_integration.py --dependency-path .cache/validation/python
docker compose run --rm --no-deps frontend node --test tests/seo-settings-page.test.mjs
```

7B 實測：74 項檢查／32 回應契約，包括真會員不能代替管理員、view-only、撤權、CSRF、唯讀模擬、版本並行、audit 故障與草稿隔離／隱藏覆寫保留。自有帳號停用／session 清理，設定快照恢復，正常 audit 保留。表單 5 項行為測試通過，PHPStan10／PHPat 通過，240 檔格式 dry-run 零修正。最初測試工具誤將既有權限 PUT 的 204 當 200，以及少提供測試景點的 SQL 空欄，已修正工具並重驗；沒有改應用程式放寬要求或掩蓋失敗。

## 7C 同版公開設定與 HTML head

GET `/api/v1/site-settings/public` 為第三個 SEO 操作，總計 71 個。[PublicSiteSettingsController](../../backend/src/Controllers/PublicSiteSettingsController.php) → [PublicSiteSettings](../../backend/src/Services/PublicSiteSettings.php) → [PublicSiteSettingsDocument](../../backend/src/DTO/PublicSiteSettingsDocument.php) 只輸出成功快照的預設與公開 token／version／generation，不回操作者、草稿或全部覆寫。

[SeoPublication](../../backend/src/Services/SeoPublication.php) 從兩個 Repository 讀公開設定，依同次內容 projection 的真實 ID 篩掉草稿／下架／刪除覆寫；ContentPublication 的 generation 納入結果。[NodeContentRenderer](../../backend/src/Adapters/NodeContentRenderer.php) 的模板雜湊包含共用 head／resolver／catalog 與固定頁，設定／模板異動都能重新發布，沒有外加 Redis 或 purge API。

[seo-resolver.js](../../frontend/assets/js/core/seo-resolver.js) 為共用純函式：逐欄依單筆覆寫、內容 SEO、頁面／範本、全站與頁面預設繼承，keywords 空字串保留；提供管理預覽的有效值來源、後綴去重、分頁 canonical、強制 noindex 與匿名媒體規則。[seo-head.mjs](../../frontend/scripts/seo-head.mjs) 統一移除舊標籤／安全編碼並產生一份 head。[build.mjs](../../frontend/scripts/build.mjs) 與 [publish.mjs](../../frontend/scripts/publish.mjs) 共用此函式，前端 JS 不在瀏覽器改寫 head；設定表單使用同一 resolver 顯示預覽及重複摘要提示，不阻擋儲存。

Nginx 的公開頁走成功 publication/current 與既有 gate，新增可由同版發布的條款／隱私路徑；此時 7D 尚未建立內容。管理／認證／私人頁保留 noindex 靜態 shell，不依賴發布成功才能開啟。Compose 的 frontend 建置容器轉傳**既有 APP_URL** 供固定 shell 的運行 origin，沒有新增環境變數；正式 SEO origin 不改 .env、Cookie 或 Nginx authority。本機／正式網址由各自部署設定處理，未提供正式網域不宣稱平台驗證通過。

分享圖不擴大 MediaAccess 授權。本站 media 必須原已公開可讀；內容下架後 resolver 改用公開 placeholder，不能為 SEO 公開任意草稿圖。外部 HTTPS 圖不由後端抓取，平台實際分享驗證仍待正式環境。完整 JSON-LD／動態 sitemap 保持後續範圍。

```powershell
docker compose run --rm --no-deps frontend npm run build
docker compose restart publisher web web-test
docker compose exec -T -u www-data app php bin/console.php content:publish
docker compose exec -T -u www-data app-test php bin/console.php content:publish
python backend/tests/seo_publication_integration.py --dependency-path .cache/validation/python
```

7C 實測：60 項真實 HTTP／DB 檢查、12 回應契約，涵蓋四種內容、公開快照排除草稿、固定頁與分頁標籤／canonical、驗證 token、保護頁 noindex、匿名資產、發布故障不混版及復原。使用自有店家／內容，finally 恢復原設定並清理，沒有呼叫 Google。resolver／head 6 項、表單 5 項及隔離副本的真實正式 origin 建置 1 項通過；PHPStan10／PHPat、244 檔格式 dry-run 通過。瀏覽器排版與整階段回歸仍待 7E。

## 7D 開發條款與隱私說明

[terms.html](../../frontend/pages/terms.html)／[privacy.html](../../frontend/pages/privacy.html) 沿用註冊頁既有的本機開發說明，清楚標記正式文案待業主提供；不是自行撰寫正式法律條款。註冊頁增加另開分頁的連結，保留尚未提交的表單與 returnTo。[policy.js](../../frontend/assets/js/policy.js) 只啟用既有導覽。

[policy-pages.mjs](../../frontend/scripts/policy-pages.mjs) 以文字編碼顯示既有 TERMS_VERSION；build 容器轉傳同名既有變數，公開產頁使用 ContentPublication 的 policyVersion，兩者不猜測新版本。auth.js 以真實 auth configuration 更新註冊說明中的版本；不改寫 member_consents 歷史。NodeContentRenderer 納入新模板 helper 的雜湊。共 67 固定頁，仍為 71 API／33 表／18 migration。

版本與安全編碼有 Node 測試及實際建置測試；`python backend/tests/policy_pages_integration.py` 唯讀檢查實際公開頁／註冊連結／當前版本與同意紀錄數不變，不接受條款或建立新會員。本機說明不代表正式條款驗收通過。
