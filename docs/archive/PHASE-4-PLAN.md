# 階段四：電影、放映場次與本週片單實作計畫

設計日期：2026-10-19；更新：2026-10-20。狀態：**4A–4E 已完成本機實作與驗收**。階段分支為 `phase/04-films-screenings`，從當時最新 main 建立。原始 Excel 與 website/ 保持不變；實際檔案見 [電影閱讀指南](../modules/films.md)，測試與交付界線見 [階段四驗收](PHASE-4-ACCEPTANCE.md)。下文保留設計與驗收基準。

本計畫依 [總計畫](IMPLEMENTATION-PLAN.md)、[規格基準](../DEVELOPMENT-WORKFLOW.md#規格基準u-122026-10-18) 及 [架構](../ARCHITECTURE.md)。S 是 Excel 明文要求，D 是 Excel 草案，U 是使用者指定，E 是具體工程設計。欄位被列在 F 欄不等於必填。

**U-16，本階段場次決定：只以放映日期及開始時間檢查重複；結束時間留作後續議題，不計算放映區間重疊。** 原型沒有場地選擇，本階段按影像博物館單一放映場地處理，不增加場地管理或選擇欄位。原型只有一組日期／時間，正式資料改為一部電影可有多筆場次。

## 1. 本階段交付與優先級界線

完成後應可操作：後台新增電影 → 填介紹與海報 → 新增／調整多筆放映日期、時間及售票外連 → 存草稿或發布／排程 → 前台本週片單、首頁電影卡及獨立電影詳情同步 → 修改、下架、刪除後同步更新。

| 來源 | 本階段處理 | 界線 |
| --- | --- | --- |
| S：功能規格 E7–L7，J7=P0 | 本週片單、照片／海報、片名、類型、放映日期時間、詳情連結 | 完成電影及場次真實讀取 |
| S：功能規格 E21–L21，J21=P0 | 電影與多筆場次的新增、編輯、刪除，海報與資料同步 | G21 的時間檢核按 U-16 執行；不宣稱已有結束時間碰撞檢查 |
| S：功能規格 E5／G5／L5 | 首頁電影區塊讀同一份已發布資料，空狀態與圖片失敗替代 | 消息／優惠區塊仍留階段五 |
| S：功能規格 L21；E-17 | 必要基本電影詳情，新增電影有直接可開啟的 .html 網址 | 列 8／29 的完整同主題前後篇仍留 P1 階段九 |
| S：串接總覽 B7–B9／B12–B15；功能規格 30／34 的本模組部分 | DB 真實來源、授權、稽核、媒體內容檢查、發布一致性、SEO head、RWD／鍵盤及錯誤提示 | 不宣告全站 SEO 設定、正式部署或完整 WCAG 驗收完成 |

不增加訂票、付款、座位、售票核銷、電影加入會員行程、多場地／影廳管理、片長推算、結束時間、neighbors API、全站搜尋或 Google 電影資料匯入。電影詳情原有前往 planner 的一般導覽可保留，不因此建立「把電影當景點存入行程」的功能。

## 2. website 頁面與互動對應

| 原始 website 路徑 | 新來源／受影響頁面 | 本階段作動 |
| --- | --- | --- |
| `admin/films.html` | `frontend/pages/admin/films.html` | 保留「本週映演」標題、側欄與表格風格；讀真實清單，搜尋、狀態篩選、分頁、編輯及刪除確認。列表以電影為單位，顯示其場次摘要 |
| `admin/film-add.html` | `frontend/pages/admin/film-add.html` | 還原原型電影名稱、類型、海報網址預覽、介紹及售票連結；增加多場次編輯、發布狀態、發布時間與可留空自動產生的 slug |
| `admin/film-edit.html` | `frontend/pages/admin/film-edit.html` | 載入電影及全部場次；失敗可重試，未保存內容有提醒；版本衝突不覆蓋草稿 |
| `cinema.html` | `frontend/pages/cinema.html` 與發布產物 | 沿用 `cinema-list`／`cinema-row` 的海報、日期、類型、介紹、時間、場次介紹連結；同電影多場次可呈現多列，按時間排序 |
| `index.html` | `frontend/pages/index.html` 與發布產物 | 沿用 `.screening-grid` 的三張電影卡及第一張主打版型；取本週最早場次對應的前三部不同電影，數量依實際資料，不足不補假資料 |
| `films/island-slow.html`、`window-sunday.html`、`wind-city.html`、`shape-of-light.html` | 相同公開網址與動態產生的 `/films/{slug}.html` | 保留 detail-page 的海報、名稱、介紹與底部導覽；顯示真實全部公開場次及售票外連，不使用燈箱 |
| `film-detail.html?id=0..3` | 舊入口轉址 | 用原型的 0-based 對照查 DB；已發布目標 301 到 canonical，無效或未公開目標 404，不把陣列位置當正式 UUID |

原型日期／時間兩欄保留在每筆場次列，加入／移除場次先改表單，按保存才寫 API；不另增場次新增／刪除專頁。海報網址即時預覽、取消返回列表、修改後重新載回、刪除確認等原型互動須保留。

原型不足與本案處理：

- 多場次編輯、草稿／發布／排程來自 S7／S21 的正式需求，沿用既有表單 Grid 與發布元件，不另行改版。
- 原型 `website/js/app.js` 的 `renderFilms` 及現有 `cinemaRow` 寫死 `OCT`；正式顯示從完整日期計算月份、星期，跨月／跨年也正確。
- 原型用名稱或陣列位置編輯／刪除，改用固定電影 UUID 與場次 UUID；同名電影不互相覆蓋。
- `public.js`、`public-details.js` 目前仍會讀 prototype.js 的電影；受管電影頁須加明確標記並改用正式資料，避免 API／SSR 內容被原型再次覆寫。消息、優惠、搜尋等未實作模組不趁機接入。
- 原始 style.css 不改；新增場次列必要樣式放前端新增資產，桌面／手機與原型並排驗收。共用控制項須有 label、錯誤關聯、焦點及唯讀狀態。

## 3. 欄位、必填與驗證來源

### 3.1 電影

| API／介面欄位 | 處理方式 | 來源與性質 |
| --- | --- | --- |
| `title`／電影名稱 | 必填；不能只有空白。列表、詳情、海報替代與 SEO 都使用同一名稱 | 原型 film-add／film-edit 的 `name` 有 required 與星號；S F7 只是欄位清單 |
| `genre`／類型 | 選填自由文字，不自行限定分類選單 | 原型 `genre` 無 required；S F7／E7 |
| `synopsis`／電影介紹 | 選填，保留段落與換行；本階段採純文字、安全輸出 | 原型 `desc` 無 required；S F7／S34；純文字呈現為 E-25 |
| `slug` | 選填；新增留空由固定 ID 產生，編輯留空沿用既有；若提供則檢查格式、唯一及歷史網址保留 | S G8／G21 要唯一，原型沒有手填必填；沿用既有景點做法，具體生成規則為 E |
| `posterMediaId`／上傳海報 | 選填，沿用媒體內容檢查；不把「海報 alt 必填」擴成「一定要上傳海報」 | 原型圖片網址非必填；D 資料表建議 C12；S 串接總覽 B12 |
| `posterUrl`／電影海報圖片網址 | 保留原型選填網址及預覽；與上傳資產擇一使用。接受安全 HTTP／HTTPS 外連，不在伺服器下載任意網址 | 原型 `image`、safeImageUrl；具體 URL 邊界為 E-25，不新增網域營運白名單 |
| `posterAlt`／海報替代文字 | 有圖片時，有效替代文字必須非空；可沿用選定媒體的 alt。沒圖片不要求填。修改僅影響此電影用途 | S G7／G21、串接總覽 B12；每筆內容覆寫沿用 E-18 |
| `posterSource`、`posterLicense` | 選填但保存；不擅自增加授權文字必填門檻 | D 資料表建議 C11；沿用 U-14／既有媒體流程 |
| `ticketUrl`／共用售票連結 | 選填；場次未另填時沿用。只做外連，不驗票或收款 | 原型 `ticket` 非必填；D C13 建議場次票務；共用預設為 E-25 |
| `status`／發布狀態 | 新增預設草稿；使用既有 draft／published／archived，前端有清楚選單 | S G5／G7／L21、串接總覽 B13；具體 enum 沿用既有 E-18 |
| `publishAt`／發布時間 | 留空本次發布立即生效；未更動的編輯保留原值；未來時間為排程，按台北顯示 | S F21／串接總覽 B13；具體 UTC 儲存與空值語意沿用既有發布 |
| `seoTitle`、`seoDescription` | 選填內容覆寫；未填以片名與自家介紹／類型形成基本 head，不要求額外 SEO 必填 | S E30、SEO規格 C17／F17；與階段七全站優先序銜接 |
| `version` | 編輯／刪除必須帶載入版本；新增由後端產生 | E-25：防止其他人修改後被靜默覆蓋，不屬業務表單必填政策 |

海報只會選用一個來源：輸入並確認網址後清除舊資產選擇；上傳成功並選用資產後清除舊網址。若 API 同時收到兩個非空來源，回欄位錯誤，避免顯示與保存來源不一致。HTTP 圖片在正式 HTTPS 頁面的混合內容限制須提示，不默默替換成另一張展示海報。外部圖片原網址的公開性不由本站控制；本站只保護草稿資料及本地上傳媒體。

### 3.2 放映場次：U-16 已定案

| 項目 | 本階段規則 | 來源 |
| --- | --- | --- |
| 是否必須有場次 | 原型日期、時間沒有 required；允許零場次保存。已發布但無本週場次的電影不列本週片單，直接詳情可顯示「場次依公告」 | 原型、U-12；不自行新增發布至少一場的門檻 |
| 日期及開始時間 | 一筆有效場次須有完整日期與開始時間；完全空白的初始列不當作場次。部分填寫時指出缺哪部分，不代填今天或現在 | S G7／G21、原型 date／time；輸入完整性為 E |
| 精度與時區 | 介面保持年月日及 HH:mm；API 用帶 offset 的日期時間，秒數為 00，存 DB 前轉 UTC | D API草案 G8；分鐘精度及傳輸格式為 E-25 |
| 同時衝突 | 同日、同一開始分鐘只能有一場；同一電影重複列及不同電影同時開始都檢查，不能藉不同時區表示避開 | U-16；S G21 的本案具體執行方式 |
| 不同開始時間 | 14:00 與 14:30 可並存；不推算片長或結束，不把它判為區間衝突 | U-16 |
| 候選比較範圍 | 建議檢查未刪除且未下架電影的已填場次，含草稿；下架／刪除釋放，恢復發布時重查 | E-25：避免規劃中的同時間場次在發布才互撞；本案工程政策，非 Excel 明定狀態範圍 |
| 每場售票連結 | 選填；非空覆寫電影共用售票連結，空白沿用；最終仍無連結就隱藏購票按鈕 | D C13、原型 ticket；繼承方式為 E-25 |
| 場地 | 本階段固定影像博物館，不建立多場地選項／管理表，也不讓空場地變成業務必填錯誤 | 原型範圍；D C13 的 venue 不直接照搬 |
| 結束時間 | 僅記錄後續議題；本階段不新增 endsAt／ends_at、不要求填寫、不做 ends > starts 或區間碰撞檢查 | U-16；D C13 的 ends_at 草案暫緩 |

API 收到未知的 `endsAt`／`venue` 不能悄悄忽略或假裝保存；依既有白名單回不支援欄位。場次 ID 由後端建立；編輯帶既有 ID 時，必須屬於當前電影，拒絕重複 ID 或挪用其他電影場次。

### 3.3 具體技術上限

初步沿用現有內容資源邊界：slug 160、片名／類型 255、介紹 20,000、網址 2,000、alt／來源／授權 255 字；分頁 pageSize 預設 20、最大 100；海報沿用 JPEG／PNG／WebP、8 MiB、邊長 6000／1200 萬像素。這些是 **E-25 技術設定，非 Excel 數值限制**，實作時要同步 UI、DTO、OpenAPI 與 SQL 容量。場次筆數若需資源上限，另記具體理由，不暗加業務數量限制。

## 4. 預計資料表與 migration

規劃起點是 21 表、十份 migration。4A 已建立並套用下列兩份 migration、三張表，現在共 24 表、十二份 migration；不改已套用的 001–009。電影 API／介面仍待後續步驟。

| 預計 migration | 資料表 | 欄位／關係 | 來源 |
| --- | --- | --- | --- |
| `010_films_and_screenings.sql` | `films` | UUID PK、可空唯一 legacy_id、唯一 slug、title、genre、synopsis、poster_media_id FK、poster_url、poster_alt／source／license、ticket_url、status、publish_at、seo_title／description、created_by／updated_by FK、version、created_at／updated_at／deleted_at | D A12–D12；原型圖片網址／票務；E-25 的版本、網址與用途覆寫 |
| 同上 | `film_screenings` | UUID PK、film_id FK、starts_at UTC DATETIME、ticket_url、created_at／updated_at；不新增 ends_at 或 venue；不重複保存電影名稱、介紹、海報 | D A13–D13 經 U-16 調整；父電影狀態決定公開性，初版不自創獨立取消場次業務 |
| `011_film_aliases.sql` | `film_aliases` | path ASCII PK、film_id FK；保存改 slug 前的固定網址，配合 legacy_id 查舊 query 入口 | S 直接網址一致；E-25，沿用 attraction_aliases 模式 |

`synopsis` 的 SQL 型別須容納設定的多位元文字上限；不要只按字數假設 TEXT 容量。媒體外鍵只接受有效 films 模組資產；海報有效 alt 的條件在 Service 驗證，公開 DTO 輸出有效替代文字，不用 SQL 把「所有電影必須有圖」寫死。

候選索引：films 的 slug／legacy_id 唯一索引、`(status,deleted_at,publish_at,id)` 公開投影、`(deleted_at,updated_at,id)` 管理排序；film_screenings 的 `(film_id,starts_at,id)` 與 `(starts_at,film_id,id)`；同電影同時間可加唯一 `(film_id,starts_at)`。實作時依 MariaDB 12.3 的 EXPLAIN 與實際查詢保留必要索引，不預先聲稱全部索引有效。

跨電影是否保留該開始時間需判斷父電影狀態，不用全表 UNIQUE starts_at 阻擋已下架歷史紀錄。由共同發布寫入鎖、交易及 Service 查詢防止並行重複；不能只查前端或以「查不到資料」當成已上鎖。直接 DB 操作不冒稱由這個 Service 提供保護。

沿用 `media_assets` 的 module 欄位、`audit_logs`、`content_publications`、既有身分與權限表，不建立另一套媒體、權限、發布紀錄或場地表。

## 5. 預計 API 與頁面對應

目前 45 個 API 操作不變；以下九個操作尚未建立，若依此落實則累計 54 個。路徑是 D／E 提案；真正要求的功能來源另外標示。

| 方法／路徑 | 權限 | 用途／頁面 | 來源 |
| --- | --- | --- | --- |
| GET `/api/v1/films` | 公開 | cinema、首頁；`week=current`、或明確 from／to、page／pageSize；只輸出成功發布版本的電影及期間內場次 | S7／5；D API草案 C8–G8 |
| GET `/api/v1/films/{slug}` | 公開 | 基本電影詳情、全部公開場次、海報與有效售票外連；未公開／已刪 404 | S L21 的同步；D C9，P0 基本子集，neighbors 不提前 |
| GET `/api/v1/films/resolve-url` | 公開／Nginx 舊入口 | 舊 query／舊 slug 301、無效 404；不能任意外站轉址 | E-25，沿用景點模式；須在 slug 動態路由前註冊 |
| GET `/api/v1/admin/films` | films.view | 後台列表；關鍵字、發布狀態、穩定分頁 | S21；D C30–G30 |
| GET `/api/v1/admin/films/{id}` | films.view | 編輯載入與重新載入，回電影、場次及 version | S21；E 單筆讀取展開 |
| POST `/api/v1/admin/films` | films.create | 新增電影，可含全部場次；回真正保存資料與公開同步狀態 | S21；D C30–G30 |
| PUT `/api/v1/admin/films/{id}` | films.edit | 保存電影與場次；version 必帶，衝突不靜默覆蓋 | 同上；E-25 交易／版本 |
| DELETE `/api/v1/admin/films/{id}` | films.delete | 刪除確認；送 version，軟刪與發布同步，不刪共用圖片 | S21；具體軟刪為 E-25 |
| POST `/api/v1/admin/films/publish-retry` | films.edit | 資料已存、公開同步失敗的重試入口 | E-25，重用現有發布協調 |

不另外建立 `/home` 或一組獨立場次 CRUD。Excel H21 的 `/admin/films/{id}/screenings` 是建議 API；本案採同一電影 aggregate 保存，避免兩次請求只成功一半。GET 回全部場次；PUT 省略 screenings 時保留，明確 `[]` 才移除全部，每次操作同樣產生電影新版本。

既有 API 的必要擴充：

- POST `/api/v1/admin/media`：增加 `module=films`，action 仍為 create／edit；未提供 module 預設 attractions，舊前端呼叫保持相容。module 僅允許明確 enum 白名單。
- GET `/api/v1/admin/media`：增加明確模組篩選並由 Service 驗相應 view；移除路由對 attractions.view 的硬綁，但保留等價授權。films 帳號不能列出景點草稿媒體。
- GET `/api/v1/admin/media/access`：依圖片實際 module 與有效權限判斷草稿預覽；匿名只可讀目前公開版本使用的圖片，不能因新增 films 權限就開放全部媒體。
- GET `/api/v1/publication/access` 沿用同一版本 gate，不為電影建立第二個公開來源。

管理寫入依既有 CSRF、帳號狀態、每請求權限及唯讀模擬規則。預計錯誤：401 未登入、403 缺權限／模擬寫入、404 目標不存在、409 version／slug／開始時間衝突、422 格式／欄位錯誤；沿用 `code/message/fieldErrors/requestId`。開始時間衝突僅提示所填時間已有場次，不把別部草稿名稱或內容回給沒有 view 的帳號。

公開列表回電影及其匹配場次，以電影為分頁單位；cinema 展開場次後按開始時間穩定排序。電影詳情回全部可公開場次，首頁去重取三部。回應附 generation 與篩選期間，SSR 與 API 不各查出不同版本。

## 6. PHP 分層與具體檔案責任

仍採 Controller → Service → Repository，View 在 frontend/。具體類別名稱是 E-25 提案，以下均是**預計新增／修改**，不代表已存在。

| 預計位置 | 責任 |
| --- | --- |
| `Controllers/FilmController.php` | 管理 HTTP、輸入 DTO 與 JSON 回應；不寫 SQL 或 HTML |
| `Controllers/PublicFilmController.php` | 片單、單筆詳情、舊入口轉址 |
| `DTO/FilmInput.php`、`ScreeningInput.php` | 明確型別、欄位白名單、日期格式與場次所屬驗證輸入；不依賴 HTTP |
| `DTO/Film.php`、`FilmScreening.php`、`FilmPage.php`、`FilmWriteResult.php` | 管理／公開資料白名單與保存／同步結果，不把 PDO 原始列直接輸出 |
| `DTO/FilmQuery.php`、`ScreeningWindow.php` | 以單一地方處理本週／日期區間、分頁與台北時區；不把目前時間硬編在 Controller |
| `Services/FilmManagement.php` | 授權、版本、時間重複檢查、電影與場次交易、稽核及發布協調 |
| `Services/PublicFilms.php` | 只讀成功發布快照並套用公開查詢，不混用 DB 最新草稿 |
| `Policies/ScreeningStartPolicy.php` | 判定同一開始分鐘、同電影重複與可比較狀態，不引入結束時間 |
| `Repositories/FilmRepository.php`、`FilmScreeningRepository.php` | SQL、資料映射、場次歸屬及批次讀寫；共用現有 PDO／Transactions，不自行開另一連線提交 |
| `Adapters/FilmSeeder.php`、`resources/films-demo.json` | 明確 CLI 才匯入受控原始資料，不讀任意瀏覽器 localStorage、不在啟動時重置 |
| 現有 `ContentPublication.php`、`NodeContentRenderer.php` | 擴充投影與來源版本；保留現有鎖、generation、重試及排程，不複製成 FilmPublication |
| 現有 `MediaController/Management/Repository/Access` | 明確模組參數、資產所屬與公開引用判斷；不複製一套 FilmMedia |
| 現有 `Composition.php`、`config/routes.php` | 明確建構子注入、路由與 module/action；不讓 Service 取得整個容器 |

重用 Input、ContentStatus、Authorization、Transactions、EventRepository、ImageStorage、既有錯誤與 Request／Response。需要一致行為時抽取小型網址／slug／日期 value object，不能為了電影導入萬用 BaseController／BaseRepository、ORM 或新的框架。

Service 的保存次序：先取得現有共同發布鎖 → DB transaction 內重查權限與目標／version → 查開始時間衝突及場次歸屬 → 寫電影、場次、別名與稽核 → commit → 重產生公開 HTML → 回保存與同步的分別結果。SQL、稽核任一步失敗全部回滾；HTML 失敗時資料已存，回可重試狀態，不讓使用者重複新增。

## 7. 發布、媒體與日期查詢的共同機制

### 7.1 公開發布不能覆蓋景點

現有 ContentPublication 的投影只有 `items`（景點）及 `aliases`。本階段保留這些鍵，另加入 `films`、`filmAliases` 及片單期間資訊；必要時用小型型別投影整理，避免一次把所有 Repository／DTO 改成通用框架。

同一個不可變 generation 必須同時包含：既有景點／博物館／首頁產物、本週電影清單、電影詳情、兩種舊網址對照及所有目前公開媒體引用。Node 寫完後才原子切換 current，API 與 Nginx 讀相同版本；電影發布不能把 places/ 或 museum.html 刪掉，景點更新也不能把 films/ 消失。

預計修改 `frontend/scripts/publish.mjs`、必要電影模板／渲染模組、NodeContentRenderer 的模板雜湊清單，以及 Nginx 的 cinema／films／film-detail 產物及轉址規則。模板或電影渲染來源改變須改 generation，不能沿用舊頁。

新預計前端來源：`api/films-api.js`、`pages/film-list.js`、`pages/film-form.js`、`pages/films.js`、`components/screening-editor.js`、`core/screening-time.js`。共用前端 API／session／對話框／formErrors／unsavedChanges 沿用既有，新增樣式限必要場次列。

### 7.2 本週與自動換週

本週採 Asia/Taipei 星期一 00:00 至下星期一 00:00，結束邊界不包含；這是 **E-25 週界線提案，Excel 未定義週起點**。資料庫維持 UTC。公開期間在成功快照依 startsAt 半開區間篩選，電影與場次先批次讀取，避免每張卡再查一次；SQL 使用開始時間碰撞與批次關聯索引，分頁採穩定第二排序鍵。這與需要全部公開場次的基本詳情共享同一投影，不冒稱公開分頁另執行 SQL range query。

generation 須納入本週起訖，使週一即使沒人編輯電影，現有 publisher 的 tick 也會更新首頁／cinema。投影保留全部公開電影與場次供詳情／明確日期查詢，current week 只限制片單；不因一場已過期就把電影整頁下架。測試時間由可替換 clock／明確查詢時間注入，不修改系統時間。

多電影相同開始時間檢查在同一共同寫入鎖及交易內進行；兩個不同電影同時新增相同時間，只能一筆成功。每個 API／CLI 寫入入口都走同一流程，不能只靠某個前端按鈕。若日後改多主機部署，須重新確認檔案鎖共享語意，不能把本機 volume 的保證延伸成分散式鎖。

### 7.3 受控示範內容與正式資料

保留原型四個片名、文案及既有 slug 對照。原型日期是 10.12 等月日，沒有放映年份；也不能從頁尾年份反推放映年。示範匯入不將它偷偷改為本週或猜一個年份。

可先匯入片名、介紹、海報與固定網址；日期未確定則不建立場次，前台誠實顯示沒有本週場次。完整年月日的測試 fixture 只寫入隔離測試庫，明確標示測試資料。正式排程由管理員填寫或客戶提供；原外部海報僅為受控示意來源，不下載任意 URL 或宣稱已取得正式素材授權。

## 8. 實作次序與功能提交

已獲授權並完成 4A–4E；下列五個功能邊界各自提交。每筆包含相關契約、測試與指南，自己造成的失誤併回原功能。

| 步驟／擬定提交 | 完成範圍與驗證 |
| --- | --- |
| 4A `build(films): prepare film and screening persistence` | schema／migration、DTO、Repository 及必要資料模型；空庫／既有資料升級、外鍵、同電影重複開始時間、型別／分層檢查。明示共用準備 |
| 4B `feat(films-public): publish weekly listings and film detail pages` | 公開 API、HTML、SEO、舊網址、共同快照及受控示範命令；同版本電影／景點皆可讀，無 JS 仍有正確 head，跨週與 404 可驗 |
| 4C `feat(films-admin): manage films and poster media` | 電影資訊／海報 CRUD、模組媒體權限、後台列表及新增／編輯；未到 4D 前場次保留只讀，更新省略 screenings 不刪既有資料，不冒稱場次編輯完成 |
| 4D `feat(film-screenings): edit screening starts without duplicates` | 動態多場次列、aggregate 保存、U-16 同時檢查、交易／並行／版本、每場票務與前台同步；前後端、契約及實際失敗測試一起交付 |
| 4E `test(phase-04): accept films and document delivered architecture` | 整合、安全／UI／桌面手機、媒體／發布回歸、持久化與實際證據；更新 PROJECT-GUIDE、API、資料表來源及模組中文指南後，才以一個階段 merge 合回 main |

4C 的服務／契約只能接收當時已完成的可寫欄位；4D 再啟用 screenings 寫入與完整重複檢查。每個中間版本都須可建置，不能以後續測試檔去驗證當時不存在的功能。實作時如有更合適的完整功能邊界可調整，不能只按檔案數拆分。

## 9. 驗收清單

下列保留驗收基準；實際執行結果、數量及範圍另記 PHASE-4-ACCEPTANCE.md，不把規劃或先前階段數字冒充本次通過。

| 範圍 | 必須驗證的情境 |
| --- | --- |
| S／U 欄位 | 片名空白拒絕；類型／介紹／海報／售票網址／零場次可留空；有海報且有效 alt 空白拒絕；slug 自動產生、唯一、保留舊址；無不必要的發布必填 |
| U-16 場次 | 相同日期時間在單片及跨片拒絕；不同時間可並存；不同日期同時間可並存；改／刪場次、下架／恢復、其他電影 ID、重複場次 ID；不出現 endsAt／venue 必填 |
| 時區 | 閏日／無效日期、跨月年、星期一／日邊界、offset 同一瞬間、非整分鐘輸入；月底不是固定 OCT；過去日期不因工程假設被拒絕 |
| 真實 DB／並行 | 同一版兩個編輯一筆 409；不同電影同時搶相同開始時間只有一筆成功；場次或稽核故障整份回滾；重跑 migration／seed 不重置手動內容 |
| 授權 | films 四動作各自驗；只有 films.create 能新增／上傳，不自動得到 view 或景點權限；只有 view 不能寫；撤權／停用、過期 session、CSRF、偽造請求、root 唯讀模擬不能修改 |
| 媒體 | URL 預覽與資產上傳擇一，格式／像素／大小、alt、非法協定、混合內容提示、圖片失敗替代；本地未引用／草稿媒體匿名不可讀；其他模組不能預覽電影草稿；改一片說明不影響共用圖 |
| 前台一致性 | 新增電影與多場次 → 發布 → cinema／首頁／直接詳情 → 修改／刪除／下架；三處海報／名稱／日期一致；無資料與失敗狀態不回填原型假資料 |
| 發布故障 | DB 已存而 renderer 失敗、重試、排程、換週；不重複新增，不供應錯誤版本。景點修改保留電影，電影修改保留景點／博物館與原型其他區塊 |
| 網址／SEO | 四個原網址、legacy id 0、改 slug 301、無效／草稿／刪除 404；無 JS head、canonical 自指、相對路徑與安全外連、無燈箱；未完成 neighbors 不留指到展示資料的假連結 |
| 原型／無障礙 | 桌面及手機逐頁對照、三張首頁卡、片單行、圖片預覽、取消／刪除對話框、動態列鍵盤、焦點及錯誤關聯、唯讀、縮放與溢出；不宣稱逐像素或正式讀屏已完成 |

PHP／Composer／PHPStan level 10／PHPat／PHPUnit／PER-CS／Rector dry-run 依既有 Docker 環境；新增 Node 行為／發布測試與真實 MariaDB／HTTP 套件。管理 API 完成後使用 security-audit；UI 實作後使用 web-design-guidelines 的當時規則及 browser；索引依 mariadb-query-optimization 與 12.3 實際 EXPLAIN 驗證。缺少工具或環境時標未執行，不記成通過。

已提供 `docs/modules/films.md`、`docs/PHASE-4-ACCEPTANCE.md` 及必要測試入口，並同步全站導讀、資料庫對照、需求對照與 54 個 API 操作。實際完成空庫／保留資料升級、隔離 app-test／web-test 重建後持久化及階段二／三回歸；未以本次驗收冒稱重啟 Docker Desktop、刪除 volume 或正式部署完成。

## 10. 決定、待提供與設計依據

- 已定案 U-16：日期＋開始時間同時檢查；結束時間後續再議。資料表草案不反過來強迫新增結束時間／場地。
- E-25 需在實作契約清楚呈現的政策：分鐘精度、週一界線、草稿場次參與比較與下架釋放、網址來源擇一、每場票務覆寫、具體資源上限。這些是本案設計，不冒稱業主原文。
- 正式內容／放映年份、海報權利及正式售票網址需客戶提供；未知值留空或顯示未安排，不能從示範文案猜。外連不增加付款與票務服務。
- 部署沿用現有 Docker／MariaDB／publisher／媒體 volumes，不需新金鑰、場地服務或 Google API。正式 HTTPS 與素材仍保留原交付待驗界線。

採用的成熟做法沿用 [ARCHITECTURE.md](../ARCHITECTURE.md)：Service Layer、Repository、DTO、建構子注入及 Adapter。具體技術參考：電影與場次同一交易的提交／回滾依 [PHP PDO Transactions](https://www.php.net/manual/en/pdo.transactions.php)；交易內行鎖語意依 [MariaDB FOR UPDATE](https://mariadb.com/docs/server/reference/sql-statements/data-manipulation/selecting-data/for-update)，不可用單次預查取代並行控制；嚴格解析並檢查日期溢位依 [PHP DateTimeImmutable::createFromFormat](https://www.php.net/manual/en/datetimeimmutable.createfromformat.php)，週界線運算參考 [setISODate](https://www.php.net/manual/en/datetimeimmutable.setisodate.php)。查閱於 2026-10-19；這些技術文件不決定本案業務必填或新增功能。
