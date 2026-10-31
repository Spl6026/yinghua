# 階段十三：全站搜尋

2026-11-01。使用者授權規劃及實作；從P1驗收後main `34e40c5`建立`phase/13-site-search`。本文件為開發計畫，完成狀態另見驗收文件；不提前實作階段十四。

## 基準與設計

- S：原始Excel「功能規格」E18/G18/L18：電影、消息、景點、美食、優惠，類型分組、直接詳情、q上限、安全字元及分頁。F18列q、type、title、summary、slug、thumbnail。API草案仍為D，不把工程數值冒稱業主限制。
- website/search.html原型的主視覺、搜尋列、search-hit卡片、空白提示與無結果提示保留。新增類型選擇、分頁、載入／錯誤／重試與URL狀態，沿既有樣式；URL返回可重現查詢。
- E-35：`q`最多255個Unicode字元，允許一字中文與字面特殊字元；空白查詢回空清單供輸入提示，不掃全站；type為place／food／film／news／offer或空字串。20筆預設、最多100筆；依類型（景點、美食、電影、消息、優惠）、同名完全符合優先、UUID穩定排序。查詢不提供會員／管理／路線資料。

## 架構與資料流

PHP維持API-only：SearchController解析SearchQuery → PublicSearch協調發布版本 → SearchRepository執行參數化SQL → 搜尋結果白名單輸出。SearchDocument純投影將成功公開快照轉成文字與詳情URL，不查DB或HTTP。前端獨立搜尋控制器，不載入原型資料陣列。

新增`GET /api/v1/search?q=&type=&page=&pageSize=`，回items（id/type/title/summary/slug/url/thumbnail/thumbnailAlt）、total、page、pageSize、generation；422沿既有錯誤格式，發布不同版／失敗503，匿名可讀。無新增管理API。

新增可重建`search_documents`一表，migration `019_public_search.sql`。主鍵generation／resource_type／resource_id；只存公開白名單文字、縮圖與URL，非編輯主表。每次發布在同一交易完整寫入新版本，HTML完成後才清除舊版本；搜尋取得既有發布鎖，確認generation後查同版資料。全站共用指紋納投影規則，部署後強制重建。備份仍以業務DB／媒體／release為準，復原content:publish重建索引。

中文短字／包含搜尋先採LOCATE參數化字面匹配，非FULLTEXT分詞；不把%／_當萬用字。MariaDB12.3實際EXPLAIN／ANALYZE核對generation／類型索引與排序；不為包含查詢盲加title B-tree，若未來規模需要搜尋引擎另行規劃，不冒稱已大規模壓測。

## 功能提交與驗收

13A：共用公開搜尋投影準備、migration、發布一致性、DTO／enum、投影單元驗證及中文指南；尚不冒稱搜尋頁已交付。

13B：一個可完整使用的全站搜尋功能，包含PHP分層與API契約、真MariaDB安全／分組／分頁／EXPLAIN、原型搜尋頁、類型與分頁／返回、請求競爭與失敗重試、安全DOM、行動及鍵盤驗收、閱讀指南。

13C：完整前後端與P1受影響回歸、搜尋同步草稿／排程／改名／下架／過期／發布失敗、不洩漏私人資料、noindex／sitemap、原始69檔及清理、PROJECT-GUIDE更新。完成本機驗收才備份／核對tree並merge commit回main，保留分支。正式服務差項沿U-21交接。

依既有DTO／Service Layer／Repository／發布generation設計，參考[MariaDB LOCATE](https://mariadb.com/docs/server/reference/sql-functions/string-functions/locate)、[EXPLAIN](https://mariadb.com/docs/server/reference/sql-statements/administrative-sql-statements/analyze-and-explain-statements/explain)及既有公開投影流程；具體SQL以本專案12.3實測為準。
