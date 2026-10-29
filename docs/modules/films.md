# 電影與放映場次閱讀指南

2026-10-25 R02 館舍同步（S：串接總覽 B7／功能規格 E6、L6；E-29）：[cinema.html](../../frontend/pages/cinema.html) 的 `MUSEUM_VISIT_START/END` 標記由 [publish.mjs](../../frontend/scripts/publish.mjs) 置換。館名、地址及開放時間取同一 generation 的 `home.museum`／公開館舍，週表使用既有 `openingHoursLines()`，與 museum 詳情同源；下架顯示空態，不回填原型時間。連結導向館舍詳情與交通資訊，保留 `.museum-info` 排版。無 API／資料表新增。

[film-publication.test.mjs](../../frontend/tests/film-publication.test.mjs) 實際產頁測結構週表、第二版地址／舊式時間、下架空態與文字編碼；本輪 Node 150 項通過。Brave 桌機 1440×900／手機 390×844 已確認時間與 museum 相同、連結可達且無頁面橫向溢出。

更新：2026-10-20。階段四 4A–4E 已完成本機實作與驗收：電影資料層、公開發布、電影資訊／海報管理及多場次編輯。最終 PHP 55 項／233 斷言、Node 88 項、20 套真實整合 619 項及 54 個 API 操作契約驗證通過；實際範圍與限制見 [階段四驗收](../PHASE-4-ACCEPTANCE.md)。下方 4A–4D 數字保留各功能當時的驗證紀錄。

2026-10-23 手機複驗補充（U-05／E-25）：`frontend/assets/css/phase4.css` 讓編輯頁標題旁 `#reload-film` 不縮成逐字直排。只補充實際新增的操作按鈕，不修改原型 style.css、欄位限制或 API；390 CSS px 的按鈕單行及頁面無水平溢出已實測。

## 已建立的資料層

| 檔案 | 用途 |
| --- | --- |
| `backend/database/migrations/010_films_and_screenings.sql` | 電影、場次及必要外鍵／索引；開始時間存 UTC，沒有結束時間／場地欄位 |
| `backend/database/migrations/011_film_aliases.sql` | 保存電影歷史網址與目標電影的關係 |
| `backend/src/DTO/ContentUrl.php` | 共用外連協定／格式邊界，不下載任意網址 |
| `backend/src/DTO/FilmInput.php` | 電影可寫欄位、型別及省略保留語意；新增以草稿為預設 |
| `backend/src/DTO/ScreeningInput.php` | 放映開始時間、分鐘精度、UTC 正規化及票務外連 |
| `backend/src/DTO/Film.php`、`FilmScreening.php`、`FilmPage.php`、`FilmWriteResult.php` | 管理／公開安全輸出、分頁及保存與同步結果 |
| `backend/src/Policies/ScreeningStartPolicy.php` | U-16 的同一開始分鐘／重複 ID 檢查，不計算片長 |
| `backend/src/Repositories/FilmRepository.php` | 電影 SQL、版本與別名、公開投影、場次批次映射 |
| `backend/src/Repositories/FilmScreeningRepository.php` | 場次歸屬、批次載回、整組保存及跨電影開始時間查詢；交易由 Service 協調 |
| `backend/tests/Unit/FilmInputTest.php` | 原型必填／選填、時區同時、非法日期、結束欄位拒絕及巢狀錯誤 |
| `backend/tests/film_persistence.php` | 限 yinghua_test，真實保存、歸屬、防止挪用及下架釋放；全部回滾 |

來源：Excel 功能規格 7／21、資料表建議 12／13；U-16 指定只比較日期與開始時間，結束時間延後。具體表名、DTO、索引及分鐘精度為 E-25，完整計畫見 [PHASE-4-PLAN.md](../PHASE-4-PLAN.md)。

`screenings` 省略代表保留，明確空陣列代表移除；父電影的 version 是整組更新的版本。同名電影可以存在，不能用名稱定位。場次保存由 `FilmManagement` 在共同發布寫入鎖及同一交易內協調，不把 Repository 的一次預查當成完整並行保護。

## 實際驗證

4D：`FilmManagement` 在同一發布寫入鎖與交易內套用 `ScreeningStartPolicy`、跨電影開始時間查詢及整組場次替換，稽核成功才 commit。修改／刪除子場次使用父電影 version；既有 ID 必須屬於同電影，重新排列保留 ID 與 created_at。草稿占用時間，下架釋放；恢復時即使未提供 screenings 也重新比較。相同日期 14:00／14:15 可並存，不推算結束時間。

`components/screening-editor.js` 提供動態列、移除確認、錯誤定位、焦點及唯讀禁用；`core/screening-time.js` 將台北日期／時間轉明確 +08:00，不依電腦時區推測。零列可保存；新增尚未填完的列需補齊或移除，新增／移除只有按電影保存才落 DB。`screenings` 省略保留、`[]` 移除全部，每場 ticketUrl 留空沿用電影預設。

4D：`films_screenings_integration.py` 真實 HTTP／MariaDB 42 項，包括同片／跨片、offset 同時、ID 歸屬、並行搶場、稽核／子表故障全交易回滾、移除釋放、下架／恢復、101 場無暗加筆數限制與排程發布；PHPStan／PHPat、PHPUnit 54 項／232 斷言通過；電影表單／場次 Node 9 項通過。

4C 管理 `FilmController → FilmManagement → FilmRepository`；`FilmPosterPolicy` 保護單一來源與有圖 alt。服務在共同寫入鎖及 transaction 中重新授權、檢查 version、保留歷史 slug、寫電影與稽核；DB commit 後同步發布，失敗明確回 202。新增／讀取／列表／編輯／刪除及 publish-retry 六個管理端點已接入，現在共 54 API。

`MediaModule` 區分 attractions／films；既有上傳端點省略 module 維持 attractions，指定 films 時依 films.create/edit 授權；媒體清單依相應 view，`MediaAccess` 依資產實際 module 判斷草稿預覽。匿名僅可讀成功快照仍引用的本地圖片。海報用途說明只改電影，不改共用資產。

`frontend/pages/admin/films.html`、`film-add.html`、`film-edit.html` 及 `pages/film-list.js`／`film-form.js` 使用原型表格、表單網格、海報網址即時預覽／取消。`api/films-api.js` 共用既有 CSRF／session 客戶端；新增網址／上傳擇一、狀態／排程／SEO 欄位及衝突／202 提示；海報預覽沿用 `admin-film-preview`，`phase4.css` 僅補充場次樣式。唯讀模擬禁用寫入，讀取失敗不能覆寫，只有新增權限在 201／202 後不能重複新增，成功後可選擇再新增一部。

4C：真實管理 HTTP／MariaDB 61 項；Node 表單 6 項；PHPStan／PHPat、PHPUnit 54 項／232 斷言通過；實際路由與契約共 54 操作一致。`films_admin_integration.py` 包含四動作分權、跨模組海報、防止 CSRF／越權、並行版本、公開失敗及重試。完整桌面／手機驗收在 4E 彙整。

4B 新增 `GET /api/v1/films`、`/films/{slug}`、`/films/resolve-url`，目前共 48 API。`PublicFilmController` 只轉 HTTP，`PublicFilms` 讀成功快照並分頁，`FilmQuery`／`ScreeningWindow` 定義台北週一至下週一的半開區間。`ContentPublication` 將電影、景點與週界線納入同一 generation；`NodeContentRenderer` 的版本雜湊包含所有電影模板與共用 renderer。

`frontend/assets/js/core/film-view.js` 共用電影卡、片單行、詳情與日期／週標示；`scripts/publish.mjs` 產生無 JS 可讀的首頁、cinema 與 `/films/{slug}.html`，`pages/films.js` 處理片單分頁、週標示更新與海報失敗替代。`assets/css/films.css` 沿用原型版型並處理封面裁切／長文字；新電影網址可安全返回登入前頁面。Nginx 禁止退回原型固定電影頁；改名與 `/film-detail.html?id=...` 透過公開快照轉址，草稿／刪除回 404。

`FilmSeeder` 與 `resources/films-demo.json` 由 `php bin/console.php film:seed-demo` 受控匯入原型四部片的文案、海報與固定網址；不猜放映年份、不建立場次、不覆寫已匯入資料。主庫已匯入四筆，尚未排場次的片單顯示空狀態。

4B：PHPStan／PHPat 通過，PHPUnit 54 項／232 斷言；真正 Node 發布器 5 項（景點 3、電影 2）驗證跨年週期、安全編碼、原子發布及下架。公開 HTTP／MariaDB 驗證命令：`python backend/tests/films_public_integration.py`；結果隨階段驗收彙整。

4A 當時：主庫／測試庫新增兩份 migration 並重跑成功，事前備份留在被忽略的 artifacts。Docker PHPStan level 10／PHPat 通過；PHPUnit 52 項／227 斷言；隔離 MariaDB 資料層 8 項通過並回滾。該準備提交尚未新增電影公開／管理路由，當時為既有 45 個 API。

## 實際操作與 API

後台「本週映演」新增或編輯電影。片名必填；類型、介紹、海報、售票網址及零場次可空。有圖時須提供有效 alt，選用已有資產可繼承其 alt；網址與上傳擇一。發布狀態選「已發布／排程」，確認發布時間後儲存。回 202 代表資料已保存、公開同步未完成；在編輯頁重試發布，不能再次新增同一筆。

場次以台北日期及開始時間填寫，每場售票網址可覆寫電影預設。草稿也占用開始時間，下架／刪除釋放；恢復時重新檢查。日期及開始分鐘相同才衝突，14:00／14:15 可以並存。結束時間與場地依 U-16 延後，沒有額外發布必填。

| 方法／API | 責任與授權 |
| --- | --- |
| GET `/api/v1/films` | 成功快照中的指定週／日期區間電影與場次，公開分頁 |
| GET `/api/v1/films/{slug}` | 成功快照的已發布詳情及場次 |
| GET `/api/v1/films/resolve-url` | legacy 0–3、UUID、固定或歷史網址解析 |
| GET `/api/v1/admin/films` | 後台搜尋／狀態／分頁；films.view |
| POST `/api/v1/admin/films` | 新增電影與場次；films.create |
| GET `/api/v1/admin/films/{id}` | 編輯所需資料；films.view，寫入另驗 films.edit |
| PUT `/api/v1/admin/films/{id}` | 帶 version 保存整組電影；films.edit |
| DELETE `/api/v1/admin/films/{id}` | 帶 version 軟刪；films.delete |
| POST `/api/v1/admin/films/publish-retry` | 重試公開同步；films.edit |

海報沿用 `/api/v1/admin/media`，傳 `module=films` 依電影權限處理，沒有另建重複上傳服務。完整輸入／回應與錯誤依 [OpenAPI](../../backend/resources/openapi.json)。時間寫入格式為 `YYYY-MM-DDTHH:mm:ss±HH:mm`；開始時間秒須為 00，DB 存 UTC，前台轉台北。

閱讀順序：`routes.php` → `FilmController` → `FilmManagement` → DTO／Policy → Repository；公開端看 `PublicFilmController` → `PublicFilms` → 成功 snapshot，HTML 看 `ContentPublication` → `NodeContentRenderer` → `publish.mjs`／`film-view.js`。Controller 處理 HTTP，Service 負責授權／交易／流程，Repository 封裝 SQL，前端負責 View；沿用 Service Layer、Repository、DTO 與 Adapter。全部新增檔案、資料表與環境命令見 [全站導讀](../PROJECT-GUIDE.md#階段四新增檔案與電影資料流)，設計依據見 [架構](../ARCHITECTURE.md)。

2026-10-21 原型對齊（U-05）：場次介紹採 `window-sunday.html` 的電影頁殼，共用 `film-view.js` 呈現 IMAGE MUSEUM / SCREENING、海報、介紹、公告、類型／日期資訊列與「回到本週映演」。原型 `island-slow.html` 的 CITY STORY／返回城市散策不再套到全部電影。多場次沿用資訊列逐列顯示，日期含實際年份；原型示意日期不進資料庫。首頁／片單恢復各自海報遮色，單頁隱藏分頁，後台新增場次按鈕回到頁首。前後篇／繼續閱讀仍待階段九；安排進旅行仍只是連結，保存待階段六。

發布紀錄 INSERT／UPDATE 失敗時，電影的已提交資料仍以 202／publication.failed 回報；共用錯誤邊界與景點一致。`backend/tests/publication_failures.py` 僅使用受保護的 yinghua_test fixture，建立暫時故障 trigger 並於 finally 移除、重試發布；涵蓋兩模組新增／更新／刪除及未完成公開版本的隔離。

片名必填來自原型，其餘規則逐項見階段四計畫的 Excel 儲存格對照。週界線、分鐘精度、草稿占用、版本／歷史網址及具體技術長度屬 E-25；不將工程設定冒稱 Excel 硬性規定。沒有新增環境變數或 Google API。`film:seed-demo` 僅匯入四部原型 metadata，不推測放映年份；主庫目前未填完整日期，本週片單可為空。
