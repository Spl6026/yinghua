# 專案開發流程與 Skills

2026-10-25 全 P0 複驗結果見 [最新報告](P0-REVERIFICATION-20261025.md)：保留首次失敗、有限診斷與受影響重驗；本輪 Semgrep／Gitleaks 已實際執行，不能再把前輪未執行當作目前狀態。P0 有已重現差項與未定位 503，仍未合併／未進入 P1。工具 fixture 或執行器錯誤與應用缺陷分開記錄，測試通過不作需求或介面正確的反向依據。

2026-10-24 階段八已依授權於 `phase/08-p0-validation` 實作：匿名health、DB故障503、獨立正式發布版／TLS、備份還原／回退及手機導覽焦點修正。**72 API／67固定頁／33表316欄／18 migration；無新DDL。** 本輪隔離部署、25項還原及9項回退通過；完整人工AA、上游映像風險與正式環境仍待驗，P0界線未通過，保留階段分支、尚未合併main或進入P1。實際結果見 [階段八紀錄](PHASE-8-ACCEPTANCE.md)、[16列來源矩陣](P0-ACCEPTANCE-MATRIX.md)、[安全差項](PHASE-8-SECURITY-REVIEW.md)；下方較早帶日期段落為當時紀錄。

設定日期：2026-10-13。適用架構：PHP 8.5、MariaDB 12.3、Docker、原生 JavaScript／jQuery。

本文件定義後續實作如何選擇 skills 及驗證改動。skills 與 Docker／Composer 品質工具環境已完成安裝；階段二認證、階段三景點／媒體／發布、階段四電影／多場次及階段五公開消息／優惠／首頁已實作，實際驗收分見 PHASE-2-ACCEPTANCE.md 至 PHASE-5-ACCEPTANCE.md。安裝、命令與驗證結果見 [ENVIRONMENT-SETUP.md](ENVIRONMENT-SETUP.md)。

目標架構見 [ARCHITECTURE.md](ARCHITECTURE.md)：前端 HTML View＋PHP API，Controller／Service／Repository 分工。新功能使用 [DESIGN-TRACEABILITY.md](DESIGN-TRACEABILITY.md) 的 S／D／U／E 標記，並附模組閱讀指南與真實資料流。backend/ 與 frontend/ 於階段二從零建立，沒有後端遷移。

## 規格基準（U-12，2026-10-18）

使用者指定原始 [工程串接功能規格](../映畫竹塹_後台工程串接功能規格.xlsx) 為作業基準。開發與驗收均依文件實際限制；工程文件、既有程式與測試不能自行擴張客戶要求。

- 欄位的必填／選填、格式、唯一性、範圍、條件式限制及驗收條件，逐項附工作表與儲存格來源。F 欄「主要資料欄位」只是欄位清單，不能直接解讀為全部必填。
- 未明定的項目標為「文件未定義」，再核對原始 website 的表單及使用者明確指示；不能推定全部必填或全部選填，也不自行增加阻擋儲存／發布的業務限制。仍有影響業務的歧義時，列出具體差異請使用者決定，繼續不受影響的工作。
- 「建議／草案」維持原文性質，不升格為硬性要求。使用者另行指定的技術、介面與流程持續標 U，不混寫為 Excel 要求。
- 文件要求安全防護、格式驗證或資源限制時，具體演算法、數值及儲存方式仍標 E。必要技術設定須說明用途；不能用「工程補充」名義自行增加業務必填政策。
- 既有實作的額外限制不因已存在或測試通過而成為規格。本次確認的是作業基準，未完成逐項校正前不得宣告既有程式全面符合；實際校正須同步處理前端、API／DTO、資料庫約束、契約與相關驗證，依 Git 規則歸回相應功能。

已核對的例子：功能規格 G13 僅寫「必要欄位」，沒有逐欄定義；F20／G20 沒有規定列表摘要／景點介紹必填，兩欄目前依 U-11 選填。圖片替代文字則見串接總覽 B12／功能規格 G7／資料表建議 D11 的明文及用途條件；不能把這些規則擴張成所有圖片相關欄位都必填。

2026-10-18 已完成既有階段二／三的 [規格校正](SPECIFICATION-ALIGNMENT.md) 與 [逐表對照](DATABASE-SPECIFICATION-MAP.md)。使用者明確核准 U-13 的八字密碼／取消首登／一般管理員自我操作；U-14 指定圖檔內容檢查範圍；U-15 最新指定確認密碼必填且一致。後續不得將舊15字、強制首登或一般帳號不可改名重新當成規格要求。固定 root 與最後管理權、授權／CSRF／session／安全雜湊維持。

## 1. Skills 的使用時機

| Skill | 狀態／來源 | 何時使用 | 在本專案的產出 |
| --- | --- | --- | --- |
| [php-modernization](../.agents/skills/php-modernization/SKILL.md) | 使用者已安裝；Netresearch，1.23.6 | 新增 PHP 邏輯、型別處理、DTO、重構或品質工具配置 | 明確型別、可分析的資料邊界、格式檢查、靜態分析與分層檢查 |
| [security-audit](../.agents/skills/security-audit/SKILL.md) | 本次新增；[Netresearch](https://github.com/netresearch/security-audit-skill)，2.11.8 | 認證、密碼、session、授權、管理 API 完成後的審查；後續上傳與地圖解析亦適用 | 有檔案位置與可重現情境的發現，以及相關越權／失敗流程驗證 |
| [mariadb-query-optimization](../.agents/skills/mariadb-query-optimization/SKILL.md) | 本次新增；[MariaDB 官方 skills](https://github.com/MariaDB/skills) | 索引與分頁設計、查詢審查、效能問題 | 查詢與索引對照、必要的 EXPLAIN 證據及符合實際版本的 SQL |
| [web-design-guidelines](../.agents/skills/web-design-guidelines/SKILL.md) | 本次新增；[Vercel Labs](https://github.com/vercel-labs/agent-skills)，1.0.0 | 新增或修改表單、導覽、錯誤提示後的 UI／無障礙審查 | 標籤、焦點、鍵盤操作、錯誤狀態、行動裝置與互動問題清單 |
| browser:control-in-app-browser | 既有環境 skill | 需要打開 localhost 實際操作驗收 | 桌面／手機、登入／登出、權限及表單流程的實際觀察 |
| spreadsheets:Spreadsheets | 既有環境 skill | 需要重新核對原始 Excel 需求 | 需求來源與功能對照；不改寫原始規格 |

只讀取當次工作需要的 skill 與參考檔。security-audit 的 TYPO3、雲端與 AI 功能不適用本案時不載入；php-modernization 的 Symfony／Doctrine／API Platform 範例不作為引入框架的理由。

web-design-guidelines 每次審查依其指示讀取當時的官方 guidelines，審查對象指定為本次修改的模板／資產。遵守既有版型與客戶設計，新增規則不能覆蓋使用者的要求。

## 2. 當前狀態

backend/ 的 API／schema／規則／測試與 frontend/ 來源已建立；tools/php/ 持續獨立管理第三方品質工具。後續業務功能依 [實作計畫](IMPLEMENTATION-PLAN.md) 重排：P0 階段 2–8、P1 階段 9–12、P2 階段 13–15；階段一為共用準備。目前階段一至七完成本機範圍；各輪結果與限制見 PHASE-2-ACCEPTANCE.md 至 PHASE-7-ACCEPTANCE.md。階段八已實作health／部署及復原基線與整合回歸，完整人工AA／正式條件與安全差項待補，見PHASE-8-ACCEPTANCE.md；尚未合併main，P0未完成，階段九以後未開始。

工具可執行不代表功能或分析已通過。環境結果見 ENVIRONMENT-SETUP.md；功能驗收以各階段 PHASE-*-ACCEPTANCE.md 的實際結果為準。

## 3. 階段二已建立的開發項目

1. 新建 backend/ 應用 manifest、PSR-4 autoload、API 入口、路由、PDO 與型別化分層；不能把工具套件目錄當成應用骨架。
2. 新建 frontend/ 頁面、共用版型與 API 模組，website 原型保留。
3. 對新程式建立 PHPStan level 10、PER-CS dry-run、Rector dry-run 與 PHPat 相依規則；不建立掩蓋新程式錯誤的 baseline。
4. 新建必要 PHPUnit 與真實 MariaDB／HTTP／郵件測試；無程式、無案例、未執行都不能寫成通過。
5. 建立 migrations／受控初始化、API 契約與中文閱讀指南，各功能標明規格或工程補充來源。

## 4. PHP 實作約定

- 邏輯與入口檔使用 strict_types，方法的參數與回傳值給定具體型別；新 View 為前端 HTML，不增加 PHP 網頁模板。
- 註冊、登入、管理員編輯、權限保存等輸入先驗證，再轉成 DTO；JSON／PDO 原始資料不直接當成可信的 domain object。
- 例如規劃 RegisterInput、LoginInput、AdminWriteInput、PermissionMatrixInput 等 DTO，實際命名隨模組調整；API 安全輸出也使用明確欄位。
- 帳號狀態、身分類型、權限 action 等有限集合適合 backed enum，集中其值與校驗。
- readonly 用在不可變的 DTO／value object；不套用到具有連線或 session 狀態的服務。資料庫連線、帳號及密碼來源保持明確。
- 密碼／token 的 PHP 參數在適用邊界使用 SensitiveParameter，錯誤與日誌仍需要明確去除敏感資訊。
- 新版本特性只在能簡化當前問題時使用；不為了出現 pipe、property hook 或 enum 而修改可讀且正常的程式。
- 適用時使用 PSR interface，例如需要可替換 logger 時採 PSR-3；不為通用範例而重建整套 HTTP 框架。

## 5. 每個實作子步驟的流程

| 步驟 | 動作 | 判斷完成的依據 |
| --- | --- | --- |
| 開始前 | 核對架構、優先級／階段計畫、API／資料表／頁面契約、來源標記與工作樹；選擇當次 skills | 範圍與未提交改動清楚；跨優先級前，上一優先級的界線驗收已完成 |
| 設計 | 明確 DTO、Controller／Service／Repository 責任、交易、狀態、權限與失敗情境；按需要審查索引 | 能說明成功、拒絕及並行請求的行為 |
| 實作 | 按架構寫 PHP API／前端頁面與模組，保留原始 website；附中文業務來源註解和閱讀指南 | 新功能來自真正後端資料，不用模擬狀態代替 |
| 程式檢查 | PHP 語法、PHPStan、格式 dry-run、適用的 PHPat／Rector 檢查 | 有實際結果；工具缺少或失敗不當成通過 |
| 功能驗證 | 執行相應的真實 MariaDB／HTTP／JS 測試 | 註冊、session、越權、root 保護、token 重用及交易錯誤等可被驗證 |
| 安全與介面審查 | security-audit 審查相關變更；UI 以 web-design-guidelines 審查；browser 實際操作 | 明確問題已修正；必要的手機、鍵盤與錯誤流程有觀察 |
| 交付 | 在該階段分支更新 OpenAPI、需求、驗收紀錄與 PROJECT-GUIDE.md；按完成的功能提交，階段驗收後合併 main | 計畫／已實作／已驗證狀態一致；全站導讀與實際檔案、頁面、API、資料庫及環境一致；自己的失誤併回對應功能提交 |

使用者指定每完成一個階段都更新 [全站架構與檔案導讀](PROJECT-GUIDE.md)，列為宣告完成及合併 main 前的必要交付檢查。更新日期與階段完成範圍，逐項核對架構、檔案用途／新增／移動／刪除、頁面與 API 的實作狀態、資料表／migration、Docker／環境變數及操作命令，並檢查清單與連結。沒有新增檔案的階段仍需核對既有內容並更新階段摘要；未完成的功能維持待開放，不以規劃代替實作。

索引與效能審查以本案 MariaDB 12.3 為準，不能讓 skill 的 11.8 預設覆蓋既有設定。管理員少量分頁可按畫面需求使用 page／offset；大量深頁查詢再評估 cursor，不為 skill 改掉已確認的 API 契約。是否啟用觀測配置依實際診斷需要決定，不自動重啟正式資料庫。

第三方 Bash audit scripts 必須在具備相應依賴的 Linux／Docker 環境使用；Windows 工作流程以 rg、實際 PHP／HTTP 測試及可用工具適配。技能安裝不代表 Semgrep、Trivy、Gitleaks 等 scanner 已安裝或已執行。

階段一至五的後續複驗使用 [固定清單 V01–V18](VERIFICATION-CHECKLIST.md)，依功能、狀態及跨模組流程逐項記錄，並區分當輪重跑與沿用的證據。新增已重現的缺陷須補相應行為回歸；修正後重驗影響範圍，不把工具通過或代表頁通過寫成全站所有情境無誤。建置完整性另用 `python backend/tests/frontend_integrity.py`；可附 `--base-url http://localhost:8080` 做唯讀 HTTP 檢查。

## 6. 安裝來源與版本記錄

新增 skills 使用 skill-installer 的 GitHub helper 安裝至本專案 .agents/skills，來源固定在以下 commit；未修改第三方 SKILL.md 或參考內容。

| Skill | GitHub 路徑 | 固定來源 commit |
| --- | --- | --- |
| security-audit | netresearch/security-audit-skill：skills/security-audit | 89ec60423e9a3e41d86b34d679573852b7215fa6 |
| mariadb-query-optimization | MariaDB/skills：mariadb-query-optimization | 86623494f8051e766c973d35c1f07368c3b4c267 |
| web-design-guidelines | vercel-labs/agent-skills：skills/web-design-guidelines | 063bee94c3f4df8453406c830b0a7df0f2860278 |

php-modernization 是使用者已有的安裝，原本 skills-lock.json 保留不覆蓋。該檔由原安裝工具管理，目前只記錄 php-modernization；這次 helper 安裝的三個 skills 以本節的來源 commit 記錄，不假造原工具的 computedHash。它們是專案開發資源，不打包成網站公開資產。

P0 → P1 → P2 依序推進，界線分別為階段 8／12／15。共用前置能力須標明當前用途；計畫重排不算實作或後續階段授權。實際驗收與正式服務差項分列，不以階段內的局部成果代替整個優先級完成。

## 7. 階段分支與功能提交

依使用者指定，每階段一個分支、每個完成的功能一筆提交。實作前從最新 main 建立該階段分支；共用前置項目、各功能與跨模組驗收分開，單一功能的前後端／契約／相應測試一起提交。驗收完成後以非 fast-forward merge 保留階段界線；不預先把未開始的階段列為完成。詳細命名、提交範例與歷史保存見 [GIT-WORKFLOW.md](GIT-WORKFLOW.md)。
