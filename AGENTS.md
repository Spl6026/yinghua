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
- 程式註解以一般繁體中文說明「為什麼」，寫給接手的開發者看，不夾帶 `[S：…]`、`E-xx`、`U-xx` 等內部代碼或階段／日期紀錄（使用者 2026-11-02 指定）。規格、文件草案、使用者指定與工程補充的來源追溯改記在 [docs/DESIGN-TRACEABILITY.md](docs/DESIGN-TRACEABILITY.md)，改寫前的代碼對照見 [docs/CODE-COMMENT-TRACEABILITY.md](docs/CODE-COMMENT-TRACEABILITY.md)。完成模組提供中文閱讀指南，不能把工程提案寫成規格硬性要求。
- 前端 JS／CSS 以 Prettier（`frontend/.prettierrc.json`，`npm run format`）、PHP 以 PHP-CS-Fixer PER-CS 維持格式；不寫成壓縮的單行程式。
- 文件分兩種：[docs/PROJECT-GUIDE.md](docs/PROJECT-GUIDE.md) 只描述目前的架構、檔案用途、API／資料表、Docker 與操作命令，架構或操作有變時直接改寫對應段落；每一輪實際完成的內容、驗證結果與待正式確認項目寫在 [docs/CHANGELOG.md](docs/CHANGELOG.md)（由新到舊）。功能提交時一併更新相關的模組指南（docs/modules/），不另寫獨立的驗收報告；歷史計畫與驗收在 docs/archive/，不再更新。（使用者 2026-11-03 指定）
- 原計畫的 15 個階段（共用準備 1、P0 2–8、P1 9–12、P2 13–15）本機範圍皆已完成，計畫見 [docs/archive/IMPLEMENTATION-PLAN.md](docs/archive/IMPLEMENTATION-PLAN.md)；各階段完成狀態見 [docs/CHANGELOG.md](docs/CHANGELOG.md)。新功能依使用者當次指示實作，不把未要求的項目當成已授權。
- PHP、Composer 與品質檢查工具在 Docker 開發環境執行。缺少工具時先建立或如實記錄，不能把「未安裝／未執行」當成檢查通過。
