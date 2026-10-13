# 專案開發流程與 Skills

設定日期：2026-10-13。適用架構：PHP 8.5、MariaDB 12.3、Docker、原生 JavaScript／jQuery。

本文件定義後續實作如何選擇 skills 及驗證改動。skills 與 Docker／Composer 品質工具環境已完成安裝；階段二認證已實作，實際驗收見 PHASE-2-ACCEPTANCE.md。安裝、命令與驗證結果見 [ENVIRONMENT-SETUP.md](ENVIRONMENT-SETUP.md)。

目標架構見 [ARCHITECTURE.md](ARCHITECTURE.md)：前端 HTML View＋PHP API，Controller／Service／Repository 分工。新功能使用 [DESIGN-TRACEABILITY.md](DESIGN-TRACEABILITY.md) 的 S／D／U／E 標記，並附模組閱讀指南與真實資料流。backend/ 與 frontend/ 於階段二從零建立，沒有後端遷移。

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

已完成環境與階段二：backend/ 的 API／schema／規則／測試與 frontend/ 來源已建立；tools/php/ 持續獨立管理第三方品質工具。後續業務功能依階段三起進行。

工具可執行不代表功能或分析已通過。環境結果見 ENVIRONMENT-SETUP.md；功能驗收以 PHASE-2-ACCEPTANCE.md 的實際結果為準。

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
| 開始前 | 核對架構、階段計畫、API／資料表／頁面契約、來源標記與工作樹；選擇當次 skills | 實作範圍與既有未提交改動清楚 |
| 設計 | 明確 DTO、Controller／Service／Repository 責任、交易、狀態、權限與失敗情境；按需要審查索引 | 能說明成功、拒絕及並行請求的行為 |
| 實作 | 按架構寫 PHP API／前端頁面與模組，保留原始 website；附中文業務來源註解和閱讀指南 | 新功能來自真正後端資料，不用模擬狀態代替 |
| 程式檢查 | PHP 語法、PHPStan、格式 dry-run、適用的 PHPat／Rector 檢查 | 有實際結果；工具缺少或失敗不當成通過 |
| 功能驗證 | 執行相應的真實 MariaDB／HTTP／JS 測試 | 註冊、session、越權、root 保護、token 重用及交易錯誤等可被驗證 |
| 安全與介面審查 | security-audit 審查相關變更；UI 以 web-design-guidelines 審查；browser 實際操作 | 明確問題已修正；必要的手機、鍵盤與錯誤流程有觀察 |
| 交付 | 更新 OpenAPI、需求與驗收紀錄，依 AGENTS.md 管理提交 | 計畫／已實作／已驗證狀態一致；自己的失誤併回對應提交 |

索引與效能審查以本案 MariaDB 12.3 為準，不能讓 skill 的 11.8 預設覆蓋既有設定。管理員少量分頁可按畫面需求使用 page／offset；大量深頁查詢再評估 cursor，不為 skill 改掉已確認的 API 契約。是否啟用觀測配置依實際診斷需要決定，不自動重啟正式資料庫。

第三方 Bash audit scripts 必須在具備相應依賴的 Linux／Docker 環境使用；Windows 工作流程以 rg、實際 PHP／HTTP 測試及可用工具適配。技能安裝不代表 Semgrep、Trivy、Gitleaks 等 scanner 已安裝或已執行。

## 6. 安裝來源與版本記錄

新增 skills 使用 skill-installer 的 GitHub helper 安裝至本專案 .agents/skills，來源固定在以下 commit；未修改第三方 SKILL.md 或參考內容。

| Skill | GitHub 路徑 | 固定來源 commit |
| --- | --- | --- |
| security-audit | netresearch/security-audit-skill：skills/security-audit | 89ec60423e9a3e41d86b34d679573852b7215fa6 |
| mariadb-query-optimization | MariaDB/skills：mariadb-query-optimization | 86623494f8051e766c973d35c1f07368c3b4c267 |
| web-design-guidelines | vercel-labs/agent-skills：skills/web-design-guidelines | 063bee94c3f4df8453406c830b0a7df0f2860278 |

php-modernization 是使用者已有的安裝，原本 skills-lock.json 保留不覆蓋。該檔由原安裝工具管理，目前只記錄 php-modernization；這次 helper 安裝的三個 skills 以本節的來源 commit 記錄，不假造原工具的 computedHash。它們是專案開發資源，不打包成網站公開資產。
