# 階段三：景點、媒體與地圖匯入

2026-10-18新增U-10：Google候選帶入行政區，官網保留HTTP／HTTPS，簡介原文只供參考，網站介紹由管理員撰寫；Google地點關聯可見且變更須人工確認或解除。未新增端點、表或migration。完整設計、契約與結果見 [地圖改善計畫](MAP-IMPORT-IMPROVEMENTS.md)。

2026-10-18 新增 U-09：人工與 Google 匯入共用結構化營業週表。新增 attractions.opening_hours（004 migration）、DTO／Google Adapter／原生表單及公開詳情；API 不增加端點、業務表不增加。設計與相近缺口見 [共用營業時間計畫](OPENING-HOURS-PLAN.md)，程式閱讀見 [模組指南](modules/opening-hours.md)。

更新：2026-10-18。使用者已授權實作，分支 `phase/03-attractions-media`。本階段屬 P0；不包含電影、消息、優惠管理、收藏保存或完整路線管理。實際結果見 [驗收記錄](PHASE-3-ACCEPTANCE.md)。

## 功能與來源

| 範圍 | 規格來源 | 本案實作選擇 |
| --- | --- | --- |
| 景點／美食／店家、博物館 | 功能規格 6／13／19／20 | 共用 attractions，商家標記 merchants；草稿／發布／下架與版本衝突 |
| 圖片上傳及替代文字 | 功能規格 19／20、圖片與安全要求 | E-18：真實解碼、重新編碼、UUID 檔名、受保護的持久化 volume |
| 首頁精選 | 功能規格 5 的景點部分 | 沿用原型的博物館／精選版位；其他首頁資料留階段 4／5 |
| 公開 HTML 與 head | SEO規格 C17／F17、功能規格 30 | E-12／18：前端 Node 產生完整 HTML，原子啟用版本；PHP 不輸出 View |
| 地圖網址匯入 | 功能規格 20 | E-19：Places API New、伺服器金鑰、候選確認；不下載 Google 圖片 |
| 路線引用保護 | 功能規格 19 | E-18：僅必要參照表及刪除保護；路線業務留 P1 |

## API 與對應畫面

以下路徑均加 `/api/v1`；契約以 [OpenAPI](../backend/resources/openapi.json) 為準。新增 15 個操作，累計 44 個。

| API | 對應頁面／用途 |
| --- | --- |
| GET／POST `/admin/attractions` | `/admin/places.html` 列表、搜尋、狀態、分頁；`/admin/place-add.html` 新增 |
| GET／PUT／DELETE `/admin/attractions/{id}` | `/admin/place-edit.html` 載入／修改；列表確認刪除 |
| POST `/admin/attractions/publish-retry` | 列表／編輯頁重試公開頁同步 |
| GET／POST `/admin/media` | 媒體分頁／multipart 圖片上傳；新增、編輯頁共用 |
| GET `/admin/media/access` | Nginx 媒體讀取檢查；公開引用或有權管理員才能讀取 |
| POST `/admin/places/resolve-map-url` | 新增／編輯頁解析地圖網址，確認候選後帶入表單 |
| GET `/publication/access` | Nginx 檢查公開 HTML 版本是否與目前資料一致 |
| GET `/attractions/resolve-url` | 歷史 slug、原型數字 ID 轉向；只導向本站已發布內容 |
| GET `/attractions` | `/explore.html`、`/food.html` 分類／行政區／搜尋／分頁 |
| GET `/attractions/{slug}` | `/places/{slug}.html` 真實詳情資料 |
| GET `/museum` | `/museum.html`、首頁博物館區塊使用相同資料 |

管理 API 沿用階段二 session、CSRF、首登限制、module/action 授權及唯讀模擬。媒體與地圖由 create／edit 情境判權；公開 API 不帶管理欄位。

## 新增資料表

Migration：`backend/database/migrations/002_attractions.sql`。另有已存在的 `002_admin_preview.sql`，兩者依完整檔名記錄，沒有覆寫既有 migration。

| 表 | 用途／關聯 |
| --- | --- |
| attractions | 內容、分類、座標、媒體 FK、slug、SEO、發布時間、版本與軟刪除 |
| merchants | 美食／在地店家的標記，FK attractions；優惠內容尚未實作 |
| media_assets | 儲存鍵、格式、尺寸、alt、來源／授權、模組與上傳管理員 |
| attraction_aliases | 歷史路徑 → attraction，避免舊 slug 被他筆占用 |
| content_publications | 公開版本產生狀態、時間、安全錯誤代碼 |
| routes | 僅路線引用保護所需的最小標頭 |
| route_attractions | 路線／景點關聯、順序及反查索引 |

新增七張，累計 20 張業務表，另有 schema_migrations 工具表。索引按管理列表、發布投影、媒體分頁及引用反查設計，EXPLAIN 使用 MariaDB 12.3 實際驗證。

## 功能交付與驗證順序

1. 共用準備：schema、DTO／enum、Repository、外部介面，保留原始 website。
2. 圖片能力：上傳 API、解碼／尺寸／大小檢查、持久化及草稿圖片存取控制。
3. 景點管理與發布：完整表單、版本、交易稽核、列表／博物館／首頁、HTML 與 SEO、排程與失敗重試、301／404、引用保護。
4. 地圖匯入：允許網域、逐跳解析、DNS／IP 限制、逾時／配額處理、候選確認及人工修正。
5. 整合驗收：真實 MariaDB／HTTP、前端與 PHP 品質檢查、實際瀏覽器上傳、手機／桌面、原始交付保留及文件更新。

每個完成的能力提交對應程式、契約、測試與中文指南。共用前置項目清楚標示為準備，不視為已完成畫面。階段四以後維持未開始。

## 複查修正的欄位與命令

003_attraction_review.sql 增 attractions.image_alt／image_source／image_license／recommendation_label，並補正座標 CHECK；表總數仍為 20。44 個 API 操作維持，POST／PUT attractions 擴充圖片說明及推薦文案欄位。新增 content:backfill-demo-labels 維護命令，預設只預覽，--apply 才交易更新符合原型且未編寫標籤的資料及稽核。PHP 類別更新後需重啟 publisher。驗收涵蓋不換圖修改說明、共用媒體隔離、原型順序／標籤、表單失敗與直接 DB 約束。
