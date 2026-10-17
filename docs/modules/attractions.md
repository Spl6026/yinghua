# 景點與發布程式閱讀指南

更新：2026-10-18。來源：功能規格 5／6／13／19／20、SEO規格 17，及 E-12／18。收藏／加入行程的伺服器保存留階段六；路線管理留階段十。

2026-10-23 操作狀態補充（E-18）：編輯頁確認「重新載入」且取得伺服器資料成功後，`attraction-form.js` 同時清空尚未上傳的圖片選擇，恢復原媒體；取消或讀取失敗時保留選檔。`attraction-form.test.mjs` 執行真正表單邏輯，驗證這三種狀態及重載後保存，不是只檢查程式字串。這是既有放棄修改流程的修正，不新增欄位或必填限制。

U-09／E-20 另新增共用營業週表：人工與 Google 帶入皆經 OpeningHours DTO、相同原生編輯器及既有管理交易；完整模型與檔案分工見 [營業時間指南](opening-hours.md)。API 的 hours 是短摘要／舊內容原文，openingHours 才是新的每週資料。

## 從哪裡讀

先看 `backend/config/routes.php`，再看 `AttractionController` → `AttractionManagement` → `AttractionRepository`。Controller 處理 HTTP 與輸入 DTO；Service 管授權、交易、版本、發布條件、引用檢查及稽核；Repository 集中 SQL，映射成 Attraction／AttractionPage。

`AttractionInput` 驗證欄位白名單、分類、slug、成對座標、網址、時區與版本。U-10後官網可HTTP／HTTPS，地圖仍HTTPS；GooglePlaceLinkPolicy要求新增／更換地點或改名稱／地址／座標後明確確認，或解除關聯。`ContentStatus` 是 draft／published／archived enum。`AttractionWriteResult` 將「已存資料」和「公開頁是否同步」分開，避免儲存成功卻誤稱發布成功。

`frontend/assets/js/pages/attraction-list.js` 控制清單、搜尋、分頁、刪除對話框。`attraction-form.js` 共用新增與編輯：讀取成功才開放修改，等待請求時禁止重複送出，衝突／載入失敗可重載，未儲存修改有提醒。共用請求在 `api/attractions-api.js`；它沿用 core/api-client.js 的 CSRF／錯誤處理。PHP 不產生表單 HTML。

## 儲存與發布資料流

1. 表單呼叫 POST／PUT，Kernel 檢查 session、CSRF、首登與唯讀模擬。
2. Service 取得發布鎖，交易內重查管理員權限、鎖定既有資料、比對 version、檢查圖片與博物館唯一設定，保存內容與稽核。
3. ContentPublication 從 DB 取得所有已發布且到期的資料與歷史網址，組成公開投影；版本雜湊包括模板版本與 origin。
4. NodeContentRenderer 透過無 shell 的程序參數呼叫 `frontend/scripts/publish.mjs`，JSON 由 stdin 傳入。實際 HTML 組合、編碼與 head 都在前端腳本；成功完成後原子切換 publication volume 的 current symlink。
5. API 與 Nginx 同時檢查已啟用版本。公開資料與新 DB 投影不一致時回 503，防止下架／生成失敗時繼續提供舊內容。成功同步回 200／201；已保存但生成失敗回 202，後台保留重試。

這是目前小型內容站的同步發布＋輪詢方案，沒有引入訊息佇列。每次編輯會重新產生景點公開集合；內容量擴大時再依量測拆分，不宣稱是大量內容的增量發布系統。

`publisher` 容器以 www-data 執行 `content:watch`，每 10 秒檢查排程／版本並重試；CLI `content:tick` 可只執行一次。排程邊界尚未產生新版本時，公開端短暫 503，而非洩漏錯誤版本。

## 公開頁與原型對照

`explore.html`／`food.html` 的卡片、分類、搜尋、行政區與分頁讀公開 API。無 JS 時 HTML 仍有第一頁可讀卡片。`museum.html`、`/places/{slug}.html` 直接含完整正文與 title／description／canonical／OG／Twitter。更新日期依台北時區顯示；DB 保存 UTC。

首頁沿用原型博物館介紹與精選圖片版位，讀取同一景點資料。電影、活動、優惠等首頁區塊尚待後續。新頁保留 frontend 原型 CSS、導覽、footer；新增樣式在 phase3.css。原始 website 保持不動。

`attraction_aliases` 保留舊 slug。舊網址只在目標仍公開時 301；下架／刪除後 404。原型 `place-detail.html?id=…` 僅用受控 legacy_id 對照，不以陣列索引推算新資料 ID。收藏按鈕仍屬原型互動，不能以此宣告階段六保存完成。

## 內容初始化與操作

```powershell
docker compose exec -T app php bin/console.php db:migrate
# 選用：匯入原始六筆展示內容，不覆寫已有筆；不是正式授權內容。
docker compose exec -u www-data -T app php bin/console.php content:seed-demo
# 不匯入示範資料時，先產生空內容版本。
docker compose exec -u www-data -T app php bin/console.php content:publish
```

`resources/attractions-demo.json` 只給明確 CLI seed 使用，網站讀取不退回該 JSON。原型外部圖片只允許受控 seed；新內容需上傳圖片。正式素材與來源授權須另外提供。`db:seed` 只初始化角色，不等於內容 seed。

刪除採軟刪除；被 route_attractions 引用時回 409，必須先調整路線。媒體不跟著刪除景點而破壞其他引用。歷代 HTML／未引用媒體目前保留在私有 volumes，沒有自動清理；備份與儲存量管理應同時涵蓋 DB、media 與 publication，不能只備份程式碼。

## 參考與驗證

原子版本切換為 E-18 工程選擇；Nginx 授權子請求依 [官方 auth_request 文件](https://nginx.org/en/docs/http/ngx_http_auth_request_module.html)。預先產生 HTML 的依據見 [架構文件](../ARCHITECTURE.md)。真實失敗／重試、越權、排程、引用保護、轉向與 XSS 證據見 [階段三驗收](../PHASE-3-ACCEPTANCE.md)。

## 圖片說明及原型資料的修正

R3-01：既有 POST／PUT attractions 現在接收 imageAlt／imageSource／imageLicense。欄位保存到各景點自己的欄位，未提供時保留，空白或 null 回到原資產說明；不改共用 media_assets。新增及編輯表單的說明可直接保存，不需要重新上傳圖片。上傳成功後帶入伺服器的正規化說明，和 mediaId 一起保存。

R3-02：recommendationLabel 是原型的「城市記憶／巷弄探索」等文案。資料庫、SSR 與 JS 卡片讀同一欄位；預設空白才顯示在地推薦。公開排序使用 publish_at DESC、id ASC，同一時間的六筆受控 seed 保持原型順序；後台清單恢復營業時間欄。

既有資料升級使用 003_attraction_review.sql，不改已套用的 SQL。新增座標約束明確要求兩者皆空或皆非空且合法；不合法的既存資料會阻止 DDL，不默默刪改。

更新後需重啟常駐 publisher，並以 www-data 執行 content:publish。舊展示資料標籤先執行 content:backfill-demo-labels 預覽，再加 --apply；只有未人工編寫且仍符合受控來源的展示筆會被補上，原始素材授權仍須確認。

2026-10-19（S E20／G6、E-24）：新增頁以DB原始範例基本資料試填，確認後只改表單，圖片／slug／發布旗標保留、舊Google關聯解除；GET samples需create，不開放一般管理列表。選填phone經PhoneNumber驗證，衍生唯讀phoneUrl，009 migration升級；館舍與景點公開HTML同步。資料流為 AttractionController.samples → AttractionManagement.samples → AttractionRepository.samples → Attraction.sampleArray；前端由 attractionSamplePicker／applyAttractionSample 載入與套用。
