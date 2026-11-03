# 變更紀錄

由新到舊記錄每一輪實際完成的內容、驗證結果與尚待正式環境確認的項目。現況說明請看 [全站導讀](PROJECT-GUIDE.md)；各次決定的來源追溯見 [設計追溯](DESIGN-TRACEABILITY.md)。

2026-11-03 起，新的紀錄只寫在本檔；`PROJECT-GUIDE.md` 只描述目前的架構與用法。更早的完整導讀保存在 [archive/PROJECT-GUIDE-20261103.md](archive/PROJECT-GUIDE-20261103.md)，階段計畫與驗收文件在 [archive/](archive/)。

## 2026-11-03 文件與測試整理

- 文件：`PROJECT-GUIDE.md` 改寫為現況導讀；逐輪紀錄移到本檔；階段計畫、驗收、審查與複驗文件移到 `docs/archive/`（69 份），連結已一併更新。`AGENTS.md` 的階段狀態移到本檔，只保留規則。
- 會員行程版本號改為每份行程各自計算（U-28），見 [會員行程指南](modules/member-itineraries.md)。
- 測試整理：約 70 支未納入自動執行的測試腳本全部跑過一次，修好 16 支已失效的（皆為測試過時），並以 `delivery.py integration --full` 納入 32 組完整回歸；刪除舊的 `phase8_validation.py`。見 [交付驗證指南](modules/delivery-validation.md#完整回歸---full2026-11-03)。

2026-11-03 品質改進：
- 修正：只有編輯權限（沒有檢視）的管理員，電影／景點「已儲存但公開頁未同步」時網址也換成編輯頁（`film-form.js`、`attraction-form.js`，補兩個回歸測試）。
- 測試：修好五支長期失敗、未納入自動化的整合測試，並加入 `scripts/validation/delivery.py`，固定套件由 14 組增為 19 組。原因與處理見 [交付驗證指南](modules/delivery-validation.md)。
- CI：新增 `.github/workflows/checks.yml`，PR 與 main 自動執行 PHPStan、PHPUnit、PHP-CS-Fixer、Prettier、前端建置與測試、維運腳本測試。`backend/phpunit.xml` 改為提供測試專用 APP_KEY，單元測試不再依賴開發環境的 .env。
- 瀏覽器冒煙測試：`backend/tests/browser_smoke.py`＋`frontend/tests/browser/smoke.mjs`（70 項，只連隔離的 8081，自建並清理帳號），`delivery.py integration --browser` 可併入執行；`frontend` 新增 devDependency `playwright` 1.63.0。
- 提案：[公開快照新鮮度檢查的效能改善](archive/PUBLICATION-FAST-CHECK-PROPOSAL.md)，待決定，尚未實作。
- 修正：後台自訂條款版本名稱時，註冊頁原本顯示內部同意代碼（policy-雜湊）。`GET /api/v1/auth/csrf` 增加 `termsLabel`（OpenAPI 已同步），註冊頁顯示該名稱，送出同意仍用 `termsVersion`。
- 公開內容新鮮度改為信任發布流程（使用者選擇方案 D，U-27）：讀取直接用最近一次成功發布的版本，publisher 每 10 秒比對更新；發布失敗時保留上一版。公開 API 約 18–20 ms 降到 6–8 ms。見 [提案與決定](archive/PUBLICATION-FAST-CHECK-PROPOSAL.md)。
沒有新增 API 路由、資料表、migration 或環境變數。

2026-11-03 行程頁拆為總覽／內容／編輯（使用者指定，U-26）：`/planner.html` 依網址參數顯示行程總覽、唯讀行程內容或編輯畫面，入口 `pages/planner.js` 分派到 `trip-overview.js`、`trip-detail.js`、`trip-editor.js`；新增 `core/planner-links.js`、`components/trip-card.js`、`components/trip-stop-view.js`。行程清單 API 摘要增加 `stopCount`（OpenAPI 已同步）。修正編輯時每次儲存、重新載入或確認都捲到頁尾的問題（舊版實測 scrollY 500→1581，新版維持 500）。沒有新增固定頁、路由、資料表、migration 或環境變數。驗證：PHPStan、PHPUnit 142、前端 317、整合 14 組、API 108 項、行程整合 100 項／契約 55、Phase 11 81 項，以及瀏覽器 1440／390 實際操作總覽→新增→編輯→內容→刪除與 `?place=` 待辦。詳見 [會員行程指南](modules/member-itineraries.md)。

2026-11-03 SOLID 與設計模式整理（使用者要求程式碼盡量符合 SOLID、套用合適的設計模式以提高可讀性；行為不變，模式對照見 [後端分層指南](modules/backend-layering.md#設計模式對照) 與 [前端指南](modules/frontend.md)）：
- 後端組裝：`Composition::kernel()` 拆為 `Composition/SharedServices.php`（同一請求共用的連線、權限、稽核、發布物件）與帳號、景點與電影、消息優惠、會員、全站設定五個工廠方法；消息／優惠後台改以介面型別組裝。
- 後端發布：快照組裝移到 `Services/PublicationSnapshotBuilder.php`（Builder），`ContentPublication` 只管發布流程；`SECTIONS` 補回發布延遲診斷漏掉的三個區段，並以單元測試確認與實際快照一致。四種公開內容的網址、標題欄位、上下篇分組與搜尋分類集中在 `Models/PublicContentType.php`（原 `DetailUrlType`，Strategy）。政策檢查只依賴新的 `Contracts/PublishedSnapshot`（介面隔離）。`bin/console.php` 改為指令表（Command），說明文字自動產生。`SeoTargetRepository` 改由 `Clock` 取得時間。
- 前端：四個後台表單共用 `core/admin-form.js` 的 `createFormState()`（載入、處理中、完成、未儲存提醒）；公開內容類型對照集中於 `core/content-kinds.js`，結構化資料改為依類型的建構函式表；`scripts/publish.mjs` 拆為 `publish-page.mjs`、`public-attractions.mjs`、`public-films.mjs`、`public-routes.mjs`、`public-static.mjs`、`home-page.mjs`，整站 33 個發布檔案在排除版本雜湊後與拆分前逐位元組相同；SEO 覆寫列移到 `components/seo-override-row.js`；頁內事件名稱集中於 `core/page-events.js`。
- 驗證：PHPStan、PHP-CS-Fixer、PHPUnit 142、前端 314、整合 14 組、API 整合 108 項通過；Browser 檢查公開／會員／後台頁零錯誤，四個後台編輯表單實際載入、未儲存確認與儲存，SEO 設定頁預覽／清除／加入單筆／儲存。`tests/publication_alias_order.php`、`tests/public_content_clock.php` 與 `policy_pages_integration.py` 在本輪修改前即失敗（不在自動化套件內），未處理。沒有新增 API、資料表、migration 或環境變數。

2026-11-03 重複邏輯整併（使用者要求統一、重用、精簡並優化效能；依前端與後端兩份重複程式盤點逐項處理）：
- 後端：錯誤代碼改為 `ErrorCode` enum；Repository 的新增／版本更新／舊網址保留／軟刪除／LIKE／IN 清單／重複鍵判斷改用 `Database` 共用方法（推薦路線與景點的軟刪除原本漏更新 `updated_at`）；UTC 時間、UUID、JSON、常用設定與公開快照查找改用共用工具；消息與優惠後台合併為 `AdminContentManagement`／`AdminContentController`，CLI 與後台共用同一組編輯服務；控制器的分頁參數、只帶版本號的請求與 200／201／202 回應改用共用方法；JSON 請求本文只解析一次。清單見 [PHP 分層指南](modules/backend-layering.md#共用的基礎工具2026-11-03-整併)。
- 後端效能：推薦路線的站點一次查完（原本每條路線查一次）；同一請求內的權限檢查重用已讀取的帳號與權限（交易內加鎖的檢查仍重新讀取）；發布驗證在同一請求內重用，正式映像建置時預先算好發布版本雜湊。
- 前端：後台景點／電影／消息／優惠／推薦行程列表改用 `core/admin-table.js`（統一刪除最後一筆後退回有效頁、讀取中不清空表格、唯讀模擬一律顯示編輯連結並停用刪除、狀態用語「草稿／排程發布／已發布／已下架」）；後台表單共同規則與圖片上傳流程改用 `core/admin-form.js`（只有新增權限時，景點／電影表單的「取消」不再連回同一張新增頁或固定回首頁，與消息、路線一致；推薦行程表單補上登入逾時處理並忽略過時的重新載入）；台北時間改用 `core/taipei-time.js`（消息／優惠後台列表的發布時間改為與其他列表相同的 `YYYY-MM-DD HH:mm:ss`）；後台 API 模組改用 `api/admin-resource.js`（管理員 API 的 create／update／remove 改為 save／delete）；移除 8 個沒人使用的匯出；style.css 移除 23 個一定被後載樣式表覆蓋的宣告（36 頁×兩種寬度逐元素比對計算樣式無差異）。清單見 [前端指南](modules/frontend.md#共用模組2026-11-03-整併)。
- 測試：後台列表測試改為以 `tests/support` 的假 DOM 與模組替身載入真正模組；`npm test` 加上 `--experimental-test-module-mocks`。建置與發布的頁面在台北時間整併前後逐字比對相同（版本號除外）。
- 後台內容寫入流程：電影、景點、推薦路線的儲存與刪除改用共用的 `Services/ContentWriter.php`。同時統一四項差異：只有「編輯」權限的管理員可開啟電影與景點的編輯頁（使用者依功能規格 D20「具 create／edit 權限者」確認，原本必須另有「檢視」）；網址識別衝突一律標在欄位旁（消息、優惠原本只有一般訊息）；格式錯誤的編號一律回「找不到」；稽核紀錄新增時「修改前版本」記空白（路線原本記 0）、狀態記實際儲存結果（景點原本記送出的值）。
- 尚未處理（後續）：發布驗證改用輕量指紋、部分頁面直接呼叫 `api()` 未經 API 模組、公開頁三套分頁讀取（景點、電影、搜尋）與 `public-list-controller` 合併、其餘約 25 個仍複製原始碼的前端測試、數百行的表單初始化函式拆分。

2026-11-03 上線穩定性（使用者要求對照一般網站慣例、整理成可上線狀態；依前端／後端／維運三份盤點逐項處理）：
- 錯誤紀錄：新增 [Support/ErrorLog.php](../backend/src/Support/ErrorLog.php)，未預期例外改記類別、錯誤代碼（PDO 含 SQLSTATE／資料庫編號）、發生位置與前 6 層呼叫位置；刻意不記例外訊息與參數（重複鍵訊息含電子郵件等值）。`php.ini` 加 `zend.exception_ignore_args`。原本只記類別名稱，正式環境出錯時無從追查。
- 資料庫逾時：網頁請求改用 `Database::forRequest()`，每個連線設 `innodb_lock_wait_timeout=5`、`max_statement_time=10`；等鎖逾時（1205）與查詢超時（1969）回 503「系統忙碌中」並帶 `Retry-After`。命令列（發布、migration）不套用。
- PHP-FPM（`docker/php/www.conf`）：`pm.max_children=16`、`pm.max_requests=500`、`request_terminate_timeout=30s`；原本是預設 5 個 worker、請求不限時，幾個卡住的請求就會讓公開頁排隊。變更需重建 app 映像。
- API 錯誤格式一致：Nginx 自己產生的 413（超過 10 MB）與 PHP 無回應的 502／503／504，在 `/api/` 下改回與 PHP 相同的 JSON（`code`／`message`／`fieldErrors`／`requestId`）；`public/index.php` 以 shutdown handler 讓記憶體不足等致命錯誤也回 JSON 500。公開頁發布未就緒時的 503 改為帶 `text/html` 的簡短說明頁（原本沒有類型，可能被瀏覽器當檔案下載）。
- Nginx（`docker/nginx/default.conf`）：`absolute_redirect off`（`/admin` 補斜線轉址原本掉了埠號）；CSP 補 `default-src`／`style-src`／`img-src`（允許 https 外部封面）／`font-src`／`connect-src`，另加 `Permissions-Policy`；`/api/` 清空用戶端自帶的 `X-Original-URI`，並隱藏 PHP 重複送出的 `Cache-Control`／`nosniff`；存取紀錄加 `time`、`upstream`、`upstream_time`。在 location 內加 `add_header` 會讓伺服器層級的安全標頭失效，錯誤位置因此刻意不加。
- 登入限速：同一帳號的 5 次／15 分鐘改以「帳號＋來源」計算，另設帳號總上限 50 次／15 分鐘（`Services/Authentication.php`）；原本只以帳號計數，知道帳號名稱的人可以從任何地方持續讓本人無法登入。註冊用電子郵件驗證碼另加單一來源 10 封／小時、全站 300 封／小時（`Services/Captcha.php`），避免被拿來大量寄信。數值屬工程設定，非規格硬性要求。
- 過期紀錄清理：新增 [SecurityRecordRepository](../backend/src/Repositories/SecurityRecordRepository.php) 與 `php bin/console.php security:prune`，分批刪除過期超過一天的 session、驗證碼、密碼重設 token 與限速紀錄；常駐的 `content:watch` 每小時自動執行一次。登入事件（auth_events）屬稽核紀錄，保存期限待決定，不清除。
- 前端 API 錯誤（[core/api-client.js](../frontend/assets/js/core/api-client.js)）：回應不是 JSON 時（閘道錯誤頁等）依狀態碼顯示「上傳內容過大」「服務暫時無法使用」等訊息並保留狀態碼，成功但不是 JSON 也視為錯誤；逾時與斷線分開說明。原本一律顯示「請檢查連線」。上傳（FormData）預設逾時改為 120 秒，其他維持 15 秒，可由 `api(path, { timeout })` 指定。新增 `tests/api-client.test.mjs` 直接載入模組測試。
- 發布腳本防呆：新增 [scripts/html-edit.mjs](../frontend/scripts/html-edit.mjs) 的 `mustReplace()`，`publish.mjs`／`public-news.mjs`／`public-offers.mjs` 把資料寫進原型頁面時，找不到預期標記就中止發布，不再靜靜產生缺內容的頁面（唯一允許找不到的是美食頁沒有的分類筆數）。`<body>` 屬性改為保留原有屬性再加上，推薦行程頁原本因 `<body data-managed-routes>` 對不上而少了屬性。`NodeContentRenderer` 發布失敗時記錄 Node 錯誤類型，「缺少標記」另附標記內容（不含頁面資料）。
- 開發提醒：改了 `frontend/` 原始檔後要先 `node scripts/build.mjs` 再發布；發布頁與建置輸出的資產版本號都由原始檔算出，只做其中一邊會讓同一模組以兩個版本號各載入一次。正式環境兩者來自同一映像，不會不一致。
- 前端小修正：`toast()` 每次重設計時器（連續提示不再提早消失）；新增 `ui.js` 的 `onPageReturn()`，收藏與行程頁切回分頁時 focus／visibilitychange／pageshow 一秒內只重新確認一次（原本會重複送出請求）；`planner.js` 初始化失敗改顯示提示；本週映演換頁後保留 `<bdi lang="en">THIS WEEK</bdi>`；`public-list-controller` 缺少分頁區塊時不再出錯。
- 城市散策／美食篩選寫進網址（`pages/attractions.js`）：分類、區域、關鍵字與頁碼以 `?category=&district=&q=&page=` 保存，重新整理、分享連結與上一頁都回到同一結果；換分類、區域、頁碼新增瀏覽紀錄，打字搜尋只更新目前紀錄。美食頁只記錄與預設「美食」不同的分類。
- 公開頁快取：Nginx 對公開 HTML（含消息／優惠內部分頁檔）改回 `no-cache`（每次以 ETag 確認），讓瀏覽器可使用「上一頁快取」；會員頁、登入相關頁與後台維持 `no-store`。`memberNavigation()` 在頁面由快取還原時重新確認帳號。Playwright 的測試瀏覽器強制關閉上一頁快取，實際還原需在一般瀏覽器確認。
- 後台入口（`assets/js/admin.js`）：改為依 `body[data-page]` 對照表只動態載入當頁模組，並與登入確認同時開始下載；原本靜態 import 全部 15 個頁面模組，每頁都會預載整棵後台程式。圖片載入失敗的替代圖改共用 `content-view.js` 的 `fallbackContentImages()`（已檢查的節點不再重複下載）。
- 頁面產生流程共用：新增 [scripts/page-pipeline.mjs](../frontend/scripts/page-pipeline.mjs)（`loadTemplates`、`markActiveNavigation`、`finalizePage`），`build.mjs` 與 `publish.mjs` 不再各寫一套模板載入、主選單選中判斷（原本一邊用對照表、一邊用 if 串）與字型／登入監看／模組預載／版本號步驟；條款預設版本集中為 `policy-pages.mjs` 的 `DEFAULT_POLICY_VERSION`。重構前後 49 個建置頁與 20 個發布頁逐字比對相同（版本號除外）。
- 正式環境：新增 `docker/php/production.ini`（`opcache.validate_timestamps=0`），release 映像改用 `composer dump-autoload --classmap-authoritative`；`compose.production.yaml` 各服務加 `mem_limit`、MariaDB `innodb-buffer-pool-size`（皆可用環境變數調整）、publisher 心跳健康檢查；`operations.py` 新增 `upgrade`（關入口 → 換 app → migration → 重新發布 → open），並以 `tests/test_upgrade_order.py` 驗證順序。compose 註解中的內部代碼改為一般說明。

2026-11-03 換頁速度（使用者回報「頁面切換有點慢」）：
- 原因：HTML 本身約 20 ms 就回來，但每換一頁瀏覽器都要把約 31 個 CSS／JS 逐一向伺服器確認是否變更（no-cache → 304），模擬 100 ms 延遲時頁面約 750–870 ms 才就緒；另外每頁會同時送出兩次 `/api/v1/auth/me`。
- 資產版本號：新增 [frontend/scripts/asset-version.mjs](../frontend/scripts/asset-version.mjs)，依 `assets/`（字型除外）、`scripts/` 內容與頁面清單算出 12 碼版本號；build 與 publish 各自計算得到同一值，HTML 引用的 CSS／JS／圖示（含 modulepreload）加上 `?v=版本號`，build 輸出的模組 import（含動態 import）也一併改寫，同一模組不會因網址不同載入兩份。Nginx 對帶版本號的 `/assets/` 網址回 `public, max-age=31536000, immutable`，不帶版本號的維持 no-cache，404 維持 no-store。
- 發布版本：`NodeContentRenderer::version()` 改為掃描整個 `assets/`（字型除外），CSS 或圖示改動也會觸發重新發布，發布頁引用的版本號才會和建置輸出一致。PHP 程式變更後需重啟 app／publisher（FPM opcache）。
- `build.mjs` 每次先清空 `dist/` 內容再輸出，已刪除的頁面不再殘留。`authApi().me()` 同一時間只送一次請求、共用結果，回應後即清除（[auth-api.js](../frontend/assets/js/api/auth-api.js)）。
- 實測（Playwright，暖快取依序點選主選單 9 頁）：模擬 100 ms 延遲時 DOMContentLoaded 由 738–869 ms 降到 154–340 ms，首次繪製由 368–524 ms 降到 192–384 ms；換頁時不再有 304 確認請求，`/auth/me` 每頁 1 次。
- 原型 `website/css/style.css` 的 `.page{animation:pageIn .55s}` 讓每頁內容由透明淡入 0.55 秒，屬原型設計，本次未更動。

2026-11-03 頁首會員連結恢復原樣式（使用者指定，取代同日的下拉選單）：
- 已登入時恢復原本的文字連結：名稱、我的收藏、更改密碼與底線的登出排成一列（`core/session.js` 的 `.member-menu`）；手機版頁首只留名稱，其餘三項放在漢堡選單底部（`.nav-account`）。「會員名稱 ▾」下拉選單與 `.account-slot` 已移除。
- 主選單不抖動的作法改為：`site-header.html` 的 `.header-actions` 內放一份看不見的「搜尋＋會員登入」（`.header-actions-sizer`）撐住訪客版寬度，實際的搜尋與帳號區（`.header-actions-row`）疊在上面靠右（`states.css`）。寬螢幕的會員連結往左延伸；761–1365px 空間不足時，連結在登入按鈕的寬度內換成兩行，不壓到主選單。實測 320–1440px 各寬度訪客與會員的主選單位置相同，訪客狀態無版面位移；會員只有搜尋圖示會左移一次讓出連結位置。

2026-11-03 頁首帳號區與會員頁導向（使用者回報）：
- 頁首帳號區曾改為固定大小的一格並以「會員名稱 ▾」下拉選單呈現，已依使用者指定改回原本的文字連結（見上一則）；主選單在確認帳號時不移動的要求保留（原本未登入會移 20px、已登入移 73px）。
- 行程、收藏、更改密碼三個會員頁：沒有登入 cookie 且網址不帶參數時，由 Nginx 直接 302 到登入頁（`$member_page_needs_login`），不再先顯示頁面再由 JS 跳轉；帶參數的網址（例如套用路線的待辦）與 cookie 已過期的情況仍由頁面處理。Nginx 設定變更需重啟 web。

2026-11-03 移除「legacy」命名（使用者指定；網站從未上線，沒有舊系統）：
- 資料：attractions／films／news_posts／offers 的 `legacy_id` 改名為 `prototype_id`（客戶原型詳情頁 `?id=N` 使用的項目編號），自動命名的唯一索引一併改為 `prototype_id`；新增 [021_prototype_ids.sql](../backend/database/migrations/021_prototype_ids.sql)，以 `RENAME COLUMN IF EXISTS`／`RENAME INDEX IF EXISTS` 寫成可重跑，既有編號原樣保留。migration 合計 21 份。
- API／快照／示範 JSON：`legacyId` → `prototypeId`，示範店家與優惠的 `attractionLegacyId` → `attractionPrototypeId`；`MerchantRepository::forAttractionPrototypeId()`。
- 轉向：`LegacyUrlResolver`／`LegacyUrlType` 改名為 [DetailUrlResolver](../backend/src/Services/DetailUrlResolver.php)／[PublicContentType](../backend/src/Models/PublicContentType.php)（`idDetailPage()`、`prototypeId()`），單元測試改為 `DetailUrlResolverTest`；Nginx 佔位檔名改為 `__resolve_{place,film,news,offer}_detail__`。轉向結果不變。
- 前端：`openingHoursLines(value, hoursText)` 與營業時間編輯器 `load(value, hoursText)` 的第二參數是缺少結構化營業時間時的文字說明。

2026-11-03 後端路由與舊網址轉向整理（使用者指定）：
- 路由：全部 111 條 API 路由（健康檢查仍由 `public/index.php` 提前處理）集中在 [config/routes.php](../backend/config/routes.php)，依原註冊順序排列；刪除 `favorite-routes.php`、`itinerary-routes.php`、`news-admin-routes.php`、`offer-admin-routes.php`、`recommended-routes.php`、`seo-routes.php`，以及原本寫在 Composition 裡的十二條路由。Composition 只組裝物件，透過 [ApiControllers](../backend/src/Controllers/ApiControllers.php) 一次交給路由表。
- 舊網址：景點、電影、消息、優惠四處重複的舊網址轉向（alias、`*-detail.html?id=`、原型編號（現名 prototypeId）、目標網址檢查）合併為 LegacyUrlResolver（現名 [DetailUrlResolver](../backend/src/Services/DetailUrlResolver.php)），各類型差異（快照鍵、舊頁路徑、優惠 0–2→101–103、`/news/coffee-walk.html` 轉優惠）寫在 LegacyUrlType（現名 [PublicContentType](../backend/src/Models/PublicContentType.php)）；景點的轉向邏輯從 Controller 移到 Service 層。狀態碼、Location 與錯誤內容不變。
- 消息與優惠管理維持各自的 Controller／Service。API 仍為 115 個操作。

2026-11-03 分階段開發遺留整理（使用者指定）：
- 前端：移除原型示範資料層（`data/prototype.js`、`pages/public-details.js` 及未使用的卡片產生器）；`public.js` 只保留導覽、淡入、視差與收藏／加入行程。刪除無人引用的 `member-itinerary.js`、`current-trip-state.js`、`google-summary.js`、`attraction-content-preview.js` 及其測試與樣式。
- 頁面：刪除從未對外提供的 4 個舊 `*-detail.html` 與 14 個範例詳情頁（舊網址仍由 Nginx／後端轉址）；電影、消息、優惠詳情的發布模板移到 `frontend/templates/{film,news,offer}-detail.html`。固定頁 69→48。
- 建置：表單 method、帳號欄位 spellcheck／autocomplete、密碼長度屬性與共用樣式表改寫在頁面原始檔，不再由 build 修補；發布頁樣式表改為一份明確清單；移除每頁內嵌的整份景點 JSON；JSON 跳脫與 escapeHtml 各只保留一份實作。
- 後端：移除 `mustChangePassword`（API；DB 欄位保留）、`legacyImage`、`Route::$allowPasswordChange`、`Challenge::$mode`；發布與認證的相依改為必填；`UtcDateTime`、`Config::termsVersion()`、`Database::open()` 取代重複程式；Repository 改名為 `SeoSettingsRepository`／`SeoOverrideRepository`／`SeoTargetRepository`，路由檔改名為 `favorite-routes.php`、`recommended-routes.php`。發布版本雜湊改為掃描整個 scripts／pages／templates／resources／assets/js/core。
- 樣式：移除未使用的 Noto TC Extended 字型（約 13 MB）與約 30 條原型死規則；功能專屬修正移回各自樣式表；`.admin-toolbar` 統一一份定義；修正收藏頁分頁列沒有樣式。
- 284 JS、124 PHP、PHPStan 通過；API 仍為 115 個操作；全站截圖除刻意統一的後台工具列外一致。

2026-11-03 程式可讀性與效能整理（使用者指定）：
- 註解：前後端、測試、Docker／Nginx 與維運腳本的註解改為一般繁體中文說明原因，不再夾帶 `[S：…]`／`E-xx`／`U-xx` 等代碼；411 則原始帶代碼註解保留在 [CODE-COMMENT-TRACEABILITY.md](CODE-COMMENT-TRACEABILITY.md)，AGENTS.md 與架構文件的註解規則同步更新。migration SQL 因有 checksum 保護未改。
- 格式：前端 JS／CSS／測試以 Prettier 3.9.10（dev only，`frontend/.prettierrc.json`，`npm run format`／`format:check`）格式化；PHP 維持 PER-CS，長參數與陣列改為一行一項。每個檔案都以「去除註解後的語法樹／token 相同」驗證未改動程式行為。
- 樣式表依功能命名：phase2→states、phase3→places、phase4→film-form、phase6→member-actions、phase7→seo-settings、phase9→content-admin、phase10→routes、phase11→planner、phase13→search；未使用的 seo.css 移除。各頁載入順序不變，全頁截圖比對一致。
- 效能：Nginx 對 HTML／API 維持 no-store，`/assets`、`/media` 改 no-cache（ETag 304；2026-11-03 起帶版本號的資產網址改為長期快取，見最上方），字型快取 30 天，並對 CSS／JS／HTML／SVG／XML 啟用 gzip（API JSON 不壓縮）；build／publish 依各頁 import 關係加入 `modulepreload`（`frontend/scripts/module-preload.mjs`）。本機量測：第二頁傳輸量由約 2.3 MB 降到 53–752 KB，首頁 DOMContentLoaded 284→141 ms。Nginx 設定變更後需重啟 web。
- 架構：四份重複的 HTML 跳脫函式改用 `core/content-view.js` 的 `escapeHtml`。
- 297 JS、124 PHP／412 斷言、PHPStan 通過；無 API、DDL、env 或固定頁數量變更。

2026-11-02 U-25文案整理：公開頁與後台中像內部測試的說明改為面向大眾／操作人員的文字（頁尾、會員條款與隱私權政策預設內容、註冊同意、密碼頁、SEO描述、列表空狀態、景點帶入、SEO設定等）。條款版本預設值改為 `2026-11-02`（compose.yaml、.env.example、前後端預設），既有 `.env` 若仍設 `local-development-v1` 需自行更新並重建服務。無API、DDL、依賴或固定頁數量變更；295 JS、124 PHP通過。詳見DESIGN-TRACEABILITY U-25。

[本輪驗收與操作](archive/GOOGLE-IMPORT-CORRECTION-20261102.md)；295 項前端測試及真 Google／隔離 MariaDB／Browser 儲存重載通過，測試資料已清理。

2026-11-02 U-24 更正（取代下方 U-23 的 Google 顯示補位）：在後台確認 Google 候選「帶入欄位」時，列表摘要與景點介紹逐欄判斷；空白才帶入 editorialSummary，已有文字保持原樣，Google 沒有簡介則不填。只有按儲存才透過既有 API 保存。移除後台舊補位預覽與公開頁即時 Google 參考框；保留候選卡的原文及來源以供確認。超過既有欄位容量的原文不截斷、不填入並提示。沒有新 API／DDL／環境變數。既有摘要的網站介紹摘錄規則不在本次變更範圍。普通 Places API 不等於授權永久保存或任意修改 Google 內容；實際匯入保存仍須具備相應內容使用權。舊 content-preview／google-summary API 暫保留相容性，現行表單／公開產頁不再呼叫；下方相關說明屬舊設計紀錄。


2026-11-02 U-24逐列重驗與介面一致性：依原始Excel 30列以真瀏覽器重驗，並以客戶原型對照frontend操作與外觀，修正欄位高度／字級忽大忽小、手機會員頁首擁擠、驗證碼過小、設定兩區連續儲存誤報衝突、保留帳號籠統403、可刪除本人帳號、未發布網址回JSON等差項。112 PHP＋3爬蟲GET＝115 HTTP契約不變；固定頁68→69（新增 `frontend/pages/404.html`）；34表338欄／20 migration、env、依賴不變。280 JS、124 PHP／412斷言、PHPStan通過。逐列結果與保留事項見[逐列重驗與介面檢查](archive/EXCEL-UI-REVERIFICATION-20261102.md)；來源見DESIGN-TRACEABILITY U-24／E-43。

## 本輪新增／變更檔案（U-24／E-43）

- frontend/assets/css/ui-consistency.css：最後載入的一致性樣式；後台基準字級、單行欄位與同列按鈕48px、Serif區塊標題、檔案選擇、營業時間兩欄、路線空狀態、驗證碼尺寸、手機會員選單、行程空狀態。由build.mjs注入所有固定頁、publish.mjs注入發布頁。
- frontend/pages/404.html：站內「找不到這個頁面」；Nginx `error_page 404 /404.html`（含景點／電影／消息／優惠網址解析及列表頁），`/api/` 不攔截仍回JSON；seo-resolver將其列為保護頁（noindex、不入sitemap）。
- docker/nginx/default.conf：404頁、四個網址解析 `fastcgi_intercept_errors`、CSP `script-src 'self'`。Nginx設定於容器啟動時由模板產生，變更後需重啟web。
- backend/src/Policies/PermissionPolicy.php `deletableTarget`、Services/AdminManagement.php：拒絕刪除本人帳號；保留帳號 `admin` 回422 account欄位錯誤。OpenAPI描述同步。
- frontend/assets/js/core/ui.js `flash／consumeFlash`：新增／編輯成功導回列表後顯示一次性訊息；admin.js另於瀏覽器原生驗證攔截時清除過時錯誤。
- frontend/assets/js/core/session.js：手機漢堡選單附加會員收藏／更改密碼／登出。public.js：前後篇縮圖失效改替代圖。auth.js：驗證碼失敗自動換圖、手動換圖清除舊錯誤。
- general-settings.js／seo-settings.js：兩區儲存後以 `settings-section-saved` 事件互相重讀；保護頁SEO覆寫欄位鎖定。offer-merchants.js：店家欄位錯誤逐欄顯示。
- 頁面文字：新增／編輯按鈕依原型（新增內容／儲存修改），列表新增按鈕「新增電影」「新增推薦行程」，路線頁按鈕順序，管理員密碼必填標示，網站設定副標與「重新讀取 SEO 設定」。首頁標題改用原型／SEO目錄標題，城市消息列表標題與目錄一致。
- scripts/validation/delivery.py：固定頁盤點68→69。

2026-11-02資料夾整理：原始69檔與執行環境保留；舊驗收散檔／不同版本Git備份已完整校驗歸檔，掃描映像TAR及可重建快取移除。測試依賴改用`.cache/validation/python`，字彙快照改用`.cache/validation/schemaorg-current-https.jsonld`；安裝清單集中`tools/validation/requirements.txt`，舊`backend/tests/requirements.txt`為相容入口。分類、恢復方式與可刪界線見[資料夾指南](archive/PROJECT-STRUCTURE.md)；歷史文件原artifacts路徑對應歸檔內原路徑，不代表證據被刪除。

2026-11-02最新：依原始Excel七張工作表完成[30列功能核實](EXCEL-FUNCTIONAL-VERIFICATION-20261102.md)，150個不同API／DB案例、四區塊空狀態與Browser操作；修復五類後台刪除後焦點、首頁分區錯誤／重試及首頁消息新分頁一致性。278 JS、122 PHP／409斷言與分層分析通過；正式平台及完整AA基線界線見報告。使用者明確核准後已套用本機8080，不修改主庫業務資料。

本輪新增`frontend/scripts/home-error-view.mjs`由build產出內部`frontend/dist/home-unavailable.response`，Nginx僅在首頁發布gate失敗時以503供應四區塊錯誤，不供舊資料或可索引JSON-LD；它不是新固定頁／API。`frontend/tests/admin-delete-focus.test.mjs`與`home-error-view.test.mjs`驗證取消／成功焦點及失敗畫面；既有`home-publication.test.mjs`加查首頁消息新分頁／編碼。四個管理列表腳本刪除後聚焦現存status，`home-view.js`保留原型三列樣式並補新分頁提示。無新表／migration／env／依賴，115 HTTP／68固定頁／34表338欄／20 migration保持；原始website不變。

操作仍為`docker compose run --rm --no-deps frontend node scripts/build.mjs`、`docker compose exec -T -u www-data app-test php bin/console.php content:publish`及標準Docker測試。Nginx變動需依既有server程序套用；首頁內部錯誤回應的交接見[正式server指南](PRODUCTION-HANDOFF.md)。本輪設定／館舍／自有資料均已還原清理，逐例非敏感證據見[機讀紀錄](evidence/EXCEL-FUNCTIONAL-20261102.json)。

2026-11-02 P0／P1／P2本機重驗完成：Excel16／11／3項分別驗證，補完整P0回歸、14固定套件及168筆有效Browser三尺寸；收藏快速離頁與後台UTC呈現已修復，122 PHP／409斷言、最終267 JS通過。115 HTTP／68頁／34表338欄／20migration保持，正式與AA基線界線見[完整重驗](archive/P0-P1-P2-REVERIFICATION-20261102.md)。以下帶日期紀錄為歷史。

2026-11-01 階段十五本機收尾通過：U-23摘要／介紹補位與E-39首幀字型鎖定已實作；14固定套件全通過，122 PHP／409斷言、最終260 JS、補位47檢查／14契約＋8真DB、真Google與6筆新UI三尺寸／鍵盤通過。真5秒延遲標題像素差0，關閉已重現的晚換字形；不承諾整頁通用零CLS。112 PHP＋3爬蟲GET＝115 HTTP契約，68頁／34表338欄／20 migration，無新DDL／env／依賴。依備份及tree核對規則merge main、保留階段分支；正式平台及server安全仍按U-18／U-21交接，不表示正式上線。見[最終本機驗收](archive/PHASE-15-ACCEPTANCE.md#最終本機收尾補位與字型修正後)及[補位指南](modules/attraction-content-fallback.md)。以下帶日期內容為歷史。

## 本輪新增共用前端檔案（E-41）

- frontend/assets/js/core/pending-writes.js：協調已啟動收藏寫入與站內導覽；member-actions.js等待確認、失敗留頁。
- frontend/assets/js/core/utc-display.js：將UTC資料庫字串明確轉台北顯示；operations-overview.js與member-directory.js共用，列表／摘要／稽核標示台北。
- frontend/tests/pending-writes.test.mjs／utc-display.test.mjs與既有member-intent／member-directory／operations回歸：等待、失敗、跨日與有offset資料。最終267 JS通過。
- backend/tests/attractions_integration.py／content_contract.py／seo_publication_integration.py／phase8_validation.py：舊fixture與路由／公開設定盤點跟上現行schema，完整記錄stderr；不改網站規則。

沒有新增資料表／migration／環境。Docker前端build命令保持；完整回歸與正式差項見上方重驗報告。

## 階段十五追加檔案與操作入口

Controller→Service／Policy→Repository／Adapter分層、PHP API-only與原型殼維持。公開景點／城市卡片／首頁博物館共用AttractionContentPolicy；publicArray增加authoredContent與contentSource，管理欄位保持原值。GoogleSummaryProvider只請求editorialSummary／attributions，AttractionSummary核對已發布版本並沿用限速表；ExternalRequestSlot用MariaDB命名鎖。完整逐檔表見上方指南。

| 範圍 | 新入口／用途 |
| --- | --- |
| 後台新增／編輯景點 | POST /api/v1/admin/attractions/content-preview；create或edit、CSRF、唯讀模擬禁止；小型預覽不儲存 |
| 公開景點／首頁博物館 | GET /api/v1/attractions/{slug}/google-summary；只按伺服器保存的關聯，no-store、失敗保留空狀態 |
| 前端共用 | components/attraction-content-preview.js、google-summary.js與phase3.css；按鈕保留焦點、舊預覽不覆蓋後續輸入 |
| 字型 | assets/js/font-boot.js、fonts.css與font-head.mjs；首幀選字型、晚到不替換；[字型指南](modules/interface-stability.md) |
| 回歸 | backend/tests/content_fallback_validation.py串真DB probe與HTTP契約；frontend/tests新增font-boot／google-summary／attraction-content-preview |

環境仍PHP 8.5／MariaDB 12.3／Docker，本次沿用GOOGLE_PLACES_API_KEY，不重新初始化帳號或資料庫。部署需同版build與content:publish，長駐publisher需restart才能載入新PHP類別。根目錄命令：

```text
docker compose run --rm --no-deps frontend node scripts/build.mjs
docker compose exec -T -u www-data app php bin/console.php content:publish
docker compose restart publisher
```

測試只在yinghua_test，以具名前綴fixture執行content_fallback_validation.py／交付14套件，再phase14_cleanup.py；完整命令與權限／配額界線見補位指南及[交付工具](modules/delivery-validation.md)。正式網域、SMTP、Google專案及server安全保持交接，不因本機功能通過宣稱正式上線。


## 階段十四目前架構與檔案入口

Controller → Service → Repository／DTO維持，PHP只回API。前端沿原型殼，新增邏輯用原生JS元件與既有API客戶端；所有HTTP入口在Composition組裝與OpenAPI列出。

| 頁面／範圍 | API與權限 | 中文逐檔指南 |
| --- | --- | --- |
| /admin/members.html | GET /api/v1/admin/members；members.view；遮罩、查詢、分頁與唯讀摘要 | [會員查詢](modules/member-directory.md) |
| /admin/settings.html／公開政策頁／共用聯絡資訊 | GET/PUT /api/v1/admin/settings；settings.view/edit；general/seo雙版本、同版發布與註冊同意 | [一般設定與政策](modules/general-site-settings.md) |
| /admin/index.html | GET /api/v1/admin/dashboard；dashboard.view加各模組view；GET /api/v1/admin/audit-logs另限root | [儀表板與稽核](modules/operations-overview.md) |
| 共用頁首／字型與 /planner.html | 沿既有會員／景點／行程API；原頁選點、草稿與明確儲存 | [介面穩定與行程操作](modules/interface-stability.md) |

資料表：沿用members、site_settings（固定general與seo）、member_consents、audit_logs及內容表，沒有新migration。計數含既有migration journal為34表338欄，21份migration（021只改欄位／索引名稱）。Docker／PHP8.5／MariaDB12.3維持；字型、OFL授權與子集位於frontend/assets/fonts，來源及hash見[字型清單](FONT-ASSETS.json)，不依賴訪客連線到Google Fonts。

操作仍在專案根目錄執行：

```powershell
docker compose up -d
docker compose --profile test up -d app-test web-test
docker compose run --rm --no-deps frontend
docker compose exec -T -u www-data app php bin/console.php content:publish
docker compose exec -T -u www-data app-test php bin/console.php content:publish
docker compose restart publisher
docker compose run --rm --no-deps frontend node --test tests/*.test.mjs
docker compose exec -T app-test /var/www/tools/vendor/bin/phpunit -c phpunit.xml
docker compose exec -T app-test /var/www/tools/vendor/bin/phpstan analyse -c phpstan.neon --no-progress
```

命名隔離fixture：phase11_fixture.py建立yinghua_test自有帳號；phase14_integration.py／phase14_policy_integration.py驗證會員／設定／權限／同意；phase14_assets.py只驗HTTP資產，不是Browser；phase14_cleanup.py只還原本輪自有設定並清理自有fixture，不碰主庫或root。API測試需要先前已準備的忽略jsonschema依賴路徑，詳測試腳本。正式網域／Search Console等仍按U-21交接。


2026-11-01 最新：階段十三依使用者授權完成全站搜尋，一個公開GET、同版MariaDB搜尋投影、五類分組／篩選／分頁、原型搜尋頁、錯誤／重試／返回。105 PHP API＋3爬蟲GET＝108 HTTP契約，68固定頁／34表338欄／20 migration；無新env／依賴／服務。230 JS／112 PHP（370斷言）、113真整合／28契約、P1 255／181契約與15筆Browser量測通過，測試資料已清理。詳見[計畫](archive/PHASE-13-PLAN.md)、[驗收](archive/PHASE-13-ACCEPTANCE.md)、[逐檔架構指南](modules/site-search.md)。正式差項沿U-21交接，階段十四／十五未開始；下方日期段落保留歷史。

2026-10-31 P1另輪驗收：依原始Excel的11列P1重跑階段9–12；255項真API／181筆契約、369項索引、224 JS／107 PHP與PHPStan／PHPat通過。18頁各三尺寸、網站與原型共108筆版型量測；會員套用後保存並重新登入載回、消息／優惠公開同步及鍵盤流程完成。本輪無新功能缺陷／程式／DDL／環境變更；正式平台依U-21交接，P2未開始。詳見[本輪P1驗收](archive/P1-ACCEPTANCE-20261031.md)，下方日期段落保留歷史。

2026-10-31 最新：階段十二依授權完成JSON-LD、同版robots／sitemap／分片及P1本機整合。104個PHP JSON API保持，另3個Nginx爬蟲GET，共107個HTTP契約；68固定HTML／33表327欄／19 migration，無新DDL／依賴／env。224 JS／107 PHP、P1 255項與181契約回應、索引369項、官方Validator七型別0錯誤／警告、Browser21筆及40個body保留通過。U-21指定網域未定先完成本機，正式Search Console／Bing／分享仍交接；P2階段十三未開始。詳見[計畫](archive/PHASE-12-PLAN.md)、[驗收](archive/PHASE-12-ACCEPTANCE.md)及[架構與檔案指南](modules/search-index.md)。下方帶日期段落保留歷史。

2026-10-31 最新：階段十一已依使用者授權完成本機範圍，包含本人多份行程、整份編輯保存、時間／備註／排序、推薦路線確認套用及P0資料相容。104 API／68固定頁／33表327欄／19 migration，沒有新DDL／環境。81項真實整合、66筆契約回應零錯誤、107 PHP／208 JS、P0收藏69與目前行程100通過；兩頁各1440／390／320實測並與原型對照。詳見[階段十一驗收](archive/PHASE-11-ACCEPTANCE.md)、[計畫](archive/PHASE-11-PLAN.md)及[檔案閱讀指南](modules/member-itineraries.md)。下方帶日期段落保留歷史；階段十二未開始，P1整體尚未簽核。

2026-10-30 最終：階段十已補完內建 Browser 本機驗收；四頁各1440／390／320共12筆實測，另12筆原型比對。公開導覽選中及刪除後焦點兩項已修、實測與回歸通過。PHP99／342、前端200、路線58／45契約回應零錯誤；96 API／68固定頁／33表327欄／19migration。全站導讀與檔案用途見[推薦路線指南](modules/recommended-routes.md)，實際範圍與共用AA基線界線見[階段十驗收](archive/PHASE-10-ACCEPTANCE.md)。本機範圍通過，依備份／完整tree核對程序合併並保留階段分支；完整會員套用仍留階段十一。下方帶日期段落保留歷史。


2026-10-30：階段十依使用者授權於 `phase/10-recommended-routes` 實作推薦路線管理與公開列表；96 API／68固定頁／33表327欄／19 migration。自動化結果見 [階段十驗收](archive/PHASE-10-ACCEPTANCE.md)，程式閱讀見 [推薦路線指南](modules/recommended-routes.md)。內建 Browser 連線不可用，實際介面待驗，尚未合併 main；完整會員套用留階段十一。下方帶日期段落保留歷史。

同日再次複驗：PHP99／342、前端198、路線57／45契約回應及共用消息優惠116／70契約回應均通過；兩站新增範圍10次HTTP通過，自有fixture／session清理。應用程式未改，Browser仍無法連線，實際介面待驗狀態保持；本輪未重跑正式部署／空庫／全站86項清單，見驗收追記。


2026-10-30：階段九本機交付完成：89 API／68固定頁／33表316欄／18 migration，無新表、DDL或環境變數。消息／優惠管理六頁與四類詳情前後篇已串接；PHPUnit94／331、前端190、真API116／70回應／17新增契約0錯誤、路由89、P0受影響消息及優惠各40、清理後68頁／17資產／85 HTTP通過。完整檔案用途、資料流與操作見[消息／優惠管理指南](modules/news-offers-admin.md)、[共用編輯準備](modules/content-editing.md)、[實際驗收與AA基線界線](archive/PHASE-9-ACCEPTANCE.md)。正式server／上游映像事項沿U-18交接；階段十未開始。下方保留歷史狀態。

2026-10-29 階段九實作中：`phase/09-news-offers-admin` 新增消息／優惠管理與四類詳情前後篇；目前89 API／68固定頁，沿用33表316欄／18 migration，無新增資料表或環境變數。PHPUnit94項331斷言、前端178項及首輪17個新增API契約已通過；實際介面驗收仍進行，未宣告完成。最新範圍與檔案用途見 [消息／優惠管理指南](modules/news-offers-admin.md)；[驗收紀錄](archive/PHASE-9-ACCEPTANCE.md) 只記錄實際結果。P0已依使用者授權確認工程界線後合併，正式server／上游映像事項仍保留交接，不等同正式部署認證。

2026-10-29階段九僅規劃：[消息／優惠管理與詳情前後篇計畫](archive/PHASE-9-PLAN.md)已核對Excel及原型；預計復用現有表與Service，補六個後台頁、合作店家必要維護及四類前後篇。17個API／0個新業務表是提案，不列既有成果；目前仍72 API／68固定頁／33表316欄／18 migration，沒有程式、環境或資料變動，未建立階段九分支。總計畫已同步P0最新本機驗收與P1尚未實作狀態。

2026-10-29 AA 最終判定：依 U-20 不以 NVDA 為必要工具；內建 Browser／隔離 Orca 補驗及有效基線逐項覆核後，WCAG 2.1 A／AA 為39通過、10不適用、1已廢止，沒有未處理失敗準則。到期／錯誤摘要焦點、正反向Tab、草稿保留及174項前端回歸通過；[完整50項矩陣與證據界線](archive/P0-AA-FINAL-20261029.md)。不宣稱本輪重跑所有真縮放或取得Orca完整錯誤字句的自動語音；已聚焦的文字錯誤依W3C 3.3.1／4.1.2判定，沒有改寫工具結果。主機讀屏未啟動，測試資料與臨時環境清理；API／DDL不變。這是目前P0工程AA驗收，不是正式server或第三方標章認證；未合併main／未開始P1。下方日期段落保留歷史。

2026-10-29 內建 Browser 補驗完成：40 個原型頁面桌手機成對對照、10 個核心頁面載入後 320px 重排，以及登入、權限、景點／圖片、電影、收藏／行程、SEO、到期草稿與鍵盤操作已實測。修正電影週期沿用範本日期的缺陷，171 項前端回歸通過；[逐項結果、檔案用途與 AA 證據界線](archive/P0-BROWSER-VERIFICATION-20261029.md)。已清理隔離資料並恢復 SEO／TTL；API／資料表不變。新提示的 Windows NVDA 即時完整播報未列通過，完整 AA 及正式 server 簽核不冒稱完成；未合併 main。下方保留歷史結果。

2026-10-29：本輪 P0 背景驗收已完成，PHP 91／前端 171／session 51 項及完整 API／DB 套件通過；原始 69 檔無差異。瀏覽器專用連線不可用，原生控制後又因無法確認 URL 被工具安全機制停止，因此本輪介面與完整 AA 不簽核，不合併 main。具體執行、跨日續驗、工具診斷及清理見 [本輪驗收紀錄](archive/P0-ACCEPTANCE-20261029.md)。沒有應用或資料表變更，仍為 72 API／68 固定頁／33 表 316 欄／18 migration。

2026-10-26：依使用者指示修正 AA-01／AA-02，新增登入到期預警／原頁重新驗證與網站導覽（分類及名稱尋找）。修正與實測範圍見 [AA 差項修正](archive/P0-AA-REPAIRS-20261026.md)。72 API／68 固定頁／33 表 316 欄／18 migration；原型與 Excel 不動。新提示的 Windows NVDA 採證未完成：啟動主機讀屏造成干擾，已依使用者反映停止並確認程序結束，後續不再自行啟動主機讀屏；不冒稱完整 AA 已通過。

2026-10-26 AA 逐項驗收：50 個歷史 A／AA 編號已逐項審查，37 通過、2 不通過、10 不適用、1 已廢止，見 [完整準則表與修正方案](archive/P0-AA-VERIFICATION-20261026.md)。Windows NVDA 2026.2 的完整確認訊息、錯誤、收藏通知與英文切換，以及反向 Tab／模態兩端、16 個受保護頁面的實際 200%／400%、文字間距與直橫向已補驗。新增 `frontend/scripts/language-passages.mjs`，由 build／publish 共用已核對英文標示；`components/dialog.js` 明確循環兩端；phase2.css 補欄位邊界並停止首頁旅行區裝飾。新增兩份功能測試，最終 159 項通過；[元件與檔案說明](modules/frontend-accessibility.md)。仍為 72 API／67 固定頁／33 表 316 欄／18 migration；無 PHP／DDL／環境變數或正式服務變動。登入到期提示／延長及已發布非當週內容的完整尋找途徑未通過，P0 不合併 main／不進 P1。下方保留前輪歷史狀態。

2026-10-26 登入後補驗：會員收藏／目前行程、管理員登入／列表、三種 P0 新增表單及真 API 載入後的權限／SEO 控制項已補驗；確認框標題與刪除說明可手動讀出。`frontend/templates/dialog.html` 新增標準警示角色／modal／說明關聯，仍由原生 dialog 處理開關與取消；[檔案說明](modules/frontend-accessibility.md#2026-10-26-確認框語意補驗)、[逐項結果與保留差項](archive/P0-COMPLETE-VERIFICATION-20261025.md#2026-10-26-登入後與確認框補驗)。152 前端及 67 頁／81 HTTP 最終通過；API／DB／PHP 架構、72 API／67 頁／33 表 316 欄／18 migration 不變。消息／優惠後台仍為 P1；自動播報／反向 Tab／Windows NVDA／完整 AA 不提前列通過。沒有新增應用環境變數或正式部署服務。

2026-10-26：依 U-19 新增 `tools/accessibility/` 的獨立背景讀屏桌面與採證腳本，檔案用途、Docker／暫存／secret、啟停及 Python 命令見 [隔離讀屏指南](modules/isolated-accessibility.md)。Orca／Firefox 已實測首頁結構、登入必填警示、註冊欄位與未勾選同意框；[具體範圍](archive/P0-COMPLETE-VERIFICATION-20261025.md#2026-10-26-隔離讀屏補驗) 不等同 Windows NVDA 或完整 AA。網站來源／架構、72 API／67 頁／33 表 316 欄／18 migration 不變；新增工程驗收工具不納入正式網站映像，主站 `.env` 不改。Windows NVDA 停止，測試 fixture 已清理；階段八仍不合併 main／不進 P1。下方日期段落保留當時結果。

2026-10-26 01:20：[實際 400% 補驗](archive/P0-COMPLETE-VERIFICATION-20261025.md#2026-10-26-0120-實際-400-縮放補驗) 完成 31 公開／認證頁、短視窗選單 Tab／Escape 與表單聚焦，連同前輪 200% 共 64 次主要觀察。原服務停止後已恢復既有容器，資料表／API／架構／程式來源不變；沒有新增套件驗證成果。NVDA 維持停止、實際讀屏仍待工具視窗存取許可；不宣告完整 AA，不合併 main／進 P1。

2026-10-25 22:02：[實際 200% 補驗](archive/P0-COMPLETE-VERIFICATION-20261025.md#2026-10-25-2202-實際-200-縮放補驗) 完成 31 個公開／認證頁的真瀏覽器縮放巡查及表單 Tab 捲動，未改應用檔案／架構／API／資料表。NVDA portable 啟動後因視窗存取授權逾時而未取得讀屏證據，已停止自有程序；400% 尚待使用者調整後量測，不列通過。前輪測試、架構與 Docker 交接範圍保持。

2026-10-25 21:40：依 U-18，既有 server 為交付目標，Docker CVE／主機設定另列部署事項，不阻擋本輪網站功能驗收。[介面補驗](archive/P0-COMPLETE-VERIFICATION-20261025.md#2026-10-25-2140-網站介面補驗) 含 50 頁／78 次觀察、鍵盤與錯誤關聯、12 類圖片文字對比界限；Node 152 項與 67 頁／81 HTTP 通過。style.css 調整圖片漸層／caption，phase2.css 以正常排版避免首頁 CTA 重疊，並以 museum-photo::after 防止發布圖片蓋掉遮罩。無新 API／資料表／migration／環境變數，仍 72 API／67 頁／33 表 316 欄／18 migration；原始 website 不動。實際縮放／讀屏的最新狀態以該節為準，不以工具安裝冒充通過。

2026-10-25 20:15：V-01 文字對比、V-02 四頁返回列表、V-03 Google 解析欄位錯誤已依授權修正，另補 320／390 頂列換行及欄位寬度。117 次觀察、5,105 可計算樣本低對比候選 0，Node 152 項、67 頁／81 HTTP 通過；完整 AA／正式差項仍保留。見 [修正追記](archive/P0-COMPLETE-VERIFICATION-20261025.md#2026-10-25-2015-修正後結果)。自身修正歸入階段八完整驗收提交，不新增進度提交／不合併 main。

檔案用途：build.mjs 依固定路由選擇四頁返回標籤；core/ui.js 的 formErrors 支援欄位群組；pages/attraction-form.js 映射解析錯誤、與保存網址分流，解除 busy 後聚焦；景點兩表單標記查詢欄；film-form.js 沿用 create-only 去向。style.css／phase2.css 管對比及窄螢幕，回歸在 attraction-form／film-form／seo-build 測試。無新程式檔、API、表、migration 或環境變數，仍 72 API／67 頁／33 表 316 欄／18 migration；PHP 分層保持。

2026-10-25 15:21 全 P0 重新驗證：以 `343bd7e` 對照原始 Excel 七工作表／16 列 P0，40 個原型對應頁完成桌面與手機共 80 組比對，並驗 320 CSS px、真 Google／選檔／發布、電影場次、會員保存、root 唯讀與 SEO 分類。**功能測試通過，但仍有一般文字對比、四個景點／電影表單頂列返回動線、Google 網址欄位錯誤關聯三類差項**；完整 AA／正式環境與映像風險未關閉，未合併 main。各頁範圍與重現位置見 [本輪完整驗證](archive/P0-COMPLETE-VERIFICATION-20261025.md)。

本輪無應用／前端／DDL／Docker 或環境設定變更，仍為 72 API／67 固定頁／33 表 316 欄／18 migration，主／測試庫查核一致。PHP 91／318、Node 150、67 頁／81 HTTP、head 74、冷部署 17／還原 25／回退 9 重跑；Rector dry-run 40 建議未套用。新增文件只負責驗證追溯，架構與檔案用途仍依本導讀及模組指南。測試資料、兩張 SEO 表與隔離 root 已清理／復原，三個自有部署演練專案移除。下方各輪歷史不作最新全綠結論。

2026-10-25 P0 差項修正：三類小字對比、電影頁館舍同源、列表 SEO 已修；alias 排序與 API／HTML gate 安全診斷已改善，發布 worker 已重載。**PHP 91／318、Node 150、PHPStan／PHPat、格式 261、head 74、67 頁／81 HTTP，以及真 DB 順序 7／診斷 8 項通過**。資料仍為 72 API／67 頁／33 表 316 欄／18 migration，沒有新路由／DDL／環境變數；原始 website 保留，合成資料與設定已清理／復原。完整檔案入口、測試命令與限制見 [本輪修正](archive/P0-IMPROVEMENTS-20261025.md)，分模組見 [無障礙](modules/frontend-accessibility.md)、[電影](modules/films.md)、[SEO](modules/seo-settings.md)、[運維](modules/operations.md)。歷史那一筆 503 不追認根因，完整 AA／正式環境及映像風險仍保留，P0 未宣告通過、不合併 main。下方保留修正前各輪歷史。

新增檔案用途：`frontend/assets/js/core/list-seo.js` 讀同版公開 context 並共用 resolver；`backend/src/Exceptions/PublicationPending.php` 限定內部診斷；`tests/Unit/PublicationOrderTest.php`／`PublicationPendingTest.php` 測誤判與秘密邊界，`tests/publication_alias_order.php`／`publication_diagnostics.py` 測真 DB 及 API／gate。架構仍為前端 View／PHP API 的 Controller→Service→Repository；沒有將 SQL 移入 Controller。修改 PHP 發布程式後須重啟長駐 publisher，命令見上述運維指南。

2026-10-25 全 P0 複驗：來源 `1fd4f14`，真 DB／HTTP、PHP／JS 品質、67 頁完整性、隔離 TLS 冷部署 17 項、還原 25 項與回退 9 項已重跑。**P0 仍未通過**：小字對比、電影頁館舍時間寫死、消息分類蓋掉 SEO 覆寫，以及首次優惠 503 尚未定位，見 [本輪完整報告](archive/P0-REVERIFICATION-20261025.md)。Semgrep／Gitleaks 本輪已執行並逐筆判讀，映像安全／人工 AA／正式條件仍保留。本輪沒有改應用程式、前端或資料表；維持 72 API／67 固定頁／33 表 316 欄／18 migration，未合併 main／未進入 P1。下方帶日期內容為各輪歷史，不能當成最新全綠結論。

本輪驗證工具用途更新：[mail_failure.py](../backend/tests/mail_failure.py) 自建並清理 active 隔離會員，避免依賴其他套件最後一筆資料；[offers_public_integration.py](../backend/tests/offers_public_integration.py) 在讀列表欄位前驗 HTTP 狀態，非預期回應保存安全 code／requestId、不自動重試。環境、架構、API 與 migration 未變；仍用 Docker 執行 PHP／Composer，測試不還原主庫。具體結果、命令入口、具名清理及證據索引見上述報告。

2026-10-24 階段八已依授權於 `phase/08-p0-validation` 實作：匿名health、DB故障503、獨立正式發布版／TLS、備份還原／回退及手機導覽焦點修正。**72 API／67固定頁／33表316欄／18 migration；無新DDL。** 本輪隔離部署、25項還原及9項回退通過；最終67頁／14資產／81HTTP完整性零差異，health18項／6契約、PHP87／302斷言、Node145、PHPStan10／PHPat及格式檢查通過。主／測試已更新運行，12個演練project及具名UI fixture已清理。完整人工AA、上游映像風險與正式環境仍待驗，P0界線未通過，保留階段分支、尚未合併main或進入P1。實際結果見 [階段八紀錄](archive/PHASE-8-ACCEPTANCE.md)、[16列來源矩陣](archive/P0-ACCEPTANCE-MATRIX.md)、[安全差項](archive/PHASE-8-SECURITY-REVIEW.md)；下方較早帶日期段落為當時紀錄。

2026-10-24 階段八8E介面修正：共用公開／後台手機選單關閉時設inert，Escape將選單內焦點返回按鈕，桌面斷點恢復可操作。修正原本能Tab到被裁切連結的實際問題，維持原型CSS與67頁；145項Node與Brave390×844重驗通過。函式及命令見 [導覽／鍵盤閱讀指南](modules/frontend-accessibility.md)。仍為72 API／33表／18 migration；整合、正式條件與安全差項尚未全驗收，不宣告P0全部完成。

2026-10-24 階段八8D：DB＋media＋release備份、全空目標還原、復原憑證撤銷與open／rollback閘門已建立。RecoveryRepository集中SQL、RecoveryMediaInspector查引用與hash、bin/recovery.php僅CLI組裝；25項隔離還原／拒絕流程及壞tar邊界通過，具體資料量與保留限制見 [運維指南](modules/operations.md#8d-備份復原與流量閘門)。沒有主庫還原、新HTTP／schema或頁面；整合／畫面與版本回退演練仍進行中。

2026-10-24 階段八8C：獨立正式Compose、app/web發布target、同版manifest、TLS／308／安全Cookie／日誌輪替、受控建置與核心／公開readiness CLI已建立；隔離新volume已驗空庫／migration／首版發布、DB暫停／FPM停止／worker到期及重啟。細節與尚待乾淨發布重演見 [運維模組](modules/operations.md#8c-正式封裝與隔離部署)，命令與設定見 [部署指南](P0-OPERATIONS.md)。本機Compose／DB查看port保留；72 API／67頁／33表／18 migration不變，備份還原與完整驗收仍進行中。

2026-10-24 階段八8B：匿名核心健康 `GET /api/v1/health` 已建立，**72 API／67固定頁／33表／18 migration**。HealthController → ApplicationHealth → 可替換 DatabaseHealthProbe／PDO Adapter，HealthReport 安全輸出；精確入口先於 eager DB 組裝，錯誤503不洩漏診斷、HEAD／405／無Cookie已驗。17項真HTTP／MariaDB及六種CLI故障、PHP87項／302斷言與PHPStan10／PHPat通過，詳見 [運維指南](modules/operations.md#8b-核心健康-api)。新增非機密 APP_VERSION，本機development；正式必填，尚無正式部署或整階段通過。

2026-10-24 階段八依使用者授權從最新 main 建立 `phase/08-p0-validation`，8A 診斷進行中。CSRF 增加有限五秒時鐘回撥預算與無秘密的拒絕原因日誌，12項受控邊界測試通過；100輪修正前觀察均為預期422，未將歷史不明註冊失敗宣稱已解決。檔案用途與命令見 [運維指南](modules/operations.md)。尚無新表／migration／HTML；整階段、部署與正式環境未驗收。以下較早段落為當時紀錄。

2026-10-24 階段八僅規劃：新增 [P0整合驗收與部署基線細節計畫](archive/PHASE-8-PLAN.md)，列出16項P0儲存格對照、優先診斷的偶發問題、一個預計health操作、正式封裝／TLS、隔離備份還原／回退及24項固定驗收。尚未授權實作或建立分支；目前仍71 API／67固定頁／33表／18 migration，沒有變更程式、環境或資料庫。 未建立階段八模組閱讀指南或通過報告冒充成果；實作後仍須逐功能維護本導讀。

2026-10-24 階段七完成本機驗收：**71 API／67 固定頁／33 表／316 欄／18 migration**。SEO 全站／逐頁／範本／單筆設定、公開 head 同版、權限／版本／audit 與開發政策頁已交付；桌面／手機與失敗流程見 [階段七驗收](archive/PHASE-7-ACCEPTANCE.md)。下方 7A–7D 與較早日期保留當時紀錄；最新功能及外部差項以本段和驗收為準，階段八未開始、P0 尚未完成。

2026-10-24 階段七 7D：新增公開開發條款／隱私說明與註冊連結，67 固定頁；仍為 71 API／33 表／18 migration。沿用註冊既有說明與 TERMS_VERSION，不修改歷史同意紀錄；正式文案待業主提供。policy-pages.mjs 處理版本文字輸出，policy.js 共用導覽，ContentPublication 保存同版 policyVersion，Compose frontend 轉傳既有 TERMS_VERSION。用途與驗證命令見 [政策說明指南](modules/seo-settings.md#7d-開發條款與隱私說明)。整階段與實際畫面驗收進行中。

2026-10-24 階段七 7C：公開 settings GET 與同版 SEO head 已建立，合計 71 個操作／33 表／18 migration／65 固定頁。PublicSiteSettings 只回成功 generation 的白名單，SeoPublication 過濾草稿覆寫，ContentPublication／NodeContentRenderer 納入設定與模板；build／publish／管理預覽共用 seo-resolver.js／seo-head.mjs。Nginx 保留公開 gate，管理／認證／私人 shell 不受發布故障阻擋；Compose frontend 僅轉傳既有 APP_URL，沒有新變數或服務。檔案、60 項／12 契約及命令見 [SEO 指南](modules/seo-settings.md#7c-同版公開設定與-html-head)。條款頁與整階段／實際畫面驗收尚未完成，完整 JSON-LD／索引與一般設定仍未開放。

2026-10-24 階段七 7B：管理 SEO GET／PUT、settings 原型表單、版本／權限／同交易 audit 已建立，API 共 70；主庫備份後升級，主／測試均 33 表／18 migration，固定頁仍 65。Controller → SeoSettings → SeoSettingsRepository／SeoOverrideRepository；SeoTargetRepository 批次目標與權限篩選、SeoSettingsResult 限定輸出；前端 seo-settings-api／seo-fields／seo-settings／phase7.css 分別包 HTTP、欄位、互動與必要樣式。詳細檔案與 74 項／32 契約、5 項表單行為驗證見 [SEO 指南](modules/seo-settings.md#7b-管理設定與原型表單)。公開 GET／head 整合仍在 7C，不把 publicationReady=false 當已完成發布，整階段未驗收。

2026-10-24 階段七 7A：依授權建立 `phase/07-seo-settings-head`。017 新增 site_settings／content_seo，DTO／enum／Repository／URL 與目標規則、共用頁面 catalog 及資料層測試已建立，見 [SEO 閱讀指南](modules/seo-settings.md)。測試庫 33 表／18 migration，主庫暫為 31／17；API 仍 68／固定頁 65，尚無可用 SEO API 或設定 UI。空庫／保留會員內容收藏行程升級、重跑、14 項 rollback 與輸入單元通過。沒有新增 Docker 服務、套件、port 或環境變數，整階段仍在實作。

2026-10-24 階段七規劃：新增 [全站 SEO／HTML head 計畫](archive/PHASE-7-PLAN.md)，含預計頁面、三個 API、site_settings／content_seo、分層／同版發布、欄位來源與固定驗收清單。**尚未實作或建立階段七分支**，因此目前仍為 68 個操作／31 表／17 份 migration／65 頁；沒有安裝或變更 Docker 環境，也沒有新增可用 SEO API。正式網址／文案等外部待辦及 P1／P2 界線見計畫。

2026-10-24 階段六完成本機驗收：68 個 API／31 表／297 欄／17 份 migration／65 個固定頁。Brave 已實測登入返回、收藏與行程分頁／保存／移除／空值／下架／409／換帳號返回，以及桌面 1440×900／手機 390×844／鍵盤；精確證據與未驗情境見 [階段六驗收](archive/PHASE-6-ACCEPTANCE.md#瀏覽器驗收)。修正愛心可及性、美食分類載入、長名稱換行及登入／註冊意圖保留。最後前端 129 項、建置 65 頁／77 個 HTTP 完整性零差異；PHP／MariaDB 未修改，沿用已列明的後端複驗。先前間歇事件仍保留，未宣稱根因消除。無新增服務、套件、port 或環境變數；階段七／十一未開始。下列帶日期段落是當時進度，非當前待辦。

2026-10-23 20:21 定點複驗：收藏／行程／發布／重建／優惠／首頁六組共 307 項、PHP 69 項／270 斷言、Node 126 項、資料層／升級、PHPStan／格式與 65 頁／77 個 HTTP 完整性再次通過，未修改應用程式。詳見 [階段六複驗](archive/PHASE-6-ACCEPTANCE.md#2026-10-23-2021-再次定點複驗)；本輪沒有重跑全部舊模組契約或依賴 audit。實際瀏覽器工具仍無法啟動，畫面待驗及先前間歇事件保留，尚未合併 main。

2026-10-23 階段六目前交付：收藏與基本行程已在階段分支建立，68 個 API／31 表／297 欄／17 份 migration／65 個固定頁；自動化結果與剩餘實際畫面驗收見 [階段六紀錄](archive/PHASE-6-ACCEPTANCE.md)。PHP 69 項／270 斷言、前端 126 項，收藏／行程／容器重建／五輪發布與既有模組契約已有實際結果。瀏覽器工具尚未恢復，並保留尚無穩定重現原因的間歇事件，不宣告整階段完成或合併 main；階段七與十一未開始。沒有新增 Docker 服務、套件、port 或環境變數，原始網站／規格保持完整。下方帶日期段落保留當時狀態。

2026-10-23 階段六 6C：新增三個目前行程 API，合計 68 個操作。`CurrentItineraryController` → `CurrentItinerary` → `ItineraryRepository` 負責本人讀取、加入／移除、父子交易與版本衝突；`CurrentItineraryPage`／`ItineraryWriteResult` 限定輸出。前端 `current-trip-state.js` 管保存版本、舊回應與身分變更，`pages/planner.js` 管讀取／確認／保存提示，`components/member-itinerary.js` 沿用原型站點版型。`member-resources-api.js`／`member-actions.js` 共用收藏與加入入口。無新增 migration、環境變數或服務；31 表／17 份 migration、65 個固定前端來源頁。命令及資料流見 [會員指南](modules/member-resources.md#6c-目前行程與基本保存)。完整階段驗收仍在進行，尚未合併 main。

2026-10-23 階段六 6B：四個本人收藏 API、愛心／登入待辦與 favorites.html 已建立，API 共 65；主／測試庫皆 31 表／17 份 migration，固定前端來源 65 頁。`config/favorite-routes.php` 為會員資源專用路由，PHPStan 已納入分析；`MemberFavorites`／`MemberAttractions` 分別管理本人交易與公開投影，`member-resource-state.js`／`member-actions.js` 共用狀態與操作，`pages/favorites.js` 控制本人列表。完整檔案／命令／驗證見 [會員指南](modules/member-resources.md#6b-收藏-api-與共用愛心)。基本行程與整階段驗收仍在進行，不標階段六完成。

2026-10-23 階段六 6A 共用準備：使用者已授權實作，分支為 `phase/06-favorites-basic-itinerary`。015／016 新建收藏／行程三表與本人唯一約束，`MemberAccess` 重查會員，`FavoriteRepository`／`ItineraryRepository` 集中本人資料 SQL；DTO、檔案用途與命令見 [會員資料閱讀指南](modules/member-resources.md)。測試庫已升級為 31 表／17 份 migration，主庫暫仍 28／15；API 仍 61，尚未接入收藏或行程頁面。空庫／保留資料升級、重跑及 25 項持久層檢查、輸入單元與靜態分析通過，不等於階段六完成。

2026-10-23 階段六僅規劃：新增 [收藏與基本行程細節計畫](archive/PHASE-6-PLAN.md)，包含來源儲存格、預計頁面／七個 API／三張表／兩份 migration、本人所有權、重送與並行、下架資料及待驗清單。僅更新工程文件，backend／frontend／schema／Docker 與目前 61 個 API／28 表／15 份 migration 不變；階段六尚未實作，完整旅程編排仍在階段十一。`PHASE-6-PLAN.md` 是設計，未建立閱讀指南或驗收通過紀錄冒充成果。

2026-10-23 固定清單複驗：結果見 [本輪紀錄](archive/RECHECK-PHASES-1-5-20261023.md)，後續依 [V01–V18 清單](archive/VERIFICATION-CHECKLIST.md) 記錄狀態。修正景點編輯確認重載後仍保留待上傳圖片的問題，新增取消／成功／失敗重試的行為回歸；前端目前 104 項通過。`backend/tests/frontend_integrity.py` 以 Python 標準函式庫檢查建置後各頁的 main／ID／label、資產與靜態模組引用，選用 `--base-url` 做本機唯讀 HTTP 驗證；它不驗證後續功能或逐像素顯示。執行 `docker compose run --rm --no-deps frontend` 後，再執行 `python backend/tests/frontend_integrity.py --base-url http://localhost:8080`。本輪沒有新增 API、資料表、migration、環境變數或階段；PHP／資料庫架構維持既定分層。

2026-10-23 階段一至五複驗：已重驗認證／權限、景點／媒體／地圖、電影／場次、公開消息／優惠／首頁及環境；代表頁桌面／手機原型對照與實際表單操作見 [複驗紀錄](archive/REVIEW-PHASES-1-5-20261023.md)。修正選填空值的公開排版、手機電影重載按鈕及頂列連結斷行。無新增 API、資料表、migration 或環境變數；仍為 61 個操作／28 表／十五份 migration，階段六起未開始。`content-cards.js`／`scripts/publish.mjs` 負責瀏覽器／預先產生卡片與空值顯示，`phase2.css`／`phase4.css` 補充實際操作狀態，`attraction-publication.test.mjs` 驗證真實 HTML 產物。更新後執行前端建置與 `content:publish`，不手改 dist。

2026-10-22 階段五完成本機實作與驗收：**公開消息、優惠／店家受控維護及首頁同版整合**。目前 28 表／276 欄、十五份 migration、61 個 API 操作。首頁與消息／優惠的分類、有效期、分頁及基本閱讀頁讀真實資料；受控 CLI 支援預覽、保存、圖片匯入與故障重試。本機已明確匯入原型四篇消息／三項優惠，正式內容與圖片權利仍待確認。結果及界線見[階段五驗收](archive/PHASE-5-ACCEPTANCE.md)，逐檔用途見[公開內容閱讀指南](modules/public-content.md)。

2026-10-22 先前規劃紀錄：[公開消息／優惠與首頁細節計畫](archive/PHASE-5-PLAN.md) 列出原型頁面、欄位來源、七個預計公開 API、四張新表與既有店家表擴充、CLI 維護、分層及驗收。規劃提交當時只有文件，為 54 個 API／24 表／十二份 migration；實作後最新狀態見上方進度。

2026-10-22 Compose 整理：保留單一 compose.yaml，使用 x-php-common／x-web-common 統一共用設定，FPM 健康檢查只套用 app／app-test；publisher 以 CLI running 狀態啟動。測試資料庫／媒體／發布位置仍明確分開。設定展開比對、完整啟動與隔離驗證見下方「Compose 閱讀與啟動」；沒有新增業務功能或 migration。

2026-10-22 PHP 安全維護：基底更新為官方 php:8.5.12-fpm-bookworm 並固定 digest，詳見 [來源與驗證紀錄](archive/PHP-SECURITY-UPDATE-20261021.md)。官方 amd64 映像已發布，臨時原始碼編譯配方及輔助工具已移除；原專案擴充／runtime／development 階段保留。這是環境維護，階段五仍未開始。

2026-10-21 修正補充：已處理發布紀錄故障的 202 回應、僅新增權限的登入／側欄／保存後狀態及電影介紹原型對齊；沒有新增 API、資料表、migration 或階段。最新證據與保留差異見 [規劃與實作複查的修正紀錄](archive/REVIEW-PLAN-IMPLEMENTATION-20261020.md#2026-10-21-修正與驗證)。新增 `backend/tests/publication_failures.py` 負責隔離發布故障回歸；`frontend/tests/admin-navigation.test.mjs` 驗證權限導向。`core/session.js` 集中 `adminNavigation`／`adminDestination`／`adminReturnTo`，各表單共用。階段五以後仍未開始。

2026-10-20 階段四完成本機實作與驗收：電影／場次／歷史網址三表、公開電影 API／片單／詳情／首頁同版發布、後台電影／海報 CRUD 與多場次編輯。主庫與測試庫現為十二份 migration、24 表／211 欄、54 個 API 操作。10 月 19 日最終驗證：整合 619 項、PHP 55 項／233 斷言、Node 88 項，獨立契約零差異，桌面／手機及隔離容器重建持久化已驗。實際證據與界線見 [階段四驗收](archive/PHASE-4-ACCEPTANCE.md)，新檔用途及操作見 [電影指南](modules/films.md)。U-16 只檢查日期及開始時間重複，結束時間後續再議。主庫四部原型電影沒有完整放映年份，未猜測或建立場次。以下較早日期的 21 表／十份 migration 是當時紀錄。

2026-10-19 業主要求校正：補回 Excel E20 的既有範例景點試填與 G6 電話流程。最新逐條來源、實作／驗證及正式交付差項見 [階段二、三業主要求對照](archive/PHASE-2-3-OWNER-ALIGNMENT.md)。本輪 14 組整合共460項、PHP44項／213斷言、Node73項及獨立契約零差異；原先完整檢查沒有覆蓋這兩個漏項，不能當成全面符合 Excel 的證明。現在45個API操作、十份migration、21表181欄。

2026-10-19 再次完整檢查：7 項問題已校正；445 項整合／故障復原檢查、PHP 41 項及 Node 68 項通過。獨立 OpenAPI 驗證未再發現格式差異。新增 008 固定身分逐位元組保護，當次九份 migration。範圍、重現命令與限制見 [再次檢查報告](archive/FULL-RECHECK-20261019.md)。

2026-10-18 U-15 更新：確認密碼已更正為必填且一致（註冊／重設／更改／CLI）；使用 Input.confirmedPassword 與共用前端比對，沒有新增資料表、migration 或 API。先前選填的驗證紀錄不代表現行政策。

2026-10-18 規格校正已交付：回溯發現的 R-01–R-07 已處理；安全政策經 U-13 明確同意，掃描範圍依 U-14 定案。355 項隔離整合驗證、主庫保留資料升級及目前行為見 [Excel 基準校正](archive/SPECIFICATION-ALIGNMENT.md)，逐表對照見 [資料庫對照](DATABASE-SPECIFICATION-MAP.md)。以下較早日期的測試保留當時結果，不當作現行政策。

**2026-10-18 修正補充：** 已修正圖片說明保存、原型順序／文案標籤、營業時間欄及座標約束，並補齊景點表單失敗／競態測試。修正結果及驗收界線見 [PHASE-3-REVIEW-PLAN.md](archive/PHASE-3-REVIEW-PLAN.md)。

更新日期：2026-10-24。這份文件是目前專案的閱讀入口：先了解網站如何執行，再查每個檔案負責什麼、資料存在哪裡，以及修改功能時該從哪裡開始。上方帶日期的段落是各次交付紀錄，以下檔案表維持目前狀態。

2026-10-18先前完整複查（當次紀錄）：[階段二、三完整複查報告](archive/FULL-REVIEW-20261018.md)。PHP37項／182斷言、前端64項及396項整合檢查通過；64頁路由／資產／imports與桌面／手機Google原文流程已核對。該次複查未新增業務功能、API、表或migration；本輪基準校正則新增005–007，詳見上方校正交付。環境復原及仍未實作的內容補位見該次報告。

規格基準（U-12，2026-10-18）：原始 Excel 為功能與限制的依據；欄位清單不等於必填清單，文件未定義處不自行加上業務限制。「建議／草案」維持原本性質，使用者指定與必要技術設定分別標 U／E。詳細規則見 [開發流程](DEVELOPMENT-WORKFLOW.md#規格基準u-122026-10-18)。該次基準設定僅記錄作業準則；其後本輪已同步校正前後端、API 契約與資料庫，實際範圍見上方交付連結。

內容補位進度：[景點摘要與介紹自動補位計畫](archive/ATTRACTION-CONTENT-FALLBACK-PLAN.md)（U-11／E-22）。兩欄已完成選填，可留空新增／編輯／發布，空值以空字串保存；自動補位（自家介紹摘錄、預覽及 Google 即時原文備援）依使用者2026-10-18指示延後，保留計畫待後續安排；Google 仍僅供後台參考。

**目前完成階段二至五：認證／權限／唯讀模擬、景點／店家、圖片／地圖、電影／多場次、公開消息／優惠及首頁同版發布。** 景點、博物館、電影、消息／優惠與首頁對應區塊讀真實資料；其他未實作業務仍為展示快照或待開放。頁面存在不代表該模組已串接資料庫。完整階段規劃見 [實作計畫](archive/IMPLEMENTATION-PLAN.md)，實際驗證見 [階段二驗收](archive/PHASE-2-ACCEPTANCE.md) 、[階段三驗收](archive/PHASE-3-ACCEPTANCE.md) 與 [階段四驗收](archive/PHASE-4-ACCEPTANCE.md)。

來源標記沿用 [設計來源表](DESIGN-TRACEABILITY.md)：S 是規格要求，D 是規格中的建議草案，U 是使用者指定，E 是工程補充。本案 PHP＋MariaDB＋Docker、原生 JS／可用 jQuery 來自 U-01；API-only、易讀分層及參考 Spring Boot 來自 U-02。以下目錄、類別、工具與容器拆法屬工程實作，不冒稱 Excel 指定。

2026-10-17 依 U-08 重排後續計畫；本文件以下的檔案、API、資料表及操作描述仍是實際已存在內容，沒有把規劃寫成實作。

| 範圍 | 階段與界線 | 實際狀態 |
| --- | --- | --- |
| 共用準備 | 1 | 已完成 |
| P0 | 2–8；8 為整合驗收 | 本機工程及有效AA基線已完成；正式server交接維持既有範圍 |
| P1 | 9–12；12 為整合驗收 | 9–12本機工程範圍完成；正式平台依U-21待網域／帳號，見PHASE-12-ACCEPTANCE.md |
| P2 | 13–15；15 為全站交付 | 未開始；搜尋、會員查詢、一般設定／營運與最終交付 |

P0 收藏／加入行程的必要保存排階段 6，完整多份旅程編排排階段 11。SEO 設定與 HTML head 排 P0 階段 7，JSON-LD／動態 sitemap 排 P1 階段 12。完整相依與原型頁對應見實作計畫及 [需求對照](archive/REQUIREMENTS-MAP.md)，不是本次新增功能。

## 階段完成狀態（原 AGENTS.md）

- 使用者已授權實作階段三、四；認證／權限、景點／媒體／地圖／發布及電影／多場次已完成本機驗收，實際範圍見各階段驗收文件。階段五已於 2026-10-22 依授權實作並完成本機驗收，見 docs/PHASE-5-ACCEPTANCE.md。階段六已依授權於 phase/06-favorites-basic-itinerary 建立收藏／目前行程，2026-10-24 完成本機驗收，範圍與限制見 docs/PHASE-6-ACCEPTANCE.md；合併前須備份並核對完整內容。階段七已另行授權並完成本機範圍；階段八已由使用者另行授權，於phase/08-p0-validation實作health／發布部署／復原及整合驗證；完整人工AA／正式環境與安全差項仍待驗，未合併main，見docs/PHASE-8-ACCEPTANCE.md。階段九以後未開始，P0界線通過後依後續授權處理。U-16 只檢查放映日期及開始時間，結束時間延後。狀態與驗收只記錄實際結果。
- 階段七已於 2026-10-24 依使用者授權在 phase/07-seo-settings-head 實作，7A–7E已完成本機範圍，見 docs/PHASE-7-ACCEPTANCE.md；不因前一條歷史狀態而視為未授權。範圍見 docs/PHASE-7-PLAN.md／docs/modules/seo-settings.md，本機驗收後依規則備份、核對完整 tree 並合併；正式差項與既有偶發驗證限制如實保留。
- 2026-10-30 最新狀態：階段八本機P0工程界線通過後已合併；階段九另依使用者授權於phase/09-news-offers-admin完成消息／優惠管理及四類詳情前後篇，本機驗收與基線／正式交接界線見docs/PHASE-9-ACCEPTANCE.md。89 API／68固定頁／33表316欄／18 migration；每功能提交與合併備份規則維持。階段十以後未開始，不因舊歷史的「階段九未開始」誤判本輪授權。
- 2026-10-30 階段十最新：使用者另行授權，在 phase/10-recommended-routes 實作路線 CRUD／排序／公開列表，96 API／68固定頁／33表327欄／19 migration。自動化結果見 docs/PHASE-10-ACCEPTANCE.md；Browser 連線失效，實際桌面／手機／鍵盤待驗，未合併 main。完整路線套用與會員旅程仍屬階段十一，尚未授權實作。保留原始69檔及既有隔離檔刪除差異。
- 2026-10-30 階段十最終：內建 Browser 已恢復並完成新增範圍實際驗收；四頁各1440／390／320共12筆，另12筆原始website比對。導覽選中與刪除後焦點已修及實測；PHP99／342、前端200、路線58／45契約回應零錯誤。96 API／68固定頁／33表327欄／19migration。本機範圍通過，備份與tree核對後依規則合併；既有正式及共用AA證據界線見docs/PHASE-10-ACCEPTANCE.md。使用者本輪另明確核准自有yinghua_test帳號routes僅view／僅create及恢復原權限測試，完成後清理。完整路線套用與階段十一仍未授權。
- 2026-10-31 階段十一最新：使用者授權於phase/11-member-itineraries完成多份本人行程CRUD、全量時間備註排序、推薦路線確認套用與P0相容；104 API／68固定頁／33表327欄／19 migration，無新DDL／環境。81整合／66契約回應0錯誤、107 PHP／208 JS、既有收藏69／current100及兩頁三尺寸Browser與原型對照通過，詳見docs/PHASE-11-ACCEPTANCE.md及docs/modules/member-itineraries.md。依備份／原始69檔／完整tree驗證後merge並保留分支；階段十二尚未授權或實作，P1整體未簽核。
- 2026-10-31 階段十二最新：已依使用者授權於phase/12-seo-indexing完成JSON-LD、同版robots／sitemap及分片、P1本機整合；104 PHP API＋3爬蟲GET＝107 HTTP契約，68固定頁／33表327欄／19migration，無新DDL／依賴／env。224 JS／107 PHP、P1 255／181契約、索引369、官方Schema.org Validator七型別及Browser21筆／40頁body保留通過。U-21正式網域未定先完成本機，Search Console／Bing／正式分享列交接，不冒稱通過；見docs/PHASE-12-ACCEPTANCE.md及docs/modules/search-index.md。備份／原始69檔／完整tree核對後以merge commit回main並保留階段分支；階段十三未授權實作。
- 2026-10-31 P1另輪驗收：使用者要求重驗，11列P1、255真API／181契約、369索引、224 JS／107 PHP／分層、18頁各三尺寸網站及原型共108筆與重新登入持久保存完成；無新程式／DDL／環境變更。正式平台依U-21交接，見docs/P1-ACCEPTANCE-20261031.md；驗收併入階段十二整合交付，P2階段十三未開始。
- 2026-11-01 階段十三：依使用者授權於phase/13-site-search完成全站搜尋、同版公開投影與原型互動。105 PHP API＋3爬蟲GET＝108 HTTP契約；68頁／34表338欄／20migration，無新env／依賴／服務。230 JS／112 PHP（370斷言）、113整合／28契約、P1 255／181契約與15筆Browser通過；新019只有PRIMARY，按實際EXPLAIN選擇。見docs/PHASE-13-ACCEPTANCE.md及docs/modules/site-search.md。依備份／原始69檔／完整tree核對後merge並保留分支；階段十四／十五未授權或實作，P2整體未完成。
