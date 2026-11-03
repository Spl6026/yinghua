# 全站導讀

這份文件只描述**目前**的架構與用法，改了架構或操作方式時直接改寫對應段落，不追加流水帳。每一輪做了什麼寫在 [變更紀錄](CHANGELOG.md)；決定的來源（Excel 儲存格、使用者指示、工程補充）寫在 [設計追溯](DESIGN-TRACEABILITY.md)。

更新：2026-11-03。

## 一、這是什麼

映畫竹塹是新竹城市生活網站：公開的景點、電影、消息、優惠、推薦行程與全站搜尋，會員的收藏與旅行計畫，以及後台內容管理、權限、SEO 與一般設定。

- 技術：PHP 8.5（只提供 JSON API）＋MariaDB 12.3＋原生 JavaScript，Docker 開發。
- 規格基準是原始 `映畫竹塹_後台工程串接功能規格.xlsx`；`website/` 是客戶交付的原型，保留原狀不修改。
- 15 個階段的本機範圍都已完成。正式網域、外部寄信、搜尋引擎與分享平台、正式伺服器的安全修補，依 [正式交接](PRODUCTION-HANDOFF.md) 在正式環境確認。

## 二、資料夾

| 目錄 | 用途 |
| --- | --- |
| `backend/` | PHP API：`src/` 程式、`config/routes.php` 路由、`database/migrations/` 資料表、`bin/` CLI、`resources/` OpenAPI 契約與示範資料、`tests/` 測試 |
| `frontend/` | `pages/` 頁面原始檔、`templates/` 共用片段、`assets/` JS／CSS／字型、`scripts/` 建置與發布腳本、`tests/` 測試 |
| `website/` | 原始交付原型，唯讀 |
| `docs/` | 本導讀、架構與流程、`modules/` 各功能閱讀指南、`archive/` 歷史計畫與驗收紀錄 |
| `docker/` | PHP 與 Nginx 設定 |
| `scripts/` | 環境初始化（`setup-*.ps1`）、維運（`operations/`）、交付驗證（`validation/delivery.py`） |
| `tools/` | 品質工具與驗證依賴清單 |
| `.github/workflows/` | CI：PR 自動檢查 |
| `artifacts/`、`.cache/` | 本機驗證產物與工具快取，不進 Git |

## 三、請求怎麼被處理

```
瀏覽器 ──► Nginx (web)
            ├─ 公開頁（首頁、列表、詳情、sitemap）：讀發布版本 /var/www/publication/current 的 HTML
            ├─ 登入、會員、後台頁：讀建置好的 frontend/dist
            └─ /api/v1/*：轉給 PHP-FPM (app) → Kernel → Controller → Service → Repository → MariaDB
```

**公開內容的發布**：後台每次儲存後，PHP 從資料庫組出「公開快照」（`PublicationSnapshotBuilder`），交給 Node 的 `frontend/scripts/publish.mjs` 產生整站 HTML、sitemap 與搜尋索引，寫成一個新的版本目錄後原子切換 `current`。公開 API 與頁面都讀同一個版本。

- 讀取時直接提供最近一次成功發布的版本，不再逐次比對資料庫。
- 排程發布時間到、跨日（優惠到期）、換週（本週映演），以及直接改資料庫的情況，由 `publisher` 服務每 10 秒比對後重新產生，最多延遲約 10 秒。
- 發布失敗時繼續提供上一版，後台會顯示「已保存，公開同步失敗」並可重試；只有從未成功發布過時才回 503。

（使用者 2026-11-03 選定，見 [設計提案與決定](archive/PUBLICATION-FAST-CHECK-PROPOSAL.md)。）

## 四、後端（`backend/src/`）

分層與每層的規則見 [PHP 分層閱讀指南](modules/backend-layering.md)（含使用到的設計模式）與 [架構](ARCHITECTURE.md)。

| 目錄 | 內容 |
| --- | --- |
| `Composition.php`、`Composition/` | 唯一組裝物件的地方；`SharedServices` 放同一請求共用的物件，各區塊一個工廠方法 |
| `Http/` | `Kernel`（身分、CSRF、權限、錯誤轉 JSON）、`Router`、請求／回應 |
| `Controllers/` | HTTP 輸入與輸出，不含業務邏輯 |
| `Services/` | 使用案例、權限、交易、發布；`ContentWriter` 是電影／景點／路線共用的儲存刪除流程 |
| `Repositories/` | SQL 與資料映射，`Database` 提供共用查詢工具 |
| `DTO/`、`Models/` | 輸入輸出物件與列舉；`PublicContentType` 集中四類公開內容的差異 |
| `Policies/` | 純規則（營業時間、優惠有效期、上下篇、首頁挑選等） |
| `Contracts/`、`Adapters/` | 外部服務介面與實作（Node 發布、SMTP、Google、圖片儲存、HTML 淨化） |
| `Exceptions/`、`Support/` | `ErrorCode` 列舉與錯誤、UUID／JSON／時間等工具 |

- 路由全部在 `backend/config/routes.php`；API 契約在 `backend/resources/openapi.json`（115 個操作，測試會以它驗證回應）。
- CLI：`backend/bin/console.php`，指令表見 `php bin/console.php help`（migrate、seed、發布、匯入、清理過期資料等）。

## 五、前端（`frontend/`）

細節見 [前端版型與程式閱讀](modules/frontend.md)。

- `pages/`：22 個公開與會員頁、`pages/admin/` 26 個後台頁；`templates/` 是頁首、頁尾等共用片段，建置時展開。
- `assets/js/`：`api/` 各 API 的呼叫模組、`core/` 共用能力（session、表單狀態、分頁、SEO、內容類型對照等）、`components/` 介面元件、`pages/` 各頁入口。
- `scripts/build.mjs` 產生 `dist/`（固定頁）；`scripts/publish.mjs` 依公開快照產生發布版本，各類內容的頁面由 `public-*.mjs` 負責。
- 資產網址帶依內容計算的版本號（`?v=`），可長期快取；公開 HTML 為 `no-cache`，會員與後台頁 `no-store`。

## 六、資料庫

21 支 migration（`backend/database/migrations/`，只新增不修改）建立 33 張業務資料表；另有 1 張由 `db:migrate` 自動建立的 migration 紀錄表：

| 類別 | 資料表 |
| --- | --- |
| 帳號與權限 | `admin_users`、`members`、`roles`、`permissions`、`role_permissions`、`admin_role_permissions`、`sessions`、`member_consents` |
| 安全與稽核 | `captcha_challenges`、`password_reset_tokens`、`auth_events`、`auth_rate_limits`、`audit_logs` |
| 公開內容 | `attractions`、`attraction_aliases`、`merchants`、`films`、`film_screenings`、`film_aliases`、`news_posts`、`news_aliases`、`offers`、`offer_aliases`、`routes`、`route_attractions`、`media_assets` |
| 會員資料 | `favorites`、`itineraries`、`itinerary_items` |
| 設定與發布 | `site_settings`、`content_seo`、`content_publications`、`search_documents` |

欄位與 Excel 的對照見 [資料表對照](DATABASE-SPECIFICATION-MAP.md)。會員行程的版本號以每份行程各自計算（見 [會員行程指南](modules/member-itineraries.md)）。

## 七、Docker 服務

| 服務 | 用途 |
| --- | --- |
| `web` | Nginx，http://localhost:8080 |
| `app` | PHP-FPM |
| `publisher` | 常駐發布程序：每 10 秒檢查並重新發布、每小時清理過期的登入與驗證碼紀錄 |
| `db` | MariaDB |
| `mailpit` | 本機收信，http://localhost:8025 |
| `frontend` | 一次性的 Node 建置與測試 |
| `app-test`、`web-test` | 隔離的測試站，http://localhost:8081，資料庫 `yinghua_test`（`--profile test`） |

環境變數與初次設定見 [環境指南](ENVIRONMENT-SETUP.md)。

## 八、常用操作

```bash
# 啟動（含測試站）
docker compose --profile test up -d
# 前端建置；殼層若設有 TERMS_VERSION 會被寫進固定頁，所以先移除
env -u TERMS_VERSION docker compose run --rm --no-deps frontend node scripts/build.mjs
# 重新發布公開內容
docker compose exec -T -u www-data app php bin/console.php content:publish
# 修改 PHP 後重啟，讓 PHP-FPM 讀到新程式
docker compose restart app app-test publisher
```

**檢查與測試**（詳見 [交付驗證指南](modules/delivery-validation.md)）：

| 範圍 | 指令 |
| --- | --- |
| PHP 靜態分析、格式、單元測試 | `docker compose exec -T app sh -c 'cd /var/www/app && /var/www/tools/vendor/bin/phpstan analyse && /var/www/tools/vendor/bin/phpunit'` |
| 前端格式與測試 | `docker compose run --rm --no-deps frontend sh -c 'npm run format:check && npm test'` |
| 整合測試（19 組，真 HTTP／MariaDB） | `python3 scripts/validation/delivery.py integration --dependency-path .cache/validation/python --schema-vocabulary .cache/validation/schemaorg-current-https.jsonld` |
| 加上瀏覽器冒煙測試 | 上一行加 `--browser` |

PR 會由 GitHub Actions（`.github/workflows/checks.yml`）自動執行不需要資料庫的檢查；整合測試仍在本機 Docker 執行。

## 九、要改某個功能時從哪裡看

| 功能 | 閱讀指南 |
| --- | --- |
| 登入、註冊、密碼、session | [authentication](modules/authentication.md) |
| 管理員、權限、唯讀模擬 | [admin-permissions](modules/admin-permissions.md)、[admin-preview](modules/admin-preview.md) |
| 景點、地圖匯入、營業時間、摘要補位 | [attractions](modules/attractions.md)、[maps-import](modules/maps-import.md)、[opening-hours](modules/opening-hours.md)、[attraction-content-fallback](modules/attraction-content-fallback.md) |
| 電影與場次 | [films](modules/films.md) |
| 消息、優惠、首頁 | [news-offers-admin](modules/news-offers-admin.md)、[public-content](modules/public-content.md) |
| 推薦路線 | [recommended-routes](modules/recommended-routes.md) |
| 會員收藏與行程 | [member-resources](modules/member-resources.md)、[member-itineraries](modules/member-itineraries.md) |
| 圖片 | [media](modules/media.md) |
| SEO、結構化資料、sitemap、搜尋 | [seo-settings](modules/seo-settings.md)、[search-index](modules/search-index.md)、[site-search](modules/site-search.md) |
| 一般設定、條款、會員查詢、儀表板 | [general-site-settings](modules/general-site-settings.md)、[member-directory](modules/member-directory.md)、[operations-overview](modules/operations-overview.md) |
| 部署、備份、還原 | [operations](modules/operations.md)、[P0 部署與運維](P0-OPERATIONS.md)、[正式交接](PRODUCTION-HANDOFF.md) |

## 十、文件地圖

| 文件 | 內容 |
| --- | --- |
| [CHANGELOG](CHANGELOG.md) | 每一輪的實際變更與驗證結果 |
| [DESIGN-TRACEABILITY](DESIGN-TRACEABILITY.md) | 每項設計的來源：Excel、使用者指示（U-xx）或工程補充（E-xx） |
| [ARCHITECTURE](ARCHITECTURE.md)、[DEVELOPMENT-WORKFLOW](DEVELOPMENT-WORKFLOW.md)、[GIT-WORKFLOW](GIT-WORKFLOW.md) | 架構原則、開發與提交流程 |
| [ADMIN-OPERATING-GUIDE](ADMIN-OPERATING-GUIDE.md) | 後台操作 |
| [DELIVERY-ACCEPTANCE-MATRIX](DELIVERY-ACCEPTANCE-MATRIX.md)、[Excel 逐列核實](EXCEL-FUNCTIONAL-VERIFICATION-20261102.md) | 規格驗收對照 |
| [archive/](archive/) | 各階段計畫、驗收、安全審查與複驗紀錄（歷史，不再更新） |
