# AA-01／AA-02 修正與閱讀指南

2026-10-29 AA 最終判定：依 U-20 不以 NVDA 為必要工具；內建 Browser／隔離 Orca 補驗及有效基線逐項覆核後，WCAG 2.1 A／AA 為39通過、10不適用、1已廢止，沒有未處理失敗準則。到期／錯誤摘要焦點、正反向Tab、草稿保留及174項前端回歸通過；[完整50項矩陣與證據界線](P0-AA-FINAL-20261029.md)。不宣稱本輪重跑所有真縮放或取得Orca完整錯誤字句的自動語音；已聚焦的文字錯誤依W3C 3.3.1／4.1.2判定，沒有改寫工具結果。主機讀屏未啟動，測試資料與臨時環境清理；API／DDL不變。這是目前P0工程AA驗收，不是正式server或第三方標章認證；未合併main／未開始P1。下方日期段落保留歷史。

2026-10-29 內建 Browser 補驗完成：40 個原型頁面桌手機成對對照、10 個核心頁面載入後 320px 重排，以及登入、權限、景點／圖片、電影、收藏／行程、SEO、到期草稿與鍵盤操作已實測。修正電影週期沿用範本日期的缺陷，171 項前端回歸通過；[逐項結果、檔案用途與 AA 證據界線](P0-BROWSER-VERIFICATION-20261029.md)。已清理隔離資料並恢復 SEO／TTL；API／資料表不變。新提示的 Windows NVDA 即時完整播報未列通過，完整 AA 及正式 server 簽核不冒稱完成；未合併 main。下方保留歷史結果。

日期：2026-10-26；分支 `phase/08-p0-validation`。依使用者「請將不通過的修改」處理。原始 Excel 與 website 不變。

## 來源與設計

S：Excel 功能規格 G34／L34、串接總覽 B15 的 WCAG／鍵盤／輔具要求。E-29：120 秒預警、期限標頭、原頁重新驗證及公開索引是具體工程設計，不是 Excel 原文規定的欄位或 API。參考 [W3C 時間可調整](https://www.w3.org/WAI/WCAG21/Understanding/timing-adjustable.html)、[多種方式](https://www.w3.org/WAI/WCAG21/Understanding/multiple-ways.html)、[APG 模態對話框](https://www.w3.org/WAI/ARIA/apg/patterns/dialog-modal/)。

## AA-01：登入到期

- 正常設定仍是閒置 30 分鐘、最長 12 小時。到期前兩分鐘提示；有效閒置期限可按一次「繼續保持登入」，不在背景持續送請求延長。
- 最長期限不變，接近上限要求原帳號密碼重新登入；過期寫入仍拒絕。重新驗證保留頁面表單，但不自動重送原儲存動作。保留表單本身不能取代 2.2.1 的預先警告與延長。
- 管理員唯讀模擬維持後端限制；有效模擬接近最長期限須先結束模擬，不能藉新提示繞過權限。跨分頁身分不同時阻止舊表單寫入並要求保留內容後重開。
- 密碼只供當次登入請求，用後清空；期限與識別只保存在記憶體，不把表單、密碼或憑證存入 localStorage。初次未登入仍走既有登入頁。

後端：`SessionTiming` DTO 經 SessionRepository → Authentication → Kernel 回傳五個 `X-Session-*` 唯讀標頭。沒有新 API 或資料表；OpenAPI 的共用說明、headers 及兩種 auth/me 回應定義同步。

前端檔案：`core/session-events.js` 負責請求事件／身分／過期回應隔離；`session-deadline.js` 用單調時間計算剩餘期限；`session-expiry.js` 協調提示與既有認證 API；`components/session-prompt.js` 提供具標題／描述、密碼欄、雙向 Tab 循環及 Escape 的原生 dialog。`session-monitor.js` 由固定頁建置與動態發布共用注入。既有 API client、管理表單及會員收藏／行程共用恢復邊界。

## AA-02：內容尋找途徑

頁尾新增「網站導覽」，連到 `/site-map.html`。分類瀏覽與名稱尋找都涵蓋同一公開發布快照；非當週電影、歷史優惠仍能找到。`scripts/public-content-index.mjs` 安全轉義文字／網址，`pages/public-index.js` 只篩選已發布 HTML 中的名稱，不查私人資料或草稿。無 JavaScript 時仍有完整分類與介紹連結。

這是最小公開導覽的名稱查找，不代表 P2 全站搜尋已完成。既有原型搜尋頁保持既定範圍。新增頁與其他公開內容共用 Nginx 發布就緒閘門，避免不同版快照混用。

## 實際驗證與界線

- Docker Node 回歸 171 項通過。PHPUnit 91 項／318 assertions 通過；PHPStan level 10／PHPat 無錯誤，七個相關 PHP 檔的 PHP-CS-Fixer dry-run 與 Rector dry-run 無修改建議。
- `backend/tests/session_deadlines_integration.py` 對真實 yinghua_test／HTTP 完成 51 項：兩種身分各延長 11 次、絕對期限不變、閒置不超過上限、到期拒絕讀寫、錯誤密碼、重新登入、舊 token 保持撤銷、CSRF、權限與停用帳號。只操作具名測試帳號。
- Brave 實測：分類中的非當週「風從城裡來」可按名稱找到並開啟真介紹頁；不依靠手貼介紹網址。
- 只在 app-test 暫改閒置期限為 180 秒，驗證實際自動預警、兩端 Tab／Shift+Tab 循環、一鍵延長及原焦點／內容保留。強制自有 session 到期後按儲存仍留在原頁；錯誤密碼顯示訊息，正確密碼恢復登入並清空密碼欄。資料庫確認沒有自動寫入；再次明確儲存後返回列表。接近絕對期限顯示重新驗證提示。
- 新提示於 320×844 CSS px 實測無整頁水平溢出，對話框約 280.5×476.3，按鈕與密碼欄可見。這是窄版重排，不能冒稱本輪重新做過實際 400% 全站巡查。
- 新提示的 Windows NVDA 採證未完成。啟動主機讀屏造成其他視窗朗讀干擾，已立即停止並確認沒有 NVDA 程序；專用視窗已關閉、尺寸覆寫已清除。後續不得自行重啟主機讀屏。先前既有介面的讀屏證據仍屬先前範圍，不能拿來代替新提示實測。
- 建置完整性：68 固定頁／16 資產／84 HTTP 檢查，問題 0。原始網站 69 檔保留；72 API／33 表 316 欄／18 migration 不變。測試帳號／資料已清理，隔離期限已恢復預設。

上述兩項缺陷已修正；新增提示完整輔具確認仍保留待驗，沒有宣告全站 AA 認證或合併 main。私有本機證據在被 Git 忽略的 `artifacts/p0-aa-repairs-20261026/`，不提交密碼、Cookie 或 NVDA 原始 log。
