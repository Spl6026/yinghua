# 儀表板與最小稽核入口

2026-11-01，實作計畫14.3／E-36。這是營運工程補充，不冒稱Excel另有一列必做儀表板。版型沿原型卡片、最近更新與快速新增，示範數字改為真實資料。

GET /api/v1/admin/dashboard：需dashboard.view，並依attractions、films、news、offers、members各自view權限，才查詢／回傳其未刪除記錄總數。不是原型假數字、月增長或本週場次數；卡片明示未刪除資料總數。最近更新只含有權的四類內容，不列會員個資。快速入口另外依create與唯讀模擬限制，包含推薦行程。

GET /api/v1/admin/audit-logs?page=：重新讀取目前操作者、驗證dashboard.view及root；一般管理員及模擬一般帳號不可讀。只回id、actorId、action、resourceType、resourceId、createdAt，20筆；不回before/after JSON、IP、雜湊或憑證，也不提供匯出或刪除。root權限不新授予任何人。

| 檔案 | 用途 |
| --- | --- |
| backend/src/Repositories/OperationsRepository.php | 固定SQL識別符映射、模組計數／最近更新、稽核分頁白名單投影 |
| backend/src/Services/OperationsOverview.php | 授權後才查模組、裁切排序最近5筆、root稽核閘門 |
| backend/src/Controllers/OperationsController.php | API組裝；空counts輸出JSON物件而不是陣列 |
| frontend/pages/admin/index.html | 儀表板heading、status、重新載入與動態容器 |
| frontend/assets/js/pages/operations-overview.js | 真卡片、更新表格、權限快捷入口與root限定稽核面板／分頁 |
| frontend/assets/css/phase2.css | 沿用原型格線、快捷間距、窄版政策標題及可讀識別字換行 |
| backend/tests/phase14_integration.py | 真DB數量、隱藏模組完全不洩漏、HTTP非root拒絕、既有root的CLI唯讀成功投影 |

沿用各內容表／members／audit_logs，無新migration。root成功路徑以隔離DB既有root在可信CLI唯讀Service驗證，沒有建立root驗收帳號、變更正式帳號或簽發root Browser cookie。一般帳號Browser不顯示稽核面板。實際HTTP／UI證據界線見[驗收](../PHASE-14-ACCEPTANCE.md)。

2026-11-02重驗修正：最近更新與root稽核的DB無offset時間明確按UTC解析，用共用utc-display.js轉Asia/Taipei，欄位標示台北。API／排序／DB不變，跨日跨年、offset與未知值回歸通過；真Browser13:59:07→21:59:07及三尺寸確認，見[完整重驗](../P0-P1-P2-REVERIFICATION-20261102.md)。
