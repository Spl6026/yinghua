# 階段六：收藏與基本行程保存計畫

設計日期：2026-10-23。使用者已授權實作，狀態：**6A–6C 已建立，6D 驗證進行中，尚未完成階段驗收**。分支 `phase/06-favorites-basic-itinerary` 從最新 main 建立。目前 API 為 68，主／測試庫皆 31 表／17 份 migration。下文保留設計與待驗基準，實際已建立檔案見 [會員資料閱讀指南](modules/member-resources.md)。

依據：[原始 Excel](../映畫竹塹_後台工程串接功能規格.xlsx)、原始 website、[總計畫](IMPLEMENTATION-PLAN.md)、[分層架構](ARCHITECTURE.md)與 U-08。S＝Excel 功能明文、D＝Excel 建議／草案、U＝使用者指定、E＝工程設計。欄位列出不等於必填；本階段的具體技術決定集中標為 E-27。

## 1. 完成後可以做什麼

會員可在真實公開景點／美食／在地店家上收藏、取消收藏、加入目前行程；可以查看本人收藏及目前行程、移除站點，重新登入或換瀏覽器仍能載回。公開頁不要求登入；私人讀寫只接受會員 session，管理員登入不等於會員登入。

本階段只提供一份「目前行程」作為加入目的地。資料模型允許將來有多份行程；多份管理、名稱／日期／時間／備註編輯、排序及推薦路線套用仍在階段十一。沒有新增消息後台、會員管理後台或收藏管理員 API。

| 基準儲存格 | 原文意思／本階段責任 | 保留界線 |
| --- | --- | --- |
| S：功能規格 E13／J13 | 景點／美食可收藏、加入行程，屬 P0 | 不把收藏擴成電影、消息、優惠券或場次預約 |
| S：功能規格 G15／L15 | 登入必須、只讀寫本人、禁止重複或已刪景點；重新登入可載回 | 同一資料模型的必要安全／保存能力隨 U-08 提前完成 |
| S：功能規格 E15／F15／J15 | 完整旅程包括排序、時間、推薦路線、名稱日期，原列 P1 | 依 U-08 留階段十一，不整列提前打勾 |
| D：資料表建議 C19–E21 | itineraries／itinerary_items／favorites、本人索引、唯一順序與名稱快照 | 採作設計依據；確切 DDL、型別、current_slot 及索引是 E-27 |
| D：API草案 B21–H24 | 本人行程、父子交易、所有權及版本；完整 CRUD 原列 P1 | 本階段採必要子集端點，不提前開放整份行程覆寫／刪除 |
| S：頁面清單 E17／E18；SEO規格 C7／E7／F7 | 私人寫入需 session；個人頁 noindex，API 仍需授權 | favorites 新頁沿用個人頁保護；完整 SEO 設定仍在階段七 |
| S：功能規格 E34／G34／L34 | 標籤、鍵盤／焦點、錯誤與狀態提示、伺服器驗證與安全輸出 | 驗收本次修改頁，不宣稱全站 WCAG 認證 |
| U-05／U-08／U-12 | 保留原型版型；P0 先必要保存；Excel 為基準 | 串接總覽 B17 是「建議」順序，不覆蓋後續明確 U-08 |

沒有從 Excel 推定行程名稱、日期、時間或備註在本階段必填，也不增加 Email 驗證後才能收藏的門檻。

## 2. website 與前端頁面、互動對照

只修改 `frontend/`；`website/` 保持原始交付。保留原型色彩、字體、雙欄／手機單欄、景點卡片、愛心、站點編號及移除按鈕。

| 原型／現有入口 | 預計修改的來源 | 階段六作動 |
| --- | --- | --- |
| `explore.html`、`food.html` 的愛心／＋行程 | 同名 pages、`pages/attractions.js`、`components/content-cards.js` | 收藏讀回真實狀態；寫入成功才改愛心。加入成功後開 planner；已加入不再建立第二筆 |
| 首頁的實際景點卡片 | `pages/index.html`、`scripts/publish.mjs`、`public.js` | 共用相同操作；首頁主視覺／區塊不重新設計 |
| `museum.html`、`places/*.html`、`place-detail.html` | `scripts/publish.mjs` 產生的正式詳情與必要模板 | 使用真實 UUID；與列表共用收藏／加入控制器，舊詳情仍由既有 resolver 處理 |
| `planner.html` | `pages/planner.html`、預計 `pages/planner.js` | 本人目前行程、站點列表、移除、空／錯誤／下架狀態；＋新增地點仍到城市散策 |
| 原型沒有獨立收藏頁 | 預計新增 `pages/favorites.html`、`pages/favorites.js` | E-27：用既有 place-grid 顯示本人收藏、取消、加入行程及分頁；planner 側欄及登入後會員選單提供「我的收藏」入口，不新增頂部主導覽類別 |
| 登入／註冊與會員選單 | `auth.js`、`core/session.js` 及共用模板的必要部分 | 沿用安全 returnTo；導回原頁／planner，明確確認待辦操作後才寫入。更改密碼等既有流程回歸 |
| 電影「安排進旅行」 | `core/film-view.js`／電影模板 | 仍開本人 planner；本階段不把 film ID 當 attraction ID、不自動加入電影或安排場次 |
| `routes.html`／planner 推薦路線卡 | 現有同名頁／元件的必要狀態 | 套用仍未開放；不得把 route query 或原型示意三站寫成本人資料 |

### 2.1 目前行程頁保留與未開放的控制

- 會員名稱來自 `/auth/me`；首次沒有行程時顯示空狀態，不種入原型三筆、固定日期或固定時間。
- 左側名稱／日期欄保留原位置，顯示目前真值／空值並設唯讀；完整編輯、排序箭頭及推薦套用明確停用。鄰近說明「目前可加入、移除地點，更多編排功能將陸續開放」，網站不顯示階段編號或內部實作說明。
- 加入／移除即保存；原「儲存行程」位置改呈現保存狀態與必要的重讀操作，不保留可點擊的假儲存按鈕。時間尚未安排時顯示「待安排」。
- 名稱預設「我的旅行計畫」、日期及 planned_time 為空，是 E-27 的系統初值，不要求使用者補填才可加入；將來階段十一可直接編輯相同資料。
- planner 若收到 `?place=<UUID>`，顯示確認加入的提示，不因 GET／重新整理自動寫入。原型 `place=1..6` 先透過已發布內容的 `legacyId` 對照真 UUID，不將數字直接寫入 FK；無對照顯示不可用。新產物一律使用 UUID。
- `?route=...` 不套用；所有參數驗白名單，成功／取消待辦後移除該待辦 query，避免上一頁或刷新重複操作。

### 2.2 未登入與狀態同步

1. 訪客瀏覽公開頁正常；點愛心或加入時，導向既有登入並保留站內目的及待辦 attraction ID，沒有把個資／token 放入 URL。
2. 登入或註冊後回目的頁，重新核對景點可用，再由使用者確認那筆待辦。僅憑 query 不自動發出寫入；這是 E-27 對保留操作意圖的安全設計。
3. 已登入直接點按鈕時，以 PUT／DELETE 保存；請求中鎖同一項操作，成功才變更狀態。逾時顯示「無法確認結果」並重新讀取；不得直接反轉愛心、假設失敗或重送非冪等操作。
4. `mountMemberActions(root)` 在 SSR 初始畫面、篩選／換頁卡片更新後重新載入所見 ID 的收藏狀態；同一地點出現多張卡時同步，舊回應不得覆蓋新頁或新身分。
5. 登出、401、頁面由瀏覽器返回／重新取得焦點時，清除或重讀記憶體狀態；不留下上一位會員的收藏。跨瀏覽器以重新載回同步，不新增 WebSocket。
6. 狀態讀取失敗與「未收藏」分開；已登入時失敗不把全部愛心顯示成確定未收藏，提供重試。私人頁先完成 session 檢查，再顯示會員資料。

## 3. 預計新增 API：七個操作

以下端點均屬 E-27；功能來源見第 1 節。統一 `/api/v1/me`，Route kind 為 Member、必須驗證登入。寫入沿用 member CSRF，安全錯誤與 `Cache-Control: no-store`；沒有 memberId 輸入，實際會員 ID 只取 session。

| 方法與路徑 | 輸入 | 成功結果／頁面 |
| --- | --- | --- |
| GET `/api/v1/me/favorites` | cursor、pageSize | 200 本人收藏頁；每列 attractionId、savedAt、availability、公開 attraction 或 null、nextCursor |
| GET `/api/v1/me/favorites/status` | ids：本頁 UUID 清單 | 200 每個 ID 的 favorited；用於所有卡片批次讀回，避免逐卡請求與全量抓收藏 |
| PUT `/api/v1/me/favorites/{attractionId}` | 無業務欄位 | 首次 201、已存在 200，回 attractionId／favorited=true；不採 toggle 語意 |
| DELETE `/api/v1/me/favorites/{attractionId}` | 無業務欄位 | 204，包括已不存在；下架後仍可取消本人收藏 |
| GET `/api/v1/me/itineraries/current` | cursor、pageSize | 200 `{itinerary:null,nextCursor:null}` 或目前行程與有序項目；GET 不建立資料 |
| PUT `/api/v1/me/itineraries/current/items/{attractionId}` | version：整數，無目前行程時為 0 | 新增 201、已存在 200；回 itineraryId、version、attractionId、changed；首次新增在同交易建立父表 |
| DELETE `/api/v1/me/itineraries/current/items/{attractionId}` | version，沿用現有 API 的 JSON DELETE body 慣例 | 200 同一種寫入結果；不存在時不異動版本，無行程時 itineraryId=null／version=0 |

API 不接受 title、tripDate、plannedTime、note、sortOrder、memberId 或任意 itineraryId 來繞過本階段界線。完整 `/me/itineraries` 清單／整份 CRUD 仍留階段十一；路由先登記 `/favorites/status` 等固定路徑，再登記參數路徑。

### 3.1 回應、分頁與錯誤

- 收藏依 `created_at DESC, attraction_id DESC`；行程依 `sort_order, id`。收藏／行程 pageSize 預設 20／50，最大 100，批次狀態最多 100 ID；具體值屬 E 的單次資源界線，不是收藏／站點總數上限。
- 游標包含排序鍵；行程游標另綁當前 itineraryId／version。每次仍套本人條件，不把編碼游標當授權。跨頁版本改變回 409，要求重讀，避免拼接兩個版本的列表。
- 行程摘要包含 id、title、tripDate、version；項目包含 attractionId、sortOrder、plannedTime、note、snapshotName、availability 及可用時的公開資料。寫入成功後重新載入所見行程頁；若重讀失敗，明確區分「保存已成功、畫面待重讀」。
- 401：會員未登入／失效；403：CSRF 拒絕；404：新增目標不公開、不存在或已刪；409：有效變更的版本衝突；422：輸入格式／未支援欄位；503：公開版本尚未就緒而不能確認新目標。不可用目標使用一致訊息，不洩漏草稿是否存在。
- 已達成的狀態重送可成功而不新增資料、不增版本；例如同一 PUT 再送、同一 DELETE 再送。若需要真的改資料，必須符合當前 version，不能讓舊請求覆蓋中間變動。
- 新增 OpenAPI schema／獨立契約驗證，**實作完成後預計 68 個操作**；現在契約仍維持 61，不提前寫入可呼叫端點。

## 4. 預計資料表與 migration

預計兩份 migration：`015_member_favorites.sql`、`016_member_itineraries.sql`，不修改舊 migration、不清空主庫。實作當時再確認編號未被占用。完成後預計 31 表／17 份 migration；目前仍是 28／15。三張表均為 InnoDB，UUID／FK 字元集沿用現有 ASCII CHAR(36)，日期時間沿用現有 UTC 儲存與台北顯示。

| 表 | 預計欄位與約束 | D／E 對照 |
| --- | --- | --- |
| favorites | member_id、attraction_id、created_at；PK(member_id,attraction_id)，兩個 FK；列表索引(member_id,created_at,attraction_id) | D：C21–D21；精確索引與冪等寫入為 E-27 |
| itineraries | id PK、member_id FK、title VARCHAR(200) 預設「我的旅行計畫」、trip_date DATE NULL、status VARCHAR(20) 預設 active、version UNSIGNED 預設 1、created_at／updated_at／deleted_at；額外 current_slot TINYINT NULL | D：C19–D19；名稱長度、active 值、current_slot、CHECK 及版本政策為 E-27；本階段 status 不開放輸入 |
| itinerary_items | id PK、itinerary_id FK、attraction_id FK、sort_order UNSIGNED、planned_time TIME NULL、note TEXT 預設空文字、snapshot_name VARCHAR(120) 與現有景點名稱同長度、created_at；UNIQUE(itinerary_id,attraction_id)、UNIQUE(itinerary_id,sort_order) | D：C20–D20 的順序／名稱快照；另一唯一鍵落實 S G15 不重複。時間／備註先建立儲存位置，不開編輯 API |

### 4.1 一份目前行程，不限制未來只有一份

E-27：`current_slot` 只允許 NULL 或 1；UNIQUE(member_id,current_slot) 讓每位會員最多一筆 current_slot=1，其餘將來的行程可為 NULL。已刪行程不得占 current_slot。這比直接 UNIQUE(member_id) 更能沿用至階段十一，也不需新建 current_itineraries 表或在 members 加循環 FK。

這利用 [MariaDB 對 UNIQUE 中多個 NULL 的既有語意](https://mariadb.com/docs/server/reference/data-types/null-values)，並以明確 CHECK 保護 slot 值及已刪資料；具體約束仍須在本案 12.3 實測，不只憑文件判通過。

第一次加入才建立目前行程與第一站；一般讀取／登入不建空資料。刪除最後一站後保留空行程及版本，避免舊請求再次使用 version=0 意外建立第二份。

索引按實際查詢建立：目前行程使用上述唯一鍵；順序讀取使用唯一順序鍵；favorites 用會員＋建立時間；D19 的 (member_id,updated_at) 階段十一出現多份列表查詢時再驗證加入，不為草案預先堆疊索引。FK 所需反向索引檢查 InnoDB 已建立的索引，避免重複。

### 4.2 下架、刪除與名稱快照

- 新增收藏／站點只能引用當下可公開的景點，新增站點以當時的公開名稱存 snapshot_name。可收藏三種 attraction 類別，不加入電影／優惠 ID。
- 既有站點遇到下架／軟刪仍保留快照名稱並標示不可瀏覽；不輸出現在草稿的介紹、地址、圖片或 slug。收藏沒有名稱快照，失效項顯示通用提示並可取消，不讀回未公開的新名稱。
- 行程／收藏私人引用不加入 `AttractionRepository::referenced()` 的商業內容刪除阻擋：會員收藏不應使業主管理員無法下架／軟刪景點。保留 FK、不實體刪景點及不連帶清除會員資料；須回歸現有路線／優惠的引用保護。
- 私人讀取與公開資料投影分開。公開版本未就緒時仍可讀本人 ID／快照，availability=unknown、公開欄位為 null；不錯稱下架、也不洩漏舊公開版本。移除本人既有項目不依賴公開發布成功；新增需等可驗證的公開版本。

## 5. PHP 架構、共用與交易

沿用 Controller → Service → Repository、明確 DTO、建構子注入及共用 Transactions；PHP 只回 JSON。不是加入 Spring Boot／ORM，而是沿用成熟分工。本階段不需要 BaseService、通用 CRUD 框架、事件匯流排或新的持久層。

| 預計檔案／位置 | 責任與重用 |
| --- | --- |
| Controllers/FavoriteController.php、CurrentItineraryController.php | 解析 route／query／JSON、建立 typed input、交給 Service，回安全 DTO；不寫 SQL |
| Services/MemberFavorites.php、CurrentItinerary.php | 本人讀寫、冪等行為、交易與行程版本；多個頁面呼叫相同使用案例 |
| Services/MemberAccess.php | 以既有 AccountRepository 重查有效會員；寫入交易內重查並鎖會員。不套用只服務管理員的 Authorization::require()，也不把 member 變成 RBAC 角色 |
| Services/MemberAttractions.php | 將既有 ContentPublication 成功快照與私人記錄組合，集中公開／不可用／未知投影；不複製發布算法、不重發私人 HTML |
| Repositories/FavoriteRepository.php、ItineraryRepository.php | prepared SQL、會員條件、唯一鍵與型別映射；不自行提交交易 |
| DTO／Models | 預計 FavoriteQuery、FavoriteStatusInput、ItineraryItemInput、FavoritePage、CurrentItineraryPage／Item 與寫入結果；readonly 用於不變資料，不包裝 PDO 為不可變物件。具體名稱實作時依現有命名核對 |
| config/routes.php、Composition.php、既有架構測試 | 明確組裝依賴，Member route 守門與 PHPat 邊界；不讓 Service 接整個容器 |

### 5.1 加入站點的交易順序

1. Kernel 驗證會員 session／CSRF；真正新增需要取得成功公開快照作目標候選，不能把 public GET 的資料直接當已授權寫入。已存在的重送與移除可略過公開投影檢查，不因公開生成故障而失敗。
2. Service 在同一交易依固定順序鎖會員 → 本人目前行程 → 目標景點；確認會員仍 active、行程屬本人及未刪。第一次建立也鎖會員，防止兩個請求建立兩份目前行程。
3. 已存在同一站時回現狀；真的新增才檢查 version。鎖定後重核景點公開狀態、到期發布時間與公開快照一致；不一致不使用舊候選寫入。
4. 父表鎖內計算 MAX(sort_order)+1、寫站點與公開名稱快照、更新父表 version／updated_at，一次 commit。移除同樣鎖父表並更新版本，不另外重新編號所有舊站。
5. 用唯一鍵處理並行競爭、已知 DB 例外轉現有安全錯誤；任何失敗整筆 rollback，不留只有父表或半筆站點。

收藏同樣交易內重查會員／目標及使用唯一鍵；目標下架後仍允許取消。私人寫入不取得全站發布鎖、不觸發 content:publish、不改公開 generation；公開可用性核對與私有交易避免形成反向鎖依賴。下架與新增交錯的線性化結果須以真實 MariaDB 並行測試確認。

Service／Repository 的所有查詢都套本人條件；即使游標、JSON 或測試呼叫提供別人的 itineraryId，也不能取得／寫入其他人的資料。前端不傳 memberId 並不取代後端所有權檢查。

既有 audit_logs.actor_admin_id 為必填管理員，不偽造 root 代會員寫稽核，也不把私人名稱／備註塞進 auth_events。此階段不新增會員行為紀錄表；必要拒絕事件沿現有安全紀錄保留最少資訊。完整會員操作稽核若另有需求再設計，不冒稱 Excel 要求。

## 6. 前端預計檔案與復用

- 新增 `api/member-resources-api.js`：七個 API 的薄封裝，沿用 core/api-client.js 的 Cookie／CSRF／逾時／統一錯誤。
- 新增 `core/member-actions.js`：愛心／加入、登入待辦、同項互斥、批次狀態與世代號；所有公開頁共用。保留文案與 UI helper，移除 `public.js` 對已實作收藏／行程操作的「後續開放」攔截，仍保護排序／套用等未實作操作。
- 新增 `pages/favorites.js`／`pages/planner.js` 及必要的 `components/member-itinerary.js`：私人列表、版本／分頁、失效／錯誤／重試、移除後焦點。共用現有卡片與通知，不讓 prototype.js 的 itineraryPreview 覆寫本人資料。
- `public.js`、`pages/attractions.js`／詳細頁及發布腳本只接共用入口。SSR 發布產物保持不含任何會員 ID、收藏狀態或私人行程；登入狀態只由瀏覽器向私人 API 取得。
- 新增 favorites 頁的 noindex,follow 與本機路由清單；如需 CSS 放最小範圍的 `phase6.css`，原型 `style.css` 保持一致。新增來源後預計固定頁為 65，動態公開內容數另計。
- 不使用 localStorage 作正式保存、不匯入舊瀏覽器的假帳號／收藏／行程。既有資料屬哪位會員無法驗證，不能默默套到新帳號。

環境沿用現有 PHP＋MariaDB＋Docker／Node 建置，不預計新增套件、容器服務、port、金鑰或 `.env` 變數。景點顯示重用既有成功公開資料，不因收藏或加入行程再次呼叫 Google Places。資料庫升級、前端建置與公開模板的重新發布仍走既有受控命令；私人資料本身不進發布快照。

## 7. 功能提交與執行順序

規劃已先提交 main；使用者後續明確授權實作，已建立階段分支。6A 的共用資料層已完成獨立驗證，其餘依下列功能順序繼續，不將共用前置當成整個階段已完成。

| 子步驟／預定提交 | 可獨立檢查的交付 |
| --- | --- |
| 6A `build(member-resources): prepare typed persistence and member access` | 三表／兩份 migration、模型／DTO／Repository、MemberAccess 與 DI、空庫／升級／唯一鍵及本人資料層測試；明列共用前置，尚無可用收藏 UI |
| 6B `feat(member-favorites): persist and display personal favorites` | 四個 API＋契約、共用愛心／登入返回、我的收藏頁與入口、失效項處理、API／DB／前端回歸及中文指南 |
| 6C `feat(member-itinerary): persist the current trip and its stops` | 三個 API＋契約、目前行程、加入／移除／版本與快照、planner／所有真實入口串接、頁面保留差異、並行／錯誤測試及指南 |
| 6D `test(phase-06): accept member persistence and update project guide` | 完整跨模組回歸、桌面／手機原型對照、隔離環境重建、驗收文件、全站導讀與資料庫／API 對照；核對未提前實作階段十一 |

自己的錯誤修正歸回對應功能，不增加修錯進度提交。合併前備份歷史，核對 main／分支完整 tree、第一提交與原型 69 檔；保留階段分支、不自行推送。現有防毒隔離的 skill 檔案差異保持原狀，不納入提交。

## 8. 實作前固定的驗收清單

以下是待執行條件，**現在沒有驗收通過結果**。實作時每項記錄 API／MariaDB／瀏覽器證據、版本、時間及未驗範圍，並依 [既有驗證流程](VERIFICATION-CHECKLIST.md) 重驗影響範圍。

| ID | 必須驗證的流程 |
| --- | --- |
| 6-T01 | 新會員讀取空收藏／空行程不建資料；首次加入只建一份父表＋一站；最後一站移除後保留空行程版本 |
| 6-T02 | 收藏／取消、重送／連點、動態篩選／換頁後狀態、同頁同項同步；登出再登入與第二瀏覽器讀回 |
| 6-T03 | 未登入先登入／註冊安全返回；外站／admin returnTo 被拒、query 只顯待辦不自動寫入；取消待辦／刷新／上一頁不重複操作 |
| 6-T04 | A／B 會員不同收藏與行程；盜填 memberId／itineraryId、串改游標／站點 ID、SQL／Service 的本人條件；admin Cookie 單獨不能讀寫，登出／停用後 401 |
| 6-T05 | CSRF 缺漏／錯誤拒絕；來源名稱與快照的 HTML／XSS 安全輸出、no-store／noindex、公開 HTML／sitemap 不含私人資料 |
| 6-T06 | 同時首次加入不同站、同站重複、相同 version 交錯新增／移除、舊請求重送／409、DB 寫入故障 rollback；不覆寫他頁變更 |
| 6-T07 | 草稿／排程未到／下架／軟刪／不存在的新增拒絕；保存後下架仍可讀快照、取消／移除，草稿更新名稱／圖片不外洩 |
| 6-T08 | 公開生成故障時 availability=unknown、私人移除可用；故障復原顯示真公開資料，不因私人增減觸發公開 generation 或阻擋管理員下架 |
| 6-T09 | 所有列表／詳情／博物館／首頁實際入口、UUID／legacy query、電影只開行程、不把 route／示意三站寫入；沒有局部頁面仍攔截已實作操作 |
| 6-T10 | 初始／載入／保存中、401／409／422／503、網路結果未知、保存成功但重讀失敗、取消／重讀、bfcache／換帳號舊回應；UI 只在服務端確認後顯成功 |
| 6-T11 | 收藏／行程分頁、跨頁版本變化、空資料、失效項、移除後焦點、愛心 aria-pressed／狀態提示、鍵盤、390px／1440px、縮放與無頁面水平溢出 |
| 6-T12 | 空庫與保留原會員／內容的升級、app／web 重建後保存、三表與約束真 MariaDB 檢查、實際列表／狀態／順序查詢 EXPLAIN；不重啟共享 DB 以追求 Performance Schema |
| 6-T13 | 階段二登入／密碼／權限／唯讀模擬，階段三圖片／發布，階段四電影／場次，階段五首頁／消息／優惠契約及介面回歸；新七個操作獨立 schema 驗證 |
| 6-T14 | Docker 中 PHP／PHPStan10／PHPat／格式、Rector dry-run 審閱、前端行為測試與建置完整性；新頁改為實際 65 頁，工具缺漏不寫通過 |
| 6-T15 | 自建 fixture 僅在 yinghua_test／8081，清理自有資料／帳號並保留正常稽核；更新 PHASE-6-ACCEPTANCE、modules/member-resources、PROJECT-GUIDE、架構／契約／逐表對照，確認階段七與十一仍未完成 |

PHP 實作套 php-modernization；登入／所有權與寫入完成後用 security-audit 審查並做真實失敗／越權測試。MariaDB 設計與實際查詢套 query-optimization，以本案 12.3 為準；表單／介面驗收用 web-design-guidelines 的當時官方指南與 browser skill。沒有執行的外部 scanner／正式環境／瀏覽器組合如實列未驗，不用通過數量取代流程覆蓋。

## 9. 成熟設計參考與本案取捨

以下是已核對的設計依據；不是客戶新增功能，也不表示引入這些框架。

- [Spring 官方交易管理範例](https://docs.spring.io/spring-framework/reference/data-access/transaction/declarative/annotations.html)：交易責任放在完成使用案例的 Service。本案以既有 Transactions 實作相同責任分工，不在 Controller 或各 Repository 分別 commit。
- [OWASP Authorization Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html)：每次請求驗證權限、保護物件識別與所有權。本案會員資料以 session 身分＋SQL 本人條件，不靠 UUID 難猜或前端藏按鈕。
- [RFC 9110 §9.2.2／§9.3.4／§9.3.5](https://www.rfc-editor.org/rfc/rfc9110.html#section-9.2.2)：PUT／DELETE 的狀態操作適合重送。本案用明確收藏／取消及新增指定站點，不用 toggle；版本仍處理真正交錯變更。
- [MariaDB FOR UPDATE](https://mariadb.com/docs/server/reference/sql-statements/data-manipulation/selecting-data/for-update)：InnoDB 的鎖定讀取須在交易內。本案父表鎖、唯一鍵與版本共同處理並行，不依賴沒有交易的 MAX+1。
- Service Layer／Repository／DTO／DI 的既有設計來源與反例見 [ARCHITECTURE](ARCHITECTURE.md#3-採用哪些成熟做法解決什麼問題)。具體 schema／頁面／數值與失敗行為仍是本計畫 E-27，須實作與驗證後才算交付。
