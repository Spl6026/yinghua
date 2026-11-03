# 會員查詢閱讀指南

2026-11-01，階段14.1。S：原始Excel功能規格E25:L25；原型website/js/app.js會員列表。E-36：20筆、80字查詢、最多100000頁、字面LIKE、遮罩與模態摘要的具體實作。不提供會員新增／刪除／編輯或匯出。

資料流：frontend/pages/admin/members.html → assets/js/pages/member-directory.js → GET /api/v1/admin/members → MemberDirectoryController → MemberDirectory → MemberDirectoryRepository → members。

| 檔案（相對專案根目錄） | 用途 |
| --- | --- |
| backend/src/DTO/MemberSummary.php | 只持有id、名稱、email、狀態、加入日期；序列化前遮罩名稱與信箱兩側，不提供完整個資／憑證 |
| backend/src/Repositories/MemberDirectoryRepository.php | 參數化搜尋、count、created_at/id穩定分頁；排除已刪除；不SELECT密碼或驗證欄位 |
| backend/src/Services/MemberDirectory.php | 每次重新驗證members.view，無寫入副作用 |
| backend/src/Controllers/MemberDirectoryController.php | 查詢型別／長度／分頁邊界與HTTP回應 |
| frontend/pages/admin/members.html | 原型搜尋、五欄表格、分頁、可水平捲動區域與遮罩摘要dialog |
| frontend/assets/js/pages/member-directory.js | 即時／手動搜尋、最新請求優先、空／失敗與重試、上一／下一頁、摘要及焦點返回 |
| backend/tests/Unit/MemberSummaryTest.php | Unicode與完整Email遮罩 |
| frontend/tests/member-directory.test.mjs | 摘要焦點、舊回應不得蓋新結果、載入失敗恢復 |

既有Composition／admin.js為組裝入口；API完整契約在backend/resources/openapi.json。無新表／migration／環境變數。MariaDB12.3實際EXPLAIN在小資料集為ALL＋filesort；包含式搜尋本就需掃描，未據此新增大量索引或宣稱大規模效能通過。真資料庫／越權與原型驗收統一見[階段十四驗收](../archive/PHASE-14-ACCEPTANCE.md)。

2026-11-02重驗修正：會員加入時間沿共用utc-display.js以台北時區呈現，列表與摘要一致且標明台北；不變動遮罩與API資料。13:53:46 UTC→21:53:46，實際查詢／摘要與正反向Tab／Escape返回通過。新增formatter與VM真依賴配合回歸，最終267 JS，見[完整重驗](../archive/P0-P1-P2-REVERIFICATION-20261102.md)。
