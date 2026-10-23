# Git 提交規則

- 第一筆提交保留原始交付的網站與規格，不修改該提交。
- 每一階段使用獨立分支；從最新 main 開始，完成驗收後以 merge commit 合併回 main，保留階段分支。未開始的階段不提前冒充實作成果。
- 按可獨立說明與驗證的功能提交；相關後端、前端、API 契約、測試及閱讀指南一起交付。共用前置環境另作明確的準備提交，不把整個階段塞成一筆，也不只按副檔名／目錄拆分。
- 提交應反映專案的實際進度。修正代理自身失誤時，將修正合併回對應的實作提交，不另留一筆進度提交。
- 合併前備份歷史，合併後確認最終檔案內容與原始交付提交都完整保留。
- 分支命名、功能提交及歷史整理方式見 [docs/GIT-WORKFLOW.md](docs/GIT-WORKFLOW.md)。

# 開發流程與 Skills

- 原始 `映畫竹塹_後台工程串接功能規格.xlsx` 是功能與限制的基準（使用者 2026-10-18 指定）。必填／選填、格式、條件式限制及驗收須追溯到工作表／儲存格；不能因欄位被列出就判為必填，也不能把文件未定義推定為全部選填。未定義處先核對原始 website 與使用者明確指示，不自行增加阻擋儲存／發布的業務限制；仍無依據者標示待確認。文件中的「建議／草案」保持建議性質。必要技術邊界及安全實作的具體設定標為 E，不冒稱規格硬性要求；既有程式或測試亦不能反過來充當規格來源。詳見 docs/DEVELOPMENT-WORKFLOW.md 的規格基準。

- 開發前讀取 [docs/DEVELOPMENT-WORKFLOW.md](docs/DEVELOPMENT-WORKFLOW.md)，依工作類型套用相關 skill，不預載所有參考文件。
- PHP 實作或重構使用 [.agents/skills/php-modernization/SKILL.md](.agents/skills/php-modernization/SKILL.md)。新程式採明確型別、DTO／enum 與適用的 PSR 規範；Rector 僅 dry-run，檢視結果後以有範圍的修改處理。
- 登入、session、密碼、授權及管理 API 完成後，使用 [.agents/skills/security-audit/SKILL.md](.agents/skills/security-audit/SKILL.md) 審查本次變更，並以真實 MariaDB 的失敗／越權測試驗證。
- 設計索引、分頁查詢或處理效能問題時，使用 [.agents/skills/mariadb-query-optimization/SKILL.md](.agents/skills/mariadb-query-optimization/SKILL.md)。版本以本專案 MariaDB 12.3 為準，依實際查詢驗證，不直接套用 skill 的預設版本或大量新增索引。
- 表單與介面驗收使用 [.agents/skills/web-design-guidelines/SKILL.md](.agents/skills/web-design-guidelines/SKILL.md)，範圍為本次修改的 frontend/ 頁面與資產；需要實際操作時搭配可用的 browser skill。
- website/ 保留原始交付檔案；新增後端程式放 backend/，新前端來源放 frontend/，新增工程文件放專案根目錄 docs/。維持 PHP＋MariaDB＋Docker、原生 JavaScript／jQuery 架構與原型設計。
- 後續架構依 [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md)：PHP 網站 HTTP 責任只提供 API；HTML View 在前端，Controller／Service／Repository 分工，使用 DTO、建構子依賴與適當共用規則。必要 CLI 不受 API-only 限制。
- 新功能與業務註解標出規格、文件草案、使用者指定或工程補充，來源 ID 見 [docs/DESIGN-TRACEABILITY.md](docs/DESIGN-TRACEABILITY.md)。完成模組提供中文閱讀指南，不能把工程提案寫成規格硬性要求。
- 每完成一個階段，必須在宣告完成及合併 main 前更新 [docs/PROJECT-GUIDE.md](docs/PROJECT-GUIDE.md)：更新日期、實際完成範圍、架構與檔案用途、頁面／API 狀態、資料表／migration、Docker／環境及操作命令；核對檔案清單與連結，不提前標記後續功能。功能開發期間有相關變更亦應隨功能提交同步維護。
- 計畫依 [docs/IMPLEMENTATION-PLAN.md](docs/IMPLEMENTATION-PLAN.md) 分為共用準備 1、P0 階段 2–8、P1 階段 9–12、P2 階段 13–15；依序完成優先級界線驗收後再進入下一級。必要前置能力標明用途，不提前冒充後續功能；重排不等於新增實作或授權。
- 使用者已授權實作階段三、四；認證／權限、景點／媒體／地圖／發布及電影／多場次已完成本機驗收，實際範圍見各階段驗收文件。階段五已於 2026-10-22 依授權實作並完成本機驗收，見 docs/PHASE-5-ACCEPTANCE.md。階段六已依授權於 phase/06-favorites-basic-itinerary 建立收藏／目前行程，2026-10-24 完成本機驗收，範圍與限制見 docs/PHASE-6-ACCEPTANCE.md；合併前須備份並核對完整內容。階段七以後未開始，依後續授權處理。U-16 只檢查放映日期及開始時間，結束時間延後。狀態與驗收只記錄實際結果。
- PHP、Composer 與品質檢查工具在 Docker 開發環境執行。缺少工具時先建立或如實記錄，不能把「未安裝／未執行」當成檢查通過。
