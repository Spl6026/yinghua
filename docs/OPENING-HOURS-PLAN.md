# 共用營業時間與匯入欄位檢查

2026-10-18，來源 U-09：使用者同意結構化營業時間，並指定非 Google 匯入的景點也使用同一方式填寫。此為階段三新增要求，不啟動其他階段。實作與驗收狀態另記末節。

## 採用設計

PHP API 接收 openingHours：固定 Asia/Taipei、備註、七天資料（0 週日至 6 週六）；每日可為未知、公休、全天或自訂時段，自訂最多四段。每段 HH:mm 開始／結束與是否次日；拒絕零長、超過一天、重疊（包含跨週）及不合法型別。跨日時段歸開始日。

attractions 新增 nullable JSON opening_hours，不新增表或端點；既有 hours 保留為摘要及舊內容原文。舊文字不推測成每日固定時間，編輯時放入備註，七天先為未知。手動建檔及 Google 候選都進入同一個每週編輯器；保存仍經版本、權限、交易、稽核與發布。

Google Adapter 讀 regularOpeningHours.periods，依 day 值映射，不解析語系文字、不假設陣列第一筆是星期日。缺資料為未知；24/7 有獨立處理；超過單日的連續時段拆分。weekdayDescriptions 只在無法安全映射時供人工核對，不把 currentOpeningHours 的特殊七天覆蓋常態週表。參考 [官方 OpeningHours](https://developers.google.com/maps/documentation/places/web-service/reference/rest/v1/places#OpeningHours)。

卡片／後台清單保留短摘要，公開詳情由前端產生每週表及備註。網站原型的字體、版型、導覽保持沿用；新增每週輸入為 U-09，欄位、限制及 DTO 拆分為工程補充 E-20。

## 其他已確認的相近情況（本次列管，未一併實作）

| 項目 | 現況及影響 | 建議 |
| --- | --- | --- |
| 地址與行政區 | MapResolver 帶入地址／座標，表單不更新 district；新增預設北區，東區地點可能仍存北區 | 從地址結構取得可辨認的新竹市行政區，帶入後仍供確認；無法判定時要求人工選取，勿猜測 |
| 官網網址 | Google websiteUri 可能 HTTP；AttractionInput 只收 HTTPS，帶入後可能 422 | 匯入時明示不符合網站規則；不要直接改 https 假裝已驗證可用，也不自動連線探測 |
| Google 地點關聯 | googlePlaceId 是隱藏欄位，手動改名稱／地址不會清除原關聯 | 顯示已關聯地點，提供解除／重新確認，避免把手動改後內容當成原 Google 地點 |
| 分類 | Google 不直接決定本網站的景點／美食／店家分類 | 目前由管理員選取是合理界線；匯入提示需明確，不將 Google types 無條件視為本網站分類 |
| 圖片／推薦標籤 | 圖片說明已有獨立保存；推薦標籤是人工文案 | 已修正圖片保存，不把 Google 評分轉成推薦文案、不匯入 Google 圖片 |

上述是程式與表單實際對照；未將推測的地址誤分類描述為所有現存資料已錯誤。其他項目需依後續授權處理，不增加 Google API 呼叫驗收成本。

## 驗證與交付順序

1. DTO／Google 邊界單元案例：跨日、跨週、全天、公休、未知、午休、排序、型別及重疊。
2. 既有 POST／PUT／GET、公開 API／HTML、唯讀／越權、舊版本、失敗不改資料，以真實隔離 MariaDB 驗證。
3. 表單讀回、Google 候選帶入與人工填寫共用流程；桌面／窄螢幕、鍵盤、動態欄位及錯誤提示驗收。
4. Docker 內 PHPStan／PHPat、PHPUnit、PER-CS dry-run、Rector dry-run、前端建置／Node；更新 OpenAPI、全站導讀、來源及模組指南。
5. 以一個可獨立說明的完整營業時間功能提交，完成驗收後 --no-ff 合併並保留階段分支；新需求不混入先前自身修正提交。

## 實際結果

已完成共用週表、Google Adapter、API 保存／相容邏輯及公開詳情；其他匯入項目只列管，沒有擴增實作。新增 004 migration，API 仍44操作、業務表仍20。開發資料庫先備份，主庫／隔離庫 migration 與發布均完成，舊時間文字未覆寫。

PHPStan level10／PHPat 通過；PHPUnit 27 tests／131 assertions；前端53項、64頁建置；既有景點65項、契約9項／44操作、先前修正21項及新週表21項真實 HTTP／MariaDB 通過。PER-CS dry-run 無差異；Rector dry-run 提出14檔風格建議，已檢視但未套用。未重跑 Google 實連或獨立掃描器。HTTP 初始化同一隔離管理員，改為依序執行；列表測試採唯一名稱避免舊中斷 fixture 干擾。

桌面瀏覽器實測新增頁的七天未知預設、編輯頁午休／跨日／公休／全天讀回；填入重疊時間會拒絕並保留輸入，具體錯誤在週表旁且焦點移至該組；修正為14:00–19:00並保存後，重開及公開詳情一致。手機覆寫請求390×844，實際355 CSS px／內容341，後台與公開週表無水平溢出、錯誤記錄空，臨時尺寸已還原。截圖位於 artifacts/opening-hours-admin-desktop.png、opening-hours-admin-mobile.png、opening-hours-public-mobile.png。未宣稱全站像素或精確390驗收。

人工安全審查：輸入白名單與有界七天／四段、嚴格時間／布林、跨週重疊拒絕；SQL 參數化，DTO 不依賴資料庫或 Google，所有週表文字 HTML 編碼。真實越權403、唯讀拒絕、舊版本409及不合法422皆已驗證；未把未安裝 scanner 當成通過。自有畫面驗收筆以既有軟刪除清理，正式內容未更動。
