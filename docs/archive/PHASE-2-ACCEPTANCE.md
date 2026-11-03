# 階段二驗收記錄

2026-10-18 規格校正已交付：回溯發現的 R-01–R-07 已處理；安全政策經 U-13 明確同意，掃描範圍依 U-14 定案。355 項隔離整合驗證、主庫保留資料升級及目前行為見 [Excel 基準校正](SPECIFICATION-ALIGNMENT.md)，逐表對照見 [資料庫對照](../DATABASE-SPECIFICATION-MAP.md)。以下較早日期的測試保留當時結果，不當作現行政策。

日期：2026-10-14。範圍：認證、管理員管理、逐人權限，以及共用 PHP API／前端架構。原始交付提交與 website／Excel 保留；後續業務模組不列為完成。

2026-10-17 規劃說明：後續工作已重排為 P0／P1／P2；本記錄保留各次驗收結果，文內舊「階段三至八」僅指當時尚未實作範圍。最新編號及新舊對照見 [IMPLEMENTATION-PLAN.md](IMPLEMENTATION-PLAN.md)，不表示重新執行驗收或新增業務成果。

## 實作內容

原計畫 26 個 API 加 U-06 唯讀模擬 3 個，共 29 個；13 張業務表與 schema_migrations 工具表；57 個原型網址對應的新前端來源，加七個認證頁，共 64 個固定 HTML。六個既有認證／管理頁及七個新增頁串接本階段 API；其餘 18 個後台頁只有登入與頁面權限門檻、真實身分及待開放提示。公開內容使用唯讀原型快照，未加入正式內容 CRUD／發布、收藏／行程或 SEO 動態產生。

PHP 不產生 HTML；Controller／Service／Repository、DTO、Policy、constructor DI 與 Adapter 分工已建立。自製 Kernel 共用 HTTP 檢查，參考 PSR-15 分工但沒有宣稱 PSR-7／PSR-15 介面相容。程式指南見 modules/authentication.md、modules/admin-permissions.md；API／表／頁面／來源對照在 PHASE-2-PLAN.md、DESIGN-TRACEABILITY.md。

## 實際執行結果

| 檢查 | 結果與範圍 |
| --- | --- |
| Docker | PHP 8.5.11、MariaDB 12.3.3、Nginx 1.30.5、Mailpit；app／web／db／mailpit 健康；Node 24 建置成功 |
| 真實 HTTP／MariaDB／Mailpit | api_integration.py 的 88 項檢查通過，固定使用 yinghua_test／8081；生產開發庫 yinghua 不清空 |
| SMTP 故障／復原 | mail_failure.py 的四項通過：驗證碼 503、忘記密碼維持 200、失敗 token 撤銷、郵件容器恢復後可重試 |
| 固定帳號 | 讀取本機 .env 的 root 登入／列表／40 權限／禁止刪除／登出檢查通過；重跑初始化不改原憑證 |
| PHPUnit | 五個業務規則測試／五個斷言通過 |
| PHPStan／PHPat | level 10 零錯誤、無 baseline；四條相依規則有效。另以暫時的 Controller→PDO 違規類別確認 PHPat 拒絕，隨即移除並重驗零錯誤 |
| PER-CS | PHP-CS-Fixer 在本次 PHP 上格式化後 dry-run 零待修改檔 |
| Rector | 僅 dry-run，零執行錯誤；六個建議都是移除 new 呼叫括號的語法偏好，為保持閱讀熟悉度未套用 |
| Composer | 應用 strict validate、platform requirements 通過；應用與工具 audit 無已知 advisory／abandoned |
| JavaScript | 三個 Node 測試通過：非法／跨身分 returnTo 與 Unicode 密碼邊界 |
| 前端建置 | Docker 建置 64 個 HTML；不用主機 Node，也沒有 npm 依賴 |
| 原始檔 | website 全部 68 個檔案與第一提交逐 byte 相同，無新增；根目錄 Excel 亦與第一提交一致 |
| 秘密 | .env 被忽略、未納入 Git；.env.example 不含密碼／APP_KEY；Docker image context 排除 .env |

88 項整合檢查涵蓋：匿名 401、CSRF／Origin、Cookie HttpOnly／SameSite、會員與後台隔離、完整 40 權限、root PUT／DELETE／矩陣及 DB 約束、mass assignment、重複帳號／Email、首登改密碼、改密碼撤銷所有 session、閒置／最長期限、即時撤權／停權、軟刪除、版本衝突及並行更新、越級與自我刪除拒絕、稽核遮罩與 append-only、稽核失敗交易回滾、圖形 PNG 不回答案、挑戰綁定／耗盡／過期／刷新／重用、條款同意、統一重設回應、token namespace／過期／重用、舊密碼失效、429 Retry-After、JSON 404／405／HEAD、私人檔案不公開。

原始測試輸出在忽略的 artifacts/，此文件只記錄結果。測試 root 密碼是每次隨機值；執行測試會撤銷 8081 測試 root 舊 session。測試不把已知展示密碼、token 或答案提交到 Git。

## 瀏覽器與介面審查

實際在連接的 Brave／Chromium 操作：管理員登入與 returnTo、真實列表與搜尋、固定 root 無編輯／刪除、40 個矩陣欄位、root 矩陣全勾選且禁止保存、註冊圖形挑戰呈現、手機尺寸導覽開關與 Escape、刪除確認的預設取消／Escape／焦點返回。手機覆寫尺寸 390×844，實際可用 viewport 約 355px；未觀察到頁面橫向溢出，表格另有橫向捲動區。

密碼變更、註冊及權限保存的成功／失敗以 HTTP 整合測試驗證；未把這些全部宣稱為瀏覽器點選驗收。測試畫面與日誌只在本機 artifacts 保留。

按 [Vercel Web Interface Guidelines](https://raw.githubusercontent.com/vercel-labs/web-interface-guidelines/main/command.md) 審查本次表單／模板／JS，修正記錄：

- frontend/assets/js/auth.js：submit 先綁定再發網路請求；認證表單另用 POST fallback，避免憑證進入網址。
- frontend/assets/js/core/ui.js：欄位關聯錯誤與首個錯誤焦點、aria-live、限速等待、草稿離頁提示。
- frontend/assets/js/components/dialog.js：每次重設 returnValue，Escape 不沿用前次確認，關閉後返回焦點。
- frontend/assets/js/pages/permissions.js：未保存切換確認、讀取時暫停保存、避免舊請求覆蓋新選擇。
- frontend/assets/js/pages/admin-list.js：URL 保留搜尋／狀態／頁碼，忽略過期回應。
- frontend/scripts/build.mjs／assets/css/phase2.css：label／name／autocomplete／spellcheck、可見焦點、表格捲動、reduced-motion。

此審查覆蓋本次認證與管理介面；原型公開內容的全站可及性、正式內容／SEO／圖片品質仍在後續整站驗收範圍。

## 原始版型對照（U-05）

前端以原始 website 的 HTML／CSS／render 函式為設計依據。style.css 包含字型載入，與原交付逐 byte 相同；phase2.css 移除登入卡片寬度、標題字級與通用卡片的改版規則。登入、後台外框／雙欄表單／矩陣、景點／映演／消息／優惠／路線／搜尋／詳情與文章導覽恢復原型結構。原型未開始實作的業務仍明示待開放，不恢復 localStorage 假認證或假保存。

本次實際驗收：

- 同一瀏覽器分頁、同一 viewport 比對原版與新版。桌面實際 1745×826，比對 14 個代表網址：登入、首頁、散策、美食、映演、新竹現場、活動、城市消息、優惠、路線、搜尋、電影／景點／優惠詳情。被量測的 header、hero、卡片／列表與詳情主要區塊數量相同，寬高差異不超過 1 CSS px，未觀察到頁面橫向溢出。
- 手機覆寫 390×844，實際 viewport 355×767；比對登入、首頁、散策、映演、消息、優惠與搜尋七頁。被量測主要區塊寬高相同，未觀察到頁面橫向溢出。會員登入外框恢復完整；導覽開關與 aria-expanded／開啟、關閉標籤同步。
- 真實隔離 API 的會員／管理員登入及 returnTo 可用；管理員列表載入且固定 root 無編輯／刪除入口；矩陣 40 個 checkbox，選固定 root 時全勾選、全 disabled、保存 disabled。手機表格在專用容器內橫向捲動，頁面沒有溢出。
- 新增管理員的側欄 240px、頂列 62px、標題位置／字型／字級、雙欄樣式與原版一致。額外狀態／初始密碼欄位使面板多一列；註冊的確認密碼與條款等真實欄位也保留，不宣稱這些面板與原版等高。
- 註冊 PNG 挑戰確實載入，手機沒有溢出且欄位都有標籤；未在瀏覽器解驗證碼或提交條款。會員登入後仍可查看原版三站行程卡，明示唯讀示意，沒有保存行程。
- Docker 重建 64 個 HTML，檢查每頁單一 main／唯一 ID、資產存在、認證／後台 input name 與 label、全部 JS 語法，均通過；三個既有 Node 測試重跑通過。website 全部 68 檔及根目錄 Excel 再次核對原交付 bytes，均一致。

本次只調整前端與文件，PHP／DB／API 契約沒有變動；88 項 HTTP／MariaDB 檢查沿用本階段前述結果，沒有在版型修正時重跑或虛增檢查數。這是代表頁面的實際尺寸與畫面檢查，不宣稱全站每個像素或外部圖片完成正式驗收。

對照 JSON、截圖與歷史備份僅在忽略的 artifacts/；程式閱讀與原版結構對應見 [modules/frontend.md](../modules/frontend.md)。依 AGENTS.md 先備份歷史，再將本次修正合併回階段二提交；不另外建立進度提交。

## 安全與查詢審查

本機網址登入修正：使用 127.0.0.1:8080 網頁時，原本登入 Origin 與 APP_URL 的 localhost:8080 不一致，API 回 CSRF_INVALID／403。Nginx 現在把網頁／資產轉到 APP_URL，保留路徑與 query；API 的來源／CSRF 判斷維持原有規則。後台登入 account 改回 autocomplete=username，並提示固定帳號 admin。

`python backend/tests/origin_redirect.py` 與 `--port 8081 --origin http://localhost:8081` 各 17 項通過：三種入口轉向、query 保留、canonical 不循環、偽造 Host 不控制目標、會員／後台 API 保持 JSON、有效來源通過 CSRF、不同來源／缺 CSRF／cross-site 仍拒絕。Nginx 配置檢查通過；Docker healthcheck 使用配置中的 Host，避免把容器內檢查轉到宿主機 port。另以獨立 HTTP Cookie jar 確認真正開發庫 `.env` 的 admin 登入、me／40 權限、登出及撤銷均通過，沒有重設密碼；瀏覽器實際從 127.0.0.1:8080/admin/login.html 到 localhost 的後台登入頁。新增紀錄屬本階段修正，合併回同一實作提交。

已套用 security-audit 的本案認證、API、秘密與日誌規則，以 [OWASP Authentication](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html)、[CSRF](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html)、[Forgot Password](https://cheatsheetseries.owasp.org/cheatsheets/Forgot_Password_Cheat_Sheet.html) 為參考，再由實際拒絕／故障測試驗證。修正涵蓋稽核個資 HMAC、更新重複 Email 的欄位錯誤、自己換角色的權限保護、交易死鎖重試及表單等待期間的預設提交。

沒有執行 Semgrep／Trivy／Gitleaks、正式 TLS／SMTP／外部弱點掃描；不把工具未安裝或正式環境未提供寫成通過。本次沒有上傳、XML、Google Maps 解析或雲端模組，相關檢查留對應阶段。

MariaDB 12.3 實際 EXPLAIN：session token 使用唯一 token_hash，type=const、估計一筆；啟用狀態列表使用 active_listing 並無 filesort；全部狀態列表仍使用此索引且有 filesort，在少量管理員資料下保留，不無故追加索引；權限目錄固定 40 筆，個人權限關聯用 PRIMARY、eq_ref。這是索引／查詢結構驗證，不是大量資料效能 benchmark。performance_schema 目前 OFF，本次未為靜態計畫檢查重啟 DB；正式效能診斷時再受控啟用。

## 使用者補充 U-06：固定 root 唯讀模擬

2026-10-14 新增使用者需求：固定系統管理員可以模擬一般管理員，使用者確認用途為唯讀驗收。原版左下身分選單外觀沿用，改由真實 session／MariaDB 控制，保留真正操作者、模擬提示與不依賴對象權限的退出。此為新增進度，不把功能來源改標為 Excel。操作及分層閱讀見 [modules/admin-preview.md](../modules/admin-preview.md)。

- `python backend/tests/admin_preview.py`：58 項通過，包含 root-only、一般管理員擁有全部權限仍拒絕、啟用／刪除／root 目標限制、CSRF／Origin、有效權限即時變更、全部管理寫入及密碼流程拒絕、雙身分保存、token 輪替、最長期限不延長、其他獨立 session 不受影響、並行切換只接受一個 token、失效對象仍可退出、首登待完成狀態、稽核失敗回滾與 root 復原撤銷模擬。
- 原本 HTTP／MariaDB／Mailpit 88 項整合檢查重跑通過。PHPStan level 10／四條 PHPat 相依規則零錯誤，PHPUnit 五個測試通過，PER-CS dry-run 零待修改檔；Rector 僅 dry-run，建議保留熟悉的括號寫法與明確條件，不自動套用。三個 Node 測試通過。
- 正常 8080 本機 admin 以獨立 HTTP Cookie jar 驗登入、me、40 權限與登出通過，未改帳密；8080 與 8081 各 17 項來源／轉向檢查通過。資料庫 migration 在開發／測試庫已套用，重跑成功，未新增業務表或清空資料。
- 瀏覽器在隔離 8081 驗實際下拉切換、雙身分提示、18 筆真實列表的刪除按鈕停用、編輯欄位／儲存停用、40 個矩陣 checkbox／套用／保存停用；零權限對象仍可退出恢復 admin。首次登入未完成與停用對象提示也實際確認可退出。
- 桌面實際 1920×965，側欄保留 240px；手機覆寫 390×844，實際 DOM 390×844，可從導覽切換，模擬提示與退出按鈕可見。被檢查畫面未觀察到橫向溢出。瀏覽器測試暫用 127.0.0.1 的測試 Origin，與開發庫 localhost Cookie 隔離；測試後已恢復測試配置、登出並關閉自己的分頁。
- Docker 重建 64 個 HTML；單一 main／唯一 ID、資產、欄位及全部 JS 語法檢查通過。website 的 68 檔與根目錄 Excel 保持原交付；原版 style.css 仍逐 byte 一致。

按 security-audit 審查 owner／effective 邊界、Cookie／CSRF、權限、唯讀 API 與稽核交易；web-design-guidelines 審查新增的標籤、錯誤提示、disabled 狀態、長名稱換行與手機退出。未執行外部弱點 scanner，不擴大宣稱範圍。結果／畫面量測只存在忽略的 artifacts/。

## 原始 website 全面複查補充

2026-10-14 依使用者要求再次核對全部 57 個原始網址；三個 subagent 分別審查公開內容、管理／認證與交付清單，主代理確認並更正。逐項問題、原型既有錯誤、合理差異及後續未完功能見 [WEBSITE-COMPARISON-REVIEW.md](WEBSITE-COMPARISON-REVIEW.md)。

本次實際重跑 88 項 API／MariaDB 與 58 項唯讀模擬檢查，均通過；新增 13 項表單狀態回歸，連同原三項共 16 項 Node 測試通過。PHPStan／四條 PHPat、五項 PHPUnit、PER-CS dry-run 通過；Rector 僅 dry-run，八檔建議未套用。Docker 建置 64 頁，HTTP bytes／資產／JS 語法／原交付 bytes 核對通過。

瀏覽器對照 35 個公開頁（1745×826）與七個手機頁（355×767），列表數相同且沒有觀察到橫向溢出；不沿用前次代表頁的「寬高差不超過 1px」宣稱到本次全站。隔離後台另驗草稿重載取消／確認、名稱（帳號）辨識、root／唯讀模擬的 40 欄與保存停用、退出及登出。註冊必要欄位、訪客 planner 門檻與修正優惠 slug 的內容差異均另列明。

本次 catalog 摘要新增 account，沒有新增路由／業務表。安全審查確認它仍須 permissions.view，回傳不含 Email；保存控制仍由 API 授權，前端停用僅是操作提示。測試庫與本機開發庫分離，未變更實際 admin 密碼。自行修正合併回對應實作提交，原始交付提交保持不變。

## 已登入登入頁與更改密碼返回修正

2026-10-14 使用者指出更改密碼頁下方固定回登入頁，已登入也能停在登入表單。會員／管理員更改頁已改為更改前回首頁／後台、更改成功撤銷 session 後再顯示「重新登入」；首登管理員完成更改前隱藏返回。登入／註冊頁以真實 me 導回已登入者，沿用 returnTo 白名單、獨立 namespace、首登與唯讀模擬規則；沒有放寬後端授權。

- Docker Node 新增十項認證導覽回歸，連同既有十六項，共二十六項通過。包含匿名／API 故障、合法／跨站原頁、兩種 namespace、首登／唯讀模擬、等待身分時提交、更改成功後連結，以及延遲 me 回應。
- 隔離 yinghua_test 的九項 HTTP／MariaDB 檢查通過：匿名、錯密碼、真正登入／me、管理 Cookie 不能充會員、跨 Origin 登出拒絕且保持登入、正常登出與撤銷。未重設密碼或清空資料。
- 瀏覽器在隔離 127.0.0.1:8081 真正登入會員／管理員，確認兩種更改頁的返回、已登入開登入頁導回、會員開註冊頁導回，以及兩種身分登出。沒有在瀏覽器變更密碼；成功後的 UI 由實際 JS 回歸測試驗證，後端撤銷沿用既有整合驗收，未虛增 88／58 項。
- Docker 建置 64 頁與 main／ID／資產／標籤／JS 語法／原交付 bytes 核對通過。本次 PHP、DB schema 及 API 契約未改；security-audit 審查導回白名單、匿名判斷、Cookie namespace、首登與唯讀規則，web-design-guidelines 審查連結語意、等待／成功狀態。未宣稱外部掃描通過。

修正合併回階段二的認證與密碼功能提交；原始交付、階段分支與合併節點保留。測試原始輸出在忽略的 artifacts/；閱讀見 [modules/authentication.md](../modules/authentication.md)。

## 認證導覽邊界再次複查

2026-10-15 使用者要求再檢查。以實際模組重現四種失敗：手機導覽重複初始化造成一次點擊切換兩次、註冊驗證碼尚未完成就能送出、登入／改密碼請求重複送出，以及改密碼的 me 尚未完成即可提交。已修正事件只綁一次、captcha／條款準備狀態、共用提交鎖與成功狀態，以及改密碼身分等待／失敗時的停用；另確認更新 captcha 不提早解除 429 等待。

- Docker Node 共 30 項通過；四項新測試，加上既有更改頁延遲案例加強。限速案例執行實際 submitForm，手機案例執行實際 initializeMenus，不只檢查 HTML。
- 本輪重跑隔離 yinghua_test 的九項 HTTP／MariaDB 登入、匿名、錯密碼、namespace、跨來源登出拒絕與撤銷檢查，通過；PHP、API 契約與 DB 沒有更改，未重跑或虛增原 88／58 項。
- Docker 建置 64 頁。按 security-audit 確認狀態仍由 me／後端授權決定，不以本機儲存冒充身分；按 web-design-guidelines 確認等待、失敗重試、連結與選單狀態。
- 本輪 browser 工具無法啟動，回報 Windows sandbox CreateProcessWithLogonW failed: 2；沒有執行新的瀏覽器點選驗收。先前會員／管理員返回與登入守門的瀏覽器紀錄保留，但不拿它當本輪新增邊界情境的實際操作結果。

修正仍合併回共用前端、認證與密碼的原有功能提交，不增加階段進度；website 與原始規格保留。完整前端狀態閱讀見 [modules/authentication.md](../modules/authentication.md)。

## 2026-10-17 階段二再驗收

本輪在瀏覽器連線恢復後重新操作階段二，使用 `127.0.0.1:8081`／`yinghua_test` 與隨機測試帳密；日常開發站的帳密未改動。原版由本機唯讀 HTTP 服務提供，沒有修改 website。正式內容模組仍不列為完成。

本輪發現並修正三項介面問題（E-07／E-14）：

- 首登管理員被導到改密碼頁後無法登出換帳號。新增「登出並切換帳號」，呼叫既有 logout API；首登限制保留，登出與改密碼互斥，等待身分時不可操作，失敗可重試，成功改密碼後只保留重新登入。
- 共用成功提示沿用 `.form-error` 的紅色。以 `data-error` 分別顯示綠色一般／成功提示及紅色錯誤；瀏覽器實際量得載入成功為 rgb(72,89,68)、欄位錯誤為 rgb(164,73,67)。
- 管理員重複 Email 保存失敗後重新載入，舊欄位提示與無效標記仍殘留。成功載入後清除舊錯誤；先以新增回歸重現失敗，修正後 Node 與實際瀏覽器均確認提示及無效標記數為零。

| 本輪檢查 | 實際結果 |
| --- | --- |
| HTTP／MariaDB／Mailpit | 88 項 API 整合、58 項唯讀模擬通過；沒有新增 API／資料表 |
| 首登登出補驗 | 五項隔離 HTTP 檢查通過：登入、me 仍要求改密碼、業務拒絕、允許登出、舊 session 失效 |
| SMTP 故障／復原 | 四項通過，Mailpit 已恢復 |
| 前端回歸 | 33 項通過；新增兩項首登登出／互斥與一項重載清除欄位錯誤 |
| PHP 品質 | PHPStan level 10／PHPat 零錯誤、PHPUnit 五項、PER-CS dry-run 零待修改；應用 Composer validate／platform requirements／audit 通過 |
| 建置／交付 | Docker 建置 64 頁，main／ID／資產／標籤／JS 語法檢查通過；68 個原始網站檔、根目錄 Excel 及原版 style.css 保留 |

瀏覽器實際操作與版型檢查：

- 桌面檢查九個認證頁與四個管理頁，並逐頁確認十八個後續業務頁的真實 root 身分與待開放提示。會員錯密碼／重試、登入 returnTo、已登入開登入／註冊頁、改密碼返回與登出均確認；後台登入、搜尋與 URL、名稱實際保存、root 直連編輯保護及 40 項矩陣鎖定均確認。
- 權限草稿切換取消、重新載入確認、一般零權限帳號的功能隱藏與直連拒絕、完整／零權限／首登帳號模擬與退出均確認。模擬時編輯欄位、40 個矩陣、套用及保存均停用。
- 使用者協助切換裝置模式後，完成同一批十三頁手機檢查。主要量測 440×956；部分操作曾讀到 330×717，以保存的各次實際量測為準。未觀察到頁面橫向溢出；列表容器寬 382、內容寬 550，使用容器捲動。公開及後台選單以 Enter 開啟、Escape 關閉，展開屬性同步；模擬提示／退出、首登登出、欄位錯誤焦點、刪除確認取消與焦點返回，以及另一分頁登出後原列表下一次請求回登入並保留 returnTo 均確認。
- 六個原型對應頁在桌面及手機完成結構／尺寸對照。桌面主要寬 1920，登入卡片寬 520、側欄寬 240、頂列高 62；手機登入／註冊卡片寬 400（440 viewport）。真實新增欄位、資料筆數、搜尋／分頁與專用表格捲動保留。裝置模式的 fullPage 圖片有縮放差異，另以 viewport 截圖及 DOM 量測確認，不宣稱逐像素相同或完整 WCAG／實體手機驗收。
- 註冊圖片與 Email 替代入口顯示正常，申請重設信的 UI 成功提示、兩種無效重設頁的錯誤／停用均確認。瀏覽器沒有解 CAPTCHA、提交條款、輸入新密碼或保存權限；這些成功／失敗與後端撤銷由前述真實 HTTP 測試驗證，改密碼成功後的前端狀態由實際 JS 回歸驗證。

本輪前端修正以 security-audit 審查 namespace／登出／互斥及首登限制，以 web-design-guidelines 審查表單訊息、焦點、連結及手機狀態。PHP 與 API 契約未改；未新增外部 scanner／正式 SMTP／TLS 驗收。此前另一次檢查曾出現單次 CSRF 403，重跑與針對性檢查未重現；本輪完整 88 項通過，不據此推定該次根因。

截圖、量測、隨機憑證與原始輸出只存忽略的 `artifacts/phase2-acceptance-20261017/` 等位置；憑證不提交。更正依既有規則合併回共用前端、密碼與管理員原功能提交，保留原始交付及階段分支，不另作新的業務進度。

## 待外部環境與後續階段

正式 SMTP／寄件身分／TLS 與正式條款待業主提供；本機 Mailpit 不證明外部送達。重設寄送失敗可重新申請，未建立持久郵件佇列。公開內容 CRUD、發布、搜尋、收藏與旅程、設定與完整 SEO／備份還原仍屬階段三至八，不列為階段二完成。

Git 只記錄本階段實際成果，沒有另外留下修正代理自身失誤的進度提交；第一筆原始交付未改寫。此次僅本機提交，不推送或覆寫遠端歷史。

## 2026-10-17 PHP 分層改善驗證

U-02／E-01：依使用者指定參考 Spring Boot 的責任分工，移除 Service 的 PDO 例外依賴；AccountRepository 只轉譯 duplicate key 為 AccountConflict。AdminManagement 專管帳號，PermissionManagement 專管個人矩陣，共用 RoleTemplates。AccountSummary、AdminPage、PermissionCatalog、PermissionMatrix、RoleTemplate、IdentityResponse、PreviewStatus、CaptchaResponse 定義輸出資料；Account 不再負責 API summary。設計來源與程式順序見 [PHP 分層閱讀指南](../modules/backend-layering.md)。

| 實際檢查 | 本輪結果 |
| --- | --- |
| Docker PHPStan level 10＋PHPat 五條分層規則 | 零錯誤，包含 Service 禁止依賴 PDO／PDOException／PDOStatement |
| Docker PHPUnit | 9 tests／29 assertions 通過；新增安全摘要、會員身分隔離、模擬操作者與驗證碼輸出邊界 |
| 真實 MariaDB 儲存例外與交易 | account_persistence.php 六項通過：帳號／Email 重複、編輯重複 Email、會員重複、外鍵失敗不誤轉與完整回滾 |
| 真實 HTTP／MariaDB／Mailpit | api_integration.py 原有 88 項通過 |
| 唯讀模擬 HTTP／MariaDB | admin_preview.py 原有 58 項通過，包含越權拒絕與稽核失敗回滾 |
| 回應欄位相容性 HTTP／MariaDB | response_contract.py 32 項通過，驗證身分、列表、角色、矩陣、模擬、衝突欄位與 204 空內容 |
| 郵件故障 | mail_failure.py 四項通過；本機 Mailpit 已恢復 |
| PER-CS 格式 dry-run | 78 個 PHP 檔案，零待修正 |
| Rector dry-run | 八個檔案有語法簡化建議、零執行錯誤；已檢閱，沒有套用與本次分層無關的語法改寫 |
| Composer audit --locked | 無已知漏洞或廢棄套件 |
| 歷史中間版本 | 七個相依改變的版本逐一通過 Docker PHP 語法／PHPStan level 10，含單元測試的版本亦通過；終點 78 個 PHP 檔案 |
| 分層規則反例 | 僅於忽略的歷史快照加入 Service → PDOException，PHPat 確實拒絕；驗證後移除反例，應用來源未加入 |

依 security-audit 審查本次授權／資料邊界：路由的身分與 module/action 旗標、Service 鎖定重驗、root／自我保護、防越級、版本與交易稽核保留。安全摘要不持有完整 Account；錯誤回應不包含 SQL 或內部例外。只在 yinghua_test 初始化隨機測試憑證，未變更開發庫管理員密碼。

frontend/、website/、OpenAPI JSON、schema、Composer 依賴皆未修改；本輪未重新操作瀏覽器，不宣稱重新驗收整站畫面。Semgrep／Trivy／Gitleaks、正式 SMTP／TLS 不在本次已執行檢查內。本次架構改善歸回相應階段二功能提交，保留原始交付與階段分支。


## 2026-10-18完整複查

已重新執行目前階段二、三適用的品質、真實HTTP／MariaDB／Mailpit、權限、媒體、發布、Google關聯及選填檢查；本輪結果、實際瀏覽器證據、環境復原與範圍限制見 [完整複查報告](FULL-REVIEW-20261018.md)。沒有新增業務或把後續功能標記完成；原始各輪數字保留，最新整體PHP37項／182斷言、前端64項、整合396項。
