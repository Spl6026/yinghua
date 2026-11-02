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
- 每完成一個階段，必須在宣告完成及合併 main 前更新 [docs/PROJECT-GUIDE.md](docs/PROJECT-GUIDE.md)：更新日期、實際完成範圍、架構與檔案用途、頁面／API 狀態、資料表／migration、Docker／環境及操作命令；核對檔案清單與連結，不提前標記後續功能。功能開發期間有相關變更亦應隨功能提交同步維護。
- 計畫依 [docs/IMPLEMENTATION-PLAN.md](docs/IMPLEMENTATION-PLAN.md) 分為共用準備 1、P0 階段 2–8、P1 階段 9–12、P2 階段 13–15；依序完成優先級界線驗收後再進入下一級。必要前置能力標明用途，不提前冒充後續功能；重排不等於新增實作或授權。
- 使用者已授權實作階段三、四；認證／權限、景點／媒體／地圖／發布及電影／多場次已完成本機驗收，實際範圍見各階段驗收文件。階段五已於 2026-10-22 依授權實作並完成本機驗收，見 docs/PHASE-5-ACCEPTANCE.md。階段六已依授權於 phase/06-favorites-basic-itinerary 建立收藏／目前行程，2026-10-24 完成本機驗收，範圍與限制見 docs/PHASE-6-ACCEPTANCE.md；合併前須備份並核對完整內容。階段七已另行授權並完成本機範圍；階段八已由使用者另行授權，於phase/08-p0-validation實作health／發布部署／復原及整合驗證；完整人工AA／正式環境與安全差項仍待驗，未合併main，見docs/PHASE-8-ACCEPTANCE.md。階段九以後未開始，P0界線通過後依後續授權處理。U-16 只檢查放映日期及開始時間，結束時間延後。狀態與驗收只記錄實際結果。
- 階段七已於 2026-10-24 依使用者授權在 phase/07-seo-settings-head 實作，7A–7E已完成本機範圍，見 docs/PHASE-7-ACCEPTANCE.md；不因前一條歷史狀態而視為未授權。範圍見 docs/PHASE-7-PLAN.md／docs/modules/seo-settings.md，本機驗收後依規則備份、核對完整 tree 並合併；正式差項與既有偶發驗證限制如實保留。
- PHP、Composer 與品質檢查工具在 Docker 開發環境執行。缺少工具時先建立或如實記錄，不能把「未安裝／未執行」當成檢查通過。

- 2026-10-30 最新狀態：階段八本機P0工程界線通過後已合併；階段九另依使用者授權於phase/09-news-offers-admin完成消息／優惠管理及四類詳情前後篇，本機驗收與基線／正式交接界線見docs/PHASE-9-ACCEPTANCE.md。89 API／68固定頁／33表316欄／18 migration；每功能提交與合併備份規則維持。階段十以後未開始，不因舊歷史的「階段九未開始」誤判本輪授權。

- 2026-10-30 階段十最新：使用者另行授權，在 phase/10-recommended-routes 實作路線 CRUD／排序／公開列表，96 API／68固定頁／33表327欄／19 migration。自動化結果見 docs/PHASE-10-ACCEPTANCE.md；Browser 連線失效，實際桌面／手機／鍵盤待驗，未合併 main。完整路線套用與會員旅程仍屬階段十一，尚未授權實作。保留原始69檔及既有隔離檔刪除差異。

- 2026-10-30 階段十最終：內建 Browser 已恢復並完成新增範圍實際驗收；四頁各1440／390／320共12筆，另12筆原始website比對。導覽選中與刪除後焦點已修及實測；PHP99／342、前端200、路線58／45契約回應零錯誤。96 API／68固定頁／33表327欄／19migration。本機範圍通過，備份與tree核對後依規則合併；既有正式及共用AA證據界線見docs/PHASE-10-ACCEPTANCE.md。使用者本輪另明確核准自有yinghua_test帳號routes僅view／僅create及恢復原權限測試，完成後清理。完整路線套用與階段十一仍未授權。

- 2026-10-31 階段十一最新：使用者授權於phase/11-member-itineraries完成多份本人行程CRUD、全量時間備註排序、推薦路線確認套用與P0相容；104 API／68固定頁／33表327欄／19 migration，無新DDL／環境。81整合／66契約回應0錯誤、107 PHP／208 JS、既有收藏69／current100及兩頁三尺寸Browser與原型對照通過，詳見docs/PHASE-11-ACCEPTANCE.md及docs/modules/member-itineraries.md。依備份／原始69檔／完整tree驗證後merge並保留分支；階段十二尚未授權或實作，P1整體未簽核。

- 2026-10-31 階段十二最新：已依使用者授權於phase/12-seo-indexing完成JSON-LD、同版robots／sitemap及分片、P1本機整合；104 PHP API＋3爬蟲GET＝107 HTTP契約，68固定頁／33表327欄／19migration，無新DDL／依賴／env。224 JS／107 PHP、P1 255／181契約、索引369、官方Schema.org Validator七型別及Browser21筆／40頁body保留通過。U-21正式網域未定先完成本機，Search Console／Bing／正式分享列交接，不冒稱通過；見docs/PHASE-12-ACCEPTANCE.md及docs/modules/search-index.md。備份／原始69檔／完整tree核對後以merge commit回main並保留階段分支；階段十三未授權實作。

- 2026-10-31 P1另輪驗收：使用者要求重驗，11列P1、255真API／181契約、369索引、224 JS／107 PHP／分層、18頁各三尺寸網站及原型共108筆與重新登入持久保存完成；無新程式／DDL／環境變更。正式平台依U-21交接，見docs/P1-ACCEPTANCE-20261031.md；驗收併入階段十二整合交付，P2階段十三未開始。

- 2026-11-01 階段十三：依使用者授權於phase/13-site-search完成全站搜尋、同版公開投影與原型互動。105 PHP API＋3爬蟲GET＝108 HTTP契約；68頁／34表338欄／20migration，無新env／依賴／服務。230 JS／112 PHP（370斷言）、113整合／28契約、P1 255／181契約與15筆Browser通過；新019只有PRIMARY，按實際EXPLAIN選擇。見docs/PHASE-13-ACCEPTANCE.md及docs/modules/site-search.md。依備份／原始69檔／完整tree核對後merge並保留分支；階段十四／十五未授權或實作，P2整體未完成。
