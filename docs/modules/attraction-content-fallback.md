# 景點摘要／介紹補位閱讀指南

2026-11-02 U-24 更正（取代下方 U-23 的 Google 顯示補位）：在後台確認 Google 候選「帶入欄位」時，列表摘要與景點介紹逐欄判斷；空白才帶入 editorialSummary，已有文字保持原樣，Google 沒有簡介則不填。只有按儲存才透過既有 API 保存。移除後台舊補位預覽與公開頁即時 Google 參考框；保留候選卡的原文及來源以供確認。超過既有欄位容量的原文不截斷、不填入並提示。沒有新 API／DDL／環境變數。既有摘要的網站介紹摘錄規則不在本次變更範圍。普通 Places API 不等於授權永久保存或任意修改 Google 內容；實際匯入保存仍須具備相應內容使用權。舊 content-preview／google-summary API 暫保留相容性，現行表單／公開產頁不再呼叫；下方相關說明屬舊設計紀錄。


2026-11-01。U-23：使用者明確啟動先前延後的補位；E-40：下述摘錄長度、API、安全邊界及顯示方式是工程實作，不是 Excel 新增的必填規則。欄位仍依 Excel／原型選填，發布條件沒有增加。

## 實際行為

列表摘要依「自填摘要 → 自填介紹摘錄 → 看介紹連結」。摘錄合併空白，最多120個 Unicode 字元，超長時最後一字為省略號。景點介紹依「自填介紹 → 已關聯 Google 地點的即時 editorialSummary 原文 → 尚未提供景點介紹」。空格、換行及 Unicode 分隔空白視為未填。

只有顯示時補位。管理 API 與資料庫始終保留管理員自填文字；改介紹後摘要重新計算，不會留下舊摘錄。清空自填內容即可恢復補位。Google 原文不截短、不改寫，不存資料庫、發布 HTML、SEO、JSON-LD、搜尋投影、檔案或 localStorage；只存在當次 HTTP 回應與頁面 DOM。列表不逐張呼叫 Google，無 JS 或服務失敗仍可看名稱、地址與原有空狀態。

後台新增／編輯的「內容補位預覽」預設收起。按預覽才請求共用政策，不儲存，也不對 Google 發請求；Google 情況顯示來源與可能無內容的說明。實際 Google 簡介需在已發布詳情頁查看。原有地點關聯參考介面保留。

## 分層與檔案用途

| 檔案 | 責任 |
| --- | --- |
| [AttractionContentPolicy.php](../../backend/src/Policies/AttractionContentPolicy.php)／[AttractionContent.php](../../backend/src/DTO/AttractionContent.php) | 純自家文案優先序／摘錄與 typed 結果；公開輸出及後台預覽共用 |
| [Attraction.php](../../backend/src/DTO/Attraction.php) | publicArray 提供顯示值、authoredContent 原值與 contentSource；管理輸出不改 |
| [AttractionContentController.php](../../backend/src/Controllers/AttractionContentController.php) | HTTP 輸入、預覽權限、呼叫 Service／Policy；不包含 SQL 或 Google HTTP |
| [AttractionSummary.php](../../backend/src/Services/AttractionSummary.php) | 已發布快照與即時 DB 版本核對、來源／地點／全站限速、並行鎖及請求後再次核對 |
| [PlaceSummaryProvider.php](../../backend/src/Contracts/PlaceSummaryProvider.php)／[GoogleSummaryProvider.php](../../backend/src/Adapters/GoogleSummaryProvider.php)／[GoogleSummary.php](../../backend/src/DTO/GoogleSummary.php) | 可替換的供應商邊界、固定 Google endpoint 與最小 field mask、原文／來源 DTO；失敗安全降級 |
| [ExternalRequestSlot.php](../../backend/src/Repositories/ExternalRequestSlot.php) | MariaDB GET_LOCK 實現每個資料庫最多一個並行外部簡介請求，finally 釋放 |
| [Composition.php](../../backend/src/Composition.php)／[openapi.json](../../backend/resources/openapi.json) | 建構子依賴組裝、兩個 HTTP 路由及完整契約 |
| [publish.mjs](../../frontend/scripts/publish.mjs) | 卡片／詳情／首頁博物館套用自家顯示內容；Google 只產生空狀態載入容器 |
| [google-summary.js](../../frontend/assets/js/components/google-summary.js) | 已發布詳情即時載入、textContent 原文、Google Maps 與提供者標示；失敗保留空狀態、不搶焦點 |
| [attraction-content-preview.js](../../frontend/assets/js/components/attraction-content-preview.js)／[attraction-form.js](../../frontend/assets/js/pages/attraction-form.js) | 小型收合預覽、呼叫後端相同規則及變更後提醒 |
| [phase3.css](../../frontend/assets/css/phase3.css) | 原文區與自家文字區分、來源字級與換行、窄版預覽 |

PHP 仍只提供 API，HTML 在前端；Controller → Service／Policy → Repository／Adapter，以 DTO 及建構子注入連接。參考原有專案分層及 Google [Place resource](https://developers.google.com/maps/documentation/places/web-service/reference/rest/v1/places)、[Places 政策](https://developers.google.com/maps/documentation/places/web-service/policies) 的原文呈現、保存與來源要求。

## API、資料與環境

- `POST /api/v1/admin/attractions/content-preview`：管理登入、CSRF，以及 attractions.create 或 edit；接受 summary／description／linked，回傳顯示文字與來源。不是儲存端點，唯讀模擬無法執行。
- `GET /api/v1/attractions/{slug}/google-summary`：只查伺服器保存的 Google ID；拒絕任意 query。無自填介紹且目前發布快照與 DB 同版才會呼叫供應商。未發布／不存在回404，其餘無內容或供應商失敗安全回 unavailable。
- 沿用 `auth_rate_limits`：來源10次／分鐘、地點6次／分鐘、全站30次／分鐘與100次／日；每 DB 一個並行請求。這是 E-40 費用／資源保護，正式 Google 專案仍需設定自己的每日配額及來源限制。不得把應用限速當供應商帳務保證。
- 沿用 `GOOGLE_PLACES_API_KEY`，沒有新 env、依賴、服務或 migration。34表338欄、20 migration 保持；HTTP 契約由113增為115（112 PHP API＋3爬蟲 GET），68固定頁不變。
- 回應 no-store；瀏覽器對 HTTP／HTTPS 提供者 URL 再次檢查，不接收 javascript／帶帳密連結。沒有讓匿名訪客提供 Place ID／任意 URL，也不傳出金鑰與上游錯誤內容。

## 驗證入口

[AttractionContentTest.php](../../backend/tests/Unit/AttractionContentTest.php)、[GoogleSummaryProviderTest.php](../../backend/tests/Unit/GoogleSummaryProviderTest.php) 驗自填優先、Unicode、120字、來源安全、Google 無資料／429／錯誤／缺 key。前端 [google-summary.test.mjs](../../frontend/tests/google-summary.test.mjs) 驗原文及失敗安全降級。

[content_fallback_validation.py](../../backend/tests/content_fallback_validation.py) 串接具名 yinghua_test 的真 MariaDB 並行／限速／發布競爭 probe，以及 [content_fallback_integration.py](../../backend/tests/content_fallback_integration.py) 的真 HTTP／OpenAPI 驗證。這些 probe 明確使用供應商替身，不冒稱外部 Google；另已用既有 Place ID 實測真實 Google available，僅保存狀態、48字長度與未落庫證據，不把原文留在測試檔。

操作前建立自有 fixture；測完依 phase14_cleanup 清理，不修改主庫。完整結果及正式服務界線見[階段十五驗收](../PHASE-15-ACCEPTANCE.md)。
