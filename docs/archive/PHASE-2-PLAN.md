# 階段二細部實作計畫：認證與管理權限

整理日期：2026-10-13；實作更新：2026-10-14。狀態：階段二已實作；驗收見 [PHASE-2-ACCEPTANCE.md](PHASE-2-ACCEPTANCE.md)。

前置環境已先完成 Composer／PHP 品質工具與 Mailpit 安裝，見 [ENVIRONMENT-SETUP.md](../ENVIRONMENT-SETUP.md)。本階段已從零建立認證、原計畫 26 個 API，加 U-06 唯讀模擬的 3 個，共 29 個；13 張業務表、migration 工具表及 64 個前端頁面來源。正式 SMTP 與正式條款仍待提供。

本計畫依原始 Excel「串接總覽」A9–A14、「功能規格」第 16、17、26、27 列，以及已整理的 API-only 架構設計 整理。端點、欄位與期限的工程補充身分不因已實作而改成規格硬性要求。

依使用者最新指定，後續改為 PHP API-only、前端 HTML View，架構見 [ARCHITECTURE.md](../ARCHITECTURE.md)。API、資料表及頁面的規格／草案／工程補充來源逐項見 [DESIGN-TRACEABILITY.md](../DESIGN-TRACEABILITY.md)。此文件保留從零建立的範圍與執行次序；實際程式閱讀指南位於 docs/modules/。

## 1. 完成後的操作結果

1. 管理員由獨立後台登入頁登入；後台 HTML 空殼先驗登入，未登入導向登入頁；不能讀取管理資料或操作 API。
2. 固定管理員能新增其他管理員、設定角色與逐人權限；資料保存在 MariaDB，重新登入仍相同。
3. 一般管理員只能開啟與操作被授權的功能，直接呼叫 API 也會驗權。
4. 訪客能以 Email、名稱、密碼及伺服器驗證碼註冊，接著真正登入／登出。
5. 會員可用寄到信箱的一次性連結重設密碼；一般管理員可走獨立的重設流程。
6. 帳號停用、刪除、改密碼及 session 撤銷在下一個請求生效；權限變更不必重新登入才生效。
7. 重要管理異動與認證事件有紀錄，紀錄不包含密碼、Cookie、驗證碼答案或重設 token。

本階段不完成景點、電影、消息、優惠與路線 CRUD，也不完成會員收藏／行程保存或會員查詢後台。這些仍依 [最新實作計畫](IMPLEMENTATION-PLAN.md) 的 P0／P1／P2 後續階段實作；後台未完成模組使用明確的待串接畫面，避免把 localStorage 操作呈現為正式保存。

## 2. 起始狀態與本階段建立範圍

開始階段二之前只有原始 website、環境工具、空的 MariaDB yinghua 與計畫文件。本階段所有應用程式與業務表均為新建，沒有遷移既有後端。

本階段從零建立 API 入口、路由、PDO、請求／回應、CSRF、認證、管理員與權限；原計畫 26 個 API，加 U-06 的 3 個，共 29 個；13 張業務表及 migration 工具中繼表。匿名 CSRF 工作階段與資料庫登入工作階段明確分開。

規格指定固定後台帳號為 admin，root 是其系統管理員身分；MariaDB 應用登入帳號仍為 spl，資料庫仍為 yinghua。這三者不是同一個帳號或名稱。

## 3. 目錄與程式分工

website/ 保留原始交付狀態。正式頁面沿用原型設計與網址，但 HTML、新 JS 與必要樣式放在 frontend/，PHP API 放 backend/。原始檔僅作參考，不直接修改。

| 位置 | 計畫用途 |
| --- | --- |
| backend/src/Controllers/ | 會員認證、管理員認證、管理員 CRUD、權限與密碼重設端點 |
| backend/src/Services/ | Password、AuthSession、Authentication、Authorization、Captcha、RateLimiter、PasswordReset、Mailer、Audit 服務 |
| backend/src/Repositories/ | 帳號、session、挑戰、權限與重設 token 的 PDO 查詢 |
| backend/src/Http/ | 請求解析、Middleware、統一錯誤與 HTTP 授權檢查 |
| backend/src/DTO/、Models/、Policies/ | 型別資料、安全輸出、帳號狀態與共用業務規則 |
| backend/src/Contracts/、Adapters/ | 有隔離需要的介面與 PHPMailer 等實作 |
| frontend/pages/ 的會員認證頁 | 會員登入、註冊、忘記密碼與重設密碼 |
| frontend/pages/admin/ | 後台登入、管理員列表／表單、權限矩陣與待串接畫面 |
| frontend/assets/js/ | 共用 UI、會員認證、後台認證、管理員表單與權限矩陣模組 |
| frontend/templates/、scripts/ | 共用版型建置，產生 Nginx 提供的固定 HTML |
| backend/database/migrations/ | 首次建立 migration 工具與 001 起的認證／授權 schema |
| backend/tests/ | 真實 MariaDB 的 API／授權／session／郵件整合測試 |
| docs/ | 本計畫、後續 PHASE-2-ACCEPTANCE.md 與初始化／重設操作說明 |

建立共用 api-client 的錯誤格式與 fetch 契約，包含登入／登出後 CSRF 更新、member／admin 身分區分與各自的登入逾時處理。會員 401 不應把後台使用者導向會員登入頁。

## 4. 執行次序

### 2A0：從零建立 API-only 應用架構

- 建立 backend/ 的應用 Composer manifest／autoload、PHP 入口、Router、HTTP Request／Response、錯誤格式、requestId 與 PDO。
- 建立 frontend/ 頁面來源、共用版型／建置與原生 JS／jQuery 模組，沿用原型外觀與 57 個網址；website 原始檔不改。
- 新增正式 Nginx 前端與 API 路由；區分 HTML 404、JSON 404／405。
- 建立 api-client、表單錯誤、通知與前後台登入 guard，業務權限由 API 驗證。
- 建立明確依賴組裝、DTO、PHPat 分層規則、應用測試與中文模組閱讀指南。

完成條件：新應用具備清楚邊界與測試；PHP HTTP 只提供 API，HTML 由前端產物提供。這是新建程式，不是遷移或重構既有後端。

### 2A：資料結構、認證核心與固定管理員初始化

Docker 中的 Composer、PHPStan、格式檢查、Rector、PHPat 與 PHPUnit 已先安裝；套件安裝在獨立 tools/php/；應用的 PSR-4 mapping、autoload、工具規則及分層測試均待本階段建立。依 [DEVELOPMENT-WORKFLOW.md](../DEVELOPMENT-WORKFLOW.md) 執行：PHP 實作使用 php-modernization；認證／授權完成後使用 security-audit；索引／查詢設計使用 mariadb-query-optimization；表單驗收使用 web-design-guidelines 與 browser。

- 建立本節 5.2／5.3 的 13 張業務表，包含帳號、角色、session、稽核與認證挑戰等；另建立 schema_migrations 工具表。
- 設計 admin_users 的 deleted_at 與版本欄位，以及 sessions 的 last_seen_at、閒置期限與撤銷索引。舊 must_change_password 暫留相容並清零，007 改預設 0；應用程式不再讀取。
- 管理員資料與權限矩陣增加版本欄位，供編輯衝突檢查；U-13 已取消首登強制改密碼，新帳號立即依授權使用功能。
- captcha 保存挑戰識別、答案的 HMAC、匿名工作階段綁定、用途、到期時間、嘗試次數與使用時間；低熵答案不以單純 SHA256 保存。
- 重設 token 保存高熵 token 的雜湊、member／admin 其中一種對象、用途、到期與使用時間。
- 認證事件和限速桶存於 MariaDB，使不同 PHP-FPM process 與容器重啟共用狀態；IP／帳號限速識別以 keyed hash 保存。
- 會員條款同意記錄包括版本、時間與來源。正式條款由客戶提供；本機測試版本明確標示，不能當成正式法律文件。
- 使用 password_hash／password_verify／password_needs_rehash 與 Argon2id；雜湊成本以容器實测調整。
- 增加 admin:init CLI，互動式輸入 Email 與密碼；不寫入固定預設密碼、不把密碼放在命令參數或提交檔案中。
- 初始化固定 admin／root 系統旗標；重跑初始化不重設已存在的帳號或密碼。
- 建立 DB constraint 與必要 trigger，保護固定帳號、系統旗標、root 角色、啟用狀態與禁止刪除；一般管理員不能被標成 root。登入時間等運作欄位可正常更新。
- 固定 admin 的密碼由本人安全改密碼流程或受控 CLI 處理；一般管理員 CRUD 不得藉由 password 欄位修改 root。

完成條件：migration 可對空資料庫首次建立並安全重跑；固定管理員可初始化；重跑不清空後續建立的資料；沒有預設密碼。

### 2B：管理員登入、session 與整個後台的保護

- 新增 /admin/login.html，以及獨立的登入／登出／me／CSRF API。
- 會員與管理員使用不同 Cookie／session namespace；Cookie path 覆蓋其實際頁面與 API 路徑，不誤設成只能供 /admin/ 使用。
- 登入憑證使用 random_bytes 產生的不可預測 token；資料庫保存雜湊。登入前匿名 session 不因為知道識別值就變成已登入身分。
- 登入成功後換發登入 token 並旋轉匿名 session／CSRF 資訊，處理 session fixation；登出撤銷 DB 記錄及移除對應 Cookie。
- 所有有效登入請求查詢 session、帳號啟用／刪除狀態及期限；停用或刪除帳號同時撤銷其所有 session。
- Cookie 使用 HttpOnly、SameSite=Lax；正式 HTTPS 啟用 Secure。本機 localhost HTTP 沿用開發設定。
- 後台 HTML 空殼不含私人資料；共用 guard 呼叫 /admin/auth/me，未登入導向後台登入，無頁面權限顯示拒絕存取。API 未登入回 401、無權限回 403，不依賴前端 guard 保護。
- returnTo 僅接受站內允許路徑，不接受外站、協定相對網址或登入頁循環。
- 登入與登出 POST 同樣校驗 CSRF；認證頁、回應與個人化頁面不被快取。
- 管理員 sidebar／topbar 顯示登入身分與登出。依 U-06 保留原版左下切換外觀，改為固定 root 才能使用的真實唯讀模擬；清楚顯示驗收對象與真正登入者，退出不依賴對象權限。流程見 [modules/admin-preview.md](../modules/admin-preview.md)。
- 尚未串接的後台模組保留路由與授權，但顯示待串接狀態，不載入舊管理員模擬 CRUD。

完成條件：未登入不能讀到後台私人資料或使用管理功能；固定管理員可以登入、登出；拿會員 Cookie 呼叫後台仍回 401。

### 2C：管理員 CRUD、權限矩陣與稽核

- 列表提供搜尋、分頁與啟用狀態；新增／編輯沿用獨立頁，刪除使用確認後的 API，不增加刪除專頁。
- 新增欄位包括 account、displayName、email、初始密碼、roleId 與 status。初始密碼遵循同一密碼政策；非 root 帳號不強制首登改密碼（U-13）。
- 輸入欄位採白名單；不能提交 is_root、任意 roleId、password_hash 或稽核操作者。
- account／email 的唯一性由 DB 約束保證，不只先做 SELECT；刪除採軟刪除並保留原識別，維持歷史稽核關聯。
- 一般管理員的 account 可修改（U-12），005 支援 Unicode 及唯一性；固定 admin 仍不可更改。
- 固定 admin 不顯示編輯／刪除／停用／降權操作；對直接送出的 PUT、DELETE、權限更新請求也回 403。
- 非 root 不可新增 root、升格 root、修改 root 身分或替換系統旗標；具權限的一般管理員可自我停用／軟刪除（U-13），並撤銷其 session；固定 root 與最後管理權仍保護。
- 角色依文件規劃 root、content-editor、news-editor、viewer 四種。此階段不增加任意角色 CRUD。
- 角色範本用來複製每個管理員的起始矩陣；建立時把完整 module/action 矩陣寫入個人權限表，不將日後的範本調整自動擴散到所有既有管理員。
- 一般管理員的逐人矩陣是有效權限來源；缺少的 module/action 預設拒絕。root 的完整權限由後端系統身分決定，不能從前端送出。
- 權限檢查按 module + view/create/edit/delete。新增／編輯頁也分別檢查 create/edit；sidebar 只呈現可查看模組。
- 提供權限定義、角色範本及可選目標的最小摘要，權限頁不為了選擇對象而取得不必要的管理員 Email 等資料。
- 權限選單最小摘要包含 id／account／displayName／isRoot；顯示「名稱（帳號）」以區分同名管理員，不回傳 Email。
- 採用規格允許的防越級策略：非 root 不得透過新增、角色套用或直接修改矩陣授出自己沒有的權限。
- 編輯、刪除及權限異動放在 transaction 中；寫入前重新檢查操作者與目標，使用版本／鎖定避免兩個操作者靜默覆蓋。
- 管理異動及其稽核同一交易保存；拒絕操作另記安全事件。before／after 僅用安全欄位，不記錄明文或雜湊密碼。
- 權限修改在下一個請求查 DB 生效，不把完整權限永久放在 Cookie 或前端 localStorage。

完成條件：管理員新增／編輯／停用／刪除與權限保存可實際操作；刷新仍保持；越級與 root 偽造請求失敗；失敗交易不留下半套權限或缺少稽核的資料。

### 2D：會員註冊、登入、登出與伺服器驗證碼

- 接上 /register.html、/login.html 與共用會員導覽，會員資料以 /auth/me 判定。
- 驗證 Email 格式與一致的正規化政策、名稱長度、密碼長度、確認密碼及條款同意；不修剪密碼或任意刪除 Email 的 +tag／點。
- 建議單因素密碼政策初值為 8–128 字元，允許密碼句、空白與 Unicode；不強制大小寫／數字／符號組合。密碼規則在前後端一致，最終由後端判定。
- 由伺服器產生圖形驗證碼，回 challengeToken、圖片與期限；JSON／DOM 不包含答案文字。
- 挑戰綁定匿名 session 及 register 用途；換新驗證碼使前一題失效。期限建議 5 分鐘、最多 3 次錯誤；成功、過期或耗盡次數後不可重用。
- 挑戰消耗與註冊建立使用 transaction／row lock，防止相同挑戰被並行提交成功兩次。
- 驗證碼刷新按鈕可鍵盤操作；錯誤關聯欄位與狀態播報。提供寄送 Email 驗證碼的替代入口，與圖形挑戰共用用途、限時與一次性規則，並另外限制寄送頻率，避免只有視覺方式才能註冊。
- 註冊後依 Excel API 草案自動建立會員 session，回 201 與安全會員摘要；重複 Email 回可理解的欄位錯誤。
- 登入只回統一的帳號或密碼錯誤，不區分未註冊、密碼錯誤或停用；未知帳號也走等價的密碼雜湊校驗，避免明顯時間差。
- 登出後該 session 不能再使用。其他分頁的前端畫面即使暫時未更新，下一次受保護 API 請求仍立即被拒絕。
- 登入、註冊、驗證碼取得／驗證採帳號與 IP 分層限速；限速更新使用原子操作，不放在單一 PHP process 記憶體。
- 移除正式頁面的舊 localStorage 登入身分判定；舊示範帳號、密碼與權限不自動匯入資料庫。

完成條件：真實註冊、登入、登出可操作；無效／過期／跨 session／重複挑戰失敗；會員不能取得管理員身分；重啟容器後限速仍有效。

### 2E：密碼重設、改密碼與開發寄信

- 會員及一般管理員各有忘記密碼與重設頁；兩種 token 用途不可互換。
- 忘記密碼統一回「若帳號有效，將寄送重設信」，不揭露 Email 是否存在；對申請與 token 校驗限速。
- token 高熵、短效、一次性，資料庫只存雜湊；新申請取消前一個可用 token。
- 重設網址使用設定的 APP_URL 建立，不信任使用者提供的 Host；頁面 no-store、noindex、Referrer-Policy: no-referrer，不載入第三方追蹤資產。
- 重設 POST 驗證 token、用途、期限與新密碼；改密碼、消耗 token、撤銷所有該帳號 session 在同一交易中完成。
- 成功後要求以新密碼重新登入，不因重設 token 而自動建立登入 session。
- 提供已登入改密碼流程，校驗目前密碼及 CSRF。固定 admin 的一般資料保持鎖定，但安全改密碼與 CLI 復原仍可用。
- 固定 admin 不接受一般管理員發起的代改密碼，也不走一般管理員 CRUD 的密碼欄位。
- SMTP 使用 PHPMailer 經 Composer 鎖定依賴；Docker build 安裝鎖定套件，主機不必先裝 Composer。
- 使用已安裝的可選 mail profile Mailpit，開始實作時串接寄信：SMTP 在容器網路內，郵件檢視介面僅綁 localhost，映像已鎖定 digest。
- 開發驗收由 docker compose --profile mail up -d --build 啟動寄信環境；正式部署則指定客戶 SMTP，不依賴 Mailpit。
- 郵件服務對外不回信件內容或 token；寄送失敗記錄可定位的錯誤，測試驗證重試與 token 不誤用。測試模式在 Mailpit 讀取信件，不在公開 API 提供 token 捷徑。
- 正式 SMTP 尚未提供時，只標記本機流程驗證完成；實際投遞、TLS 與正式寄件身分待部署環境驗證。

完成條件：本機信箱可取得連結並完成重設；舊密碼、舊 session、已使用／過期／錯誤用途 token 都失敗；本人更改密碼、session 撤銷與無強制首登流程均可驗證。

## 5. API 與頁面契約

階段二盤點：原計畫 26 個 API 加 U-06 的 3 個，共 29 個；13 張業務資料表（另有 migration 工具表）、完整串接 6 個既有頁面、新增 7 個頁面。另有 18 個既有後台頁面只加存取控制與待串接畫面。以下 API 與本階段頁面已實作；未來業務模組僅有授權入口，正式內容仍待後續階段。原計畫包含 E-06 Email 替代入口與 E-07 本人改密碼等額外方案（首登強制已依 U-13 撤除），數量不是 Excel 的硬性要求；完整來源見 DESIGN-TRACEABILITY.md。

### 5.1 API 與操作頁面逐項對照

原計畫 26 個端點之外，使用者新增 U-06：共用 sidebar 的 `GET／POST／DELETE /api/v1/admin/auth/preview` 三個端點，總數 29 個。沒有新增頁面／業務表；sessions 加 preview_admin_id，admin/auth/me 加雙身分資訊。三個端點的欄位、授權及頁面對照見 [modules/admin-preview.md](../modules/admin-preview.md)，完整契約同步 OpenAPI 2.1.0。

表內路徑都是對外路由；「新增頁」表示原型 website 沒有該檔案，實作時在 frontend/pages 建立 HTML，並不在 website 新增檔案。新增 API 數量按 HTTP 方法＋路徑計算，HEAD 不另外計數。

| 狀態 | 方法與路徑 | 用途／授權 | 對應頁面或共用位置 | 來源標記 |
| --- | --- | --- | --- | --- |
| 本階段新增 | GET /api/v1/auth/csrf | 匿名／會員流程 CSRF | register.html、login.html、會員密碼流程及共用登出 | S-SEC-09／E-02 |
| 本階段新增 | GET /api/v1/auth/captcha | 註冊圖形挑戰；匿名 session 與限速 | website/register.html | S-016／S-SEC-10／D：API 草案 |
| 本階段新增 | POST /api/v1/auth/captcha/email | Email 驗證碼替代方式；CSRF 與寄送限速 | website/register.html | S-016／E-06／D：API 草案 |
| 本階段新增 | POST /api/v1/auth/register | 註冊成功 201，建立會員 session | website/register.html | S-016／D：API 草案 |
| 本階段新增 | POST /api/v1/auth/login | 會員登入 | website/login.html | S-017／D：API 草案 |
| 本階段新增 | POST /api/v1/auth/logout | 撤銷目前會員 session，成功 204 | 所有前台頁面的共用會員導覽；不另建登出頁 | S-017／D：API 草案 |
| 本階段新增 | GET /api/v1/auth/me | 目前會員，未登入 401 | 共用會員導覽、會員改密碼頁 | S-017／D：API 草案 |
| 本階段新增 | POST /api/v1/auth/forgot-password | 會員重設申請，泛化回應 | /forgot-password.html（新增頁） | S-SEC-09／E-03 |
| 本階段新增 | POST /api/v1/auth/reset-password | 一次性 token 重設密碼 | /reset-password.html（新增頁） | S-SEC-09／E-03 |
| 本階段新增 | POST /api/v1/auth/change-password | 會員以目前密碼更新密碼 | /change-password.html（新增頁） | E-07 |
| 本階段新增 | GET /api/v1/admin/auth/csrf | 後台流程 CSRF | 後台登入、管理表單與後台密碼流程 | S-SEC-09／E-02 |
| 本階段新增 | POST /api/v1/admin/auth/login | 管理員登入 | /admin/login.html（新增頁） | S-SEC-09／S-PAGE／E-02 |
| 本階段新增 | POST /api/v1/admin/auth/logout | 撤銷目前管理員 session | 所有後台頁面的共用導覽；不另建登出頁 | S-SEC-09／E-02 |
| 本階段新增 | GET /api/v1/admin/auth/me | 真實管理員摘要與有效權限 | 共用後台導覽、權限設定及本人改密碼頁 | S-SEC-14／S-PAGE／E-02 |
| 本階段新增 | POST /api/v1/admin/auth/forgot-password | 一般管理員獨立重設申請 | /admin/forgot-password.html（新增頁） | S-SEC-09／E-03 |
| 本階段新增 | POST /api/v1/admin/auth/reset-password | 一般管理員 token 重設 | /admin/reset-password.html（新增頁） | S-SEC-09／E-03 |
| 本階段新增 | POST /api/v1/admin/auth/change-password | 本人改密碼，無首登強制（U-13） | /admin/change-password.html（新增頁） | E-07 |
| 本階段新增 | GET /api/v1/admin/admins | 搜尋／分頁／狀態篩選，admins.view | website/admin/admins.html | S-026／D：API 草案 |
| 本階段新增 | GET /api/v1/admin/admins/{id} | 單筆資料，admins.view；編輯頁另需 admins.edit | website/admin/admin-edit.html | S-026／E-13 |
| 本階段新增 | POST /api/v1/admin/admins | 新增一般管理員，admins.create | website/admin/admin-add.html | S-026／D：API 草案 |
| 本階段新增 | PUT /api/v1/admin/admins/{id} | 編輯／啟用／停用，admins.edit | website/admin/admin-edit.html；列表可共用狀態操作 | S-026／E-09／D：API 草案 |
| 本階段新增 | DELETE /api/v1/admin/admins/{id} | 軟刪除一般管理員，admins.delete | website/admin/admins.html 的確認操作；無刪除專頁 | S-026／E-09／D：API 草案 |
| 本階段新增 | GET /api/v1/admin/roles | 可選角色與起始矩陣；admins.create、admins.edit 或 permissions.view 其一 | website/admin/admin-add.html、admin-edit.html、permissions.html | S-027／E-13 |
| 本階段新增 | GET /api/v1/admin/permissions/catalog | 矩陣定義與目標最小摘要，permissions.view | website/admin/permissions.html | S-027／E-13 |
| 本階段新增 | GET /api/v1/admin/admins/{id}/permissions | 讀取目標矩陣，permissions.view | website/admin/permissions.html | S-027／D：API 草案 |
| 本階段新增 | PUT /api/v1/admin/admins/{id}/permissions | 保存矩陣，permissions.edit；拒絕 root 目標及越級授權 | website/admin/permissions.html | S-027／S-SEC-14／E-10／D：API 草案 |
| 使用者補充新增 | GET /api/v1/admin/auth/preview | 可模擬的一般管理員最小摘要；僅真正 root | frontend/templates/admin-sidebar.html 共用左下選單 | U-06／E-16 |
| 使用者補充新增 | POST /api/v1/admin/auth/preview | 開始／切換唯讀模擬；root＋CSRF，輪替 Cookie／token | 共用左下選單；不新增頁面 | U-06／E-16 |
| 使用者補充新增 | DELETE /api/v1/admin/auth/preview | 退出；root＋CSRF，不依賴模擬對象的權限／啟用狀態 | 共用左下「本人」及上方「退出模擬」 | U-06／E-16 |

圖形挑戰規劃由 captcha API 回傳 challengeToken、imageDataUrl 與 expiresAt，不額外增加圖片讀取 API。admin:init 與固定管理員 CLI 密碼復原是命令列操作，不新增公開初始化 API。目前沒有 health API；Docker 使用環境層的健康檢查，不列入這 29 個業務／認證端點。

### 5.2 本階段建立的五張認證輔助資料表

最初 schema 見 001_identity.sql；目前再套用 005 的帳號字元集／root trigger 與 007 的核准認證政策。完整現況見 DATABASE-SPECIFICATION-MAP.md。captcha_challenges 的表名見文件「資料表建議」第 23 列，auth_events 見「功能規格」I17；password_reset_tokens、auth_rate_limits、member_consents 是工程補充 E-03／E-04／E-05。逐表來源見 [DESIGN-TRACEABILITY.md](../DESIGN-TRACEABILITY.md)。所有時間以 UTC 保存；不在紀錄中保存原始 Cookie、密碼或可直接使用的 token。

| 新表 | 主要欄位草案 | 關聯／約束 | 對應功能與頁面 |
| --- | --- | --- | --- |
| captcha_challenges | id、token_hash、purpose、mode、answer_hmac、session_binding_hash、recipient_hash、attempt_count、max_attempts、expires_at、used_at、revoked_at、created_at | token_hash 唯一；綁定匿名 session；Email 模式另綁定註冊 Email；purpose 限 register；圖片及 Email 方式共用此表 | website/register.html 的挑戰取得／替代方式／註冊校驗 |
| password_reset_tokens | id、member_id、admin_id、token_hash、purpose、expires_at、used_at、revoked_at、created_at | member_id／admin_id 必須且只能擇一，分別 FK 帳號表；token_hash 唯一；用途隔離；重送撤銷舊 token | 會員及一般管理員忘記密碼／重設密碼頁 |
| auth_events | id、member_id、admin_id、event_type、outcome、reason_code、account_hash、ip_hash、user_agent_hash、request_id、created_at | 對象帳號可空，例如不存在帳號的失敗登入；對象時間索引；與計畫中的 audit_logs 的管理異動紀錄分開 | 註冊、登入、登出、鎖定、密碼異動與被拒絕的認證／授權請求；本階段不新增紀錄查詢頁 |
| auth_rate_limits | scope、bucket_key、window_start、attempt_count、locked_until、expires_at、updated_at | scope＋bucket_key＋window_start 唯一；原子更新；scope 區分帳號／IP、member／admin 與操作用途 | 登入、註冊、挑戰、Email 挑戰與密碼重設的共用限速；無獨立頁面 |
| member_consents | id、member_id、document_key、document_version、accepted_at、source、ip_hash | member_id FK members；member_id＋document_key＋document_version 唯一；document_key 區分條款／隱私 | website/register.html 的條款／隱私同意及版本紀錄 |

### 5.3 本階段建立的八張核心資料表

下列八張與 5.2 的五張均於本階段首次建立；正式 DDL 見 001_identity.sql，沒有既有／調整／遷移狀態。

| 預計新表 | 欄位／設計重點 | 對應功能 |
| --- | --- | --- |
| members | UUID、Email 唯一、名稱、密碼雜湊、狀態、驗證／登入時間、timestamps、deleted_at | 註冊、登入、目前身分、密碼流程 |
| admin_users | UUID、account／Email 唯一、名稱、雜湊、role_id、status、is_root、must_change_password、version、permissions_version、timestamps、deleted_at | 管理員認證、CRUD、root 保護 |
| sessions | member_id／admin_id 擇一、token_hash、expires_at、idle_expires_at、last_seen_at、revoked_at、隱私摘要 | 登入、期限、登出及全裝置撤銷 |
| roles | root、content-editor、news-editor、viewer 的角色字典與系統標記 | 角色下拉選單、初始化 |
| permissions | module＋action 定義及唯一約束 | 權限矩陣與逐請求授權 |
| role_permissions | role_id＋permission_id 唯一、allowed | 角色起始範本 |
| admin_role_permissions | admin_id＋permission_id 唯一、allowed、updated_by、updated_at | 完整個人矩陣、版本由 admin_users 控制 |
| audit_logs | actor_admin_id、action、resource、安全 before／after、request_id、created_at、隱私摘要 | 管理員及矩陣異動稽核 |

schema_migrations 已由本階段新建的 migration 工具管理，不計入 13 張業務表。每張表的規格／工程來源見 DESIGN-TRACEABILITY.md。

### 5.4 完整串接的六個既有網頁

| website 原始檔 | 正式網址 | 預計前端頁面來源 | 階段二內容 | 來源標記 |
| --- | --- | --- | --- | --- |
| website/register.html | /register.html | frontend/pages/register.html | 註冊、挑戰、Email 替代方式、同意版本、欄位錯誤 | S-016；Email 替代入口 E-06 |
| website/login.html | /login.html | frontend/pages/login.html | 會員登入、忘記密碼入口與返回原頁 | S-017 |
| website/admin/admins.html | /admin/admins.html | frontend/pages/admin/admins.html | 真實列表、搜尋／分頁／狀態、刪除確認與 root 鎖定 | S-026 |
| website/admin/admin-add.html | /admin/admin-add.html | frontend/pages/admin/admin-add.html（共用 admin-form.js） | 帳號資料、初始密碼、角色起始矩陣與狀態 | S-026 |
| website/admin/admin-edit.html | /admin/admin-edit.html | frontend/pages/admin/admin-edit.html（共用 admin-form.js） | 載回單筆、編輯／停用、版本衝突與 root 保護 | S-026 |
| website/admin/permissions.html | /admin/permissions.html | frontend/pages/admin/permissions.html | 選擇目標、套用角色起始值、載回／保存矩陣與越級限制 | S-027 |

### 5.5 原型沒有、預計新增的七個網頁

| website 對應 | 新增正式網址 | 預計前端頁面來源 | 功能 | 來源標記 |
| --- | --- | --- | --- | --- |
| 無 | /forgot-password.html | frontend/pages/forgot-password.html | 會員申請重設信 | S-SEC-09 要重設；獨立頁面 E-03 |
| 無 | /reset-password.html | frontend/pages/reset-password.html | 會員以信件 token 重設密碼 | S-SEC-09 要重設；獨立頁面 E-03 |
| 無 | /change-password.html | frontend/pages/change-password.html | 已登入會員用目前密碼改密碼 | E-07，本人改密碼補充 |
| 無 | /admin/login.html | frontend/pages/admin/login.html | 獨立管理員登入 | S-PAGE 要管理員登入；獨立頁面 E-02 |
| 無 | /admin/forgot-password.html | frontend/pages/admin/forgot-password.html | 一般管理員申請重設信 | S-SEC-09；後台獨立頁面 E-03 |
| 無 | /admin/reset-password.html | frontend/pages/admin/reset-password.html | 一般管理員以 token 重設密碼 | S-SEC-09；後台獨立頁面 E-03 |
| 無 | /admin/change-password.html | frontend/pages/admin/change-password.html | 本人改密碼及固定管理員安全改密碼，無首登強制 | E-07／U-13，無首登強制 |

新增頁明確納入前端建置與 Nginx 靜態路由，不依賴只掃 website 原型的 catalog。登出、刪除、啟用／停用及驗證碼切換均是現有頁的操作，不新增專頁。

### 5.6 其餘十八個後台原型頁面的存取控制

| website 原始頁面 | 所需權限 | 階段二處理／後續完整實作 |
| --- | --- | --- |
| website/admin/index.html | dashboard.view | 真實管理員身分與受保護入口；真實統計留 P2 階段十四 |
| website/admin/places.html、place-add.html、place-edit.html | attractions.view／create／edit，分別對應三頁 | 加授權及待串接畫面；內容與圖片 CRUD 留階段三 |
| website/admin/films.html、film-add.html、film-edit.html | films.view／create／edit | 加授權及待串接畫面；電影／場次 CRUD 留階段四 |
| website/admin/news.html、news-add.html、news-edit.html | news.view／create／edit | 加授權及待串接畫面；消息 CRUD 留 P1 階段九 |
| website/admin/offers.html、offer-add.html、offer-edit.html | offers.view／create／edit | 加授權及待串接畫面；優惠 CRUD 留 P1 階段九 |
| website/admin/routes.html、route-add.html、route-edit.html | routes.view／create／edit | 加授權及待串接畫面；推薦路線 CRUD 留 P1 階段十 |
| website/admin/members.html | members.view | 加授權及待串接畫面；會員查詢留 P2 階段十四 |
| website/admin/settings.html | settings.view；保存另需 settings.edit | 加授權及待串接畫面；SEO 設定留 P0 階段七；一般設定留 P2 階段十四 |

共用 site-header 影響所有前台頁面的會員身分與登出；共用 admin-sidebar／topbar 影響全部後台頁面的身分、登出與導航權限。website/planner.html 在階段二可做登入門檻，但必要保存排 P0 階段六，完整旅程管理排 P1 階段十一，也不在此新增行程 API。公開導覽的 /auth/me 回 401 只代表訪客，不自動將一般公開頁導向登入。

API 統一使用 code、message、fieldErrors、requestId；401 表示未登入／失效憑證，403 表示無權限／root 保護／CSRF 拒絕，422 表示欄位或挑戰錯誤，409 表示唯一性／編輯版本衝突，429 表示限速且提供 Retry-After。

更新 OpenAPI 的輸入、輸出、方法、狀態碼與安全要求；文件集合路徑混放 CRUD 的草案需整理成正式集合與單筆路徑。backend/resources/openapi.json 2.1.0 已列出 29 個 implemented 操作、明確輸入／輸出 schema 與 Cookie／CSRF 安全要求。

## 6. 前端銜接與尚未完成模組

- 不在既有 92 KB app.js 後面疊加登入 handlers。正式認證與管理員頁只載入新模組。
- 共用選單、通知與內容展示互動拆到新位置；需要暫留的公開展示程式移成受控的 legacy-public 模組，去除其中模擬登入／管理員與認證 localStorage 邏輯。
- 前端建置依頁面選擇共用版型與資產，不再無條件載入舊 app.js。Nginx 靜態路由一起調整，確保新增模組以 JavaScript MIME 提供。
- 真實會員導覽由後端／me 資料更新；使用 .text() 或輸出編碼顯示名稱，避免名稱成為 XSS。
- 管理員權限、按鈕與導航只由有效權限決定；後端仍逐請求檢查。
- 表單含送出中、成功、失敗、欄位錯誤、429 等待與 session 過期狀態；後端成功後才顯示保存成功。
- 景點等未完成後台操作不再假裝寫入成功；公開內容仍可展示原型資料。收藏／加入行程必要保存依 P0 階段六，完整旅程依 P1 階段十一，不在本階段自動匯入舊瀏覽器資料。

## 7. 建議設定初值

下列是可配置的開發初值，實作時以功能與負載測試調整，不當作 Excel 已指定數值。

| 項目 | 初值 |
| --- | --- |
| 密碼長度 | 8–128 字元，允許 Unicode／空白 |
| 管理員 session | 閒置 15 分鐘、總長 8 小時 |
| 會員 session | 閒置 30 分鐘、總長 24 小時 |
| 驗證碼 | 5 分鐘、最多 3 次錯誤 |
| 密碼重設 token | 30 分鐘、只可成功使用一次 |
| 帳號登入失敗 | 15 分鐘內 5 次後暫停嘗試；不永久鎖住帳號 |
| IP 登入限速 | 5 分鐘 30 次，另對驗證碼／註冊／寄信分別設定 |
| Email 挑戰／重設寄信 | 每個用途、每帳號每小時 3 次，另加 IP 上限 |

IP 判定使用可信的連線來源；只有明確配置的反向代理能影響 client IP，不能信任任意 X-Forwarded-For。

## 8. 測試與完成條件

每個子步驟完成即跑相應測試，最後執行完整回歸；不等到全部畫面寫完才驗證權限。

| 驗收面向 | 必測情境 |
| --- | --- |
| 首次建置與初始化 | 空資料庫首次建立 001；migration checksum／安全重跑；root 初始化不重置現存密碼；容器重啟不丟帳號 |
| 登入 | 正確／錯誤／未知／停用帳號；未知與錯誤帳號回應一致；登入前憑證不能固定成登入後憑證 |
| 身分分離 | 會員 Cookie 不能使用後台；後台 Cookie 不能冒充會員；同瀏覽器可同時使用兩種身分 |
| session | 登出、逾時、改密碼、停用、刪除後舊 token 失效；其他分頁下一次請求也失效 |
| 會員註冊 | Email 大小寫／重複／並行註冊；無效欄位；密碼與條款版本；挑戰期限／用途／工作階段／重用 |
| CAPTCHA | 前端無答案文字；並行消耗只成功一次；刷新使舊題失效；圖片與 Email 替代方式皆可完成 |
| 限速 | IP 與帳號分層；不同 FPM process 共用計數；重啟仍生效；到期可以再試 |
| CRUD 與 root | UI 無 root 操作；偽造 PUT／DELETE／權限／role／is_root／password 仍失敗；一般管理員軟刪除保留稽核 |
| 權限 | 完整 module/action 矩陣；未指定預設拒絕；套用角色不能越級；撤權下一請求生效；兩個操作者衝突 |
| CSRF | 缺少、錯誤、過期、其他工作階段的 token；登入、登出、註冊、密碼與管理異動皆覆蓋 |
| 重設／改密碼 | Mailpit 實際收到信；錯誤／到期／重用／member-admin 混用 token 失敗；並行重設只有一次成功；所有舊 session 撤銷 |
| 資料最小化 | API／稽核／日誌無密碼、password_hash、答案、Cookie、raw token；不能靠多送欄位升權 |
| 網頁流程 | 桌面／手機、鍵盤、欄位錯誤播報、刷新、直接網址、返回原頁與逾時跳轉 |
| 原始網站 | website 逐檔與第一筆原始提交一致；新增文件只在 project/docs |

應用測試從零建立：公開頁 200；後台靜態空殼不含私人資料；驗證 guard 的導向／拒絕提示及 API 的 401／403／授權後結果。另驗證 PDO、migration、安全錯誤格式、私有檔案阻擋與共用 API 工具。環境健康不算應用測試通過。

測試使用可辨識的可丟棄帳號與 transaction／清理程序；不以真實固定管理員作破壞性測試，不留下已知密碼的展示帳號。

交付包括可操作的登入與權限流程、首次 migration／seed 工具與 schema、API 契約、root 初始化／密碼復原文件、Mailpit 開發郵件流程與 PHASE-2-ACCEPTANCE.md。實際測過才填入完成結果，不先寫通過數字。

## 9. 外部條件、Git 與當前狀態

本機開始實作不需要正式主機或 SMTP 帳密：可先使用 localhost、Mailpit 與測試條款版本。正式部署前需客戶提供 SMTP／寄件身分、HTTPS 網域及正式條款／隱私版本。Email 是否必須驗證後才啟用會員，原規格未明確要求，預設按註冊成功即登入的草案，不自行加入強制啟用限制。

2A0–2E 是工作順序。階段二在獨立分支按完成的功能細分提交，各功能包含相應前後端、契約、驗證及閱讀指南；階段驗收後合併 main，不再以一筆提交包住整個階段。修正代理自身失誤併回對應功能提交，不另外算進度。前置工具及郵件容器已先完成安裝；API、認證邏輯、資料表與固定管理員已於本階段建立；本機驗收見 PHASE-2-ACCEPTANCE.md，正式 SMTP 與條款另待提供。Git 流程見 [GIT-WORKFLOW.md](../GIT-WORKFLOW.md)。

## 10. 工程依據

- [PHP password_hash](https://www.php.net/manual/en/function.password-hash.php)：密碼雜湊 API 與 Argon2id 支援。
- [PHP Session Management Basics](https://www.php.net/manual/en/features.session.security.management.php)：工作階段安全與識別更新。
- [OWASP Authorization](https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html)：最小權限、預設拒絕、逐請求授權。
- [OWASP Authentication](https://cheatsheetseries.owasp.org/cheatsheets/Authentication_Cheat_Sheet.html)：登入回應、密碼政策與限速。
- [OWASP Forgot Password](https://cheatsheetseries.owasp.org/cheatsheets/Forgot_Password_Cheat_Sheet.html)：一次性 token、泛化回應與重設後的登入處理。
- [PHPMailer](https://github.com/PHPMailer/PHPMailer)：SMTP 寄信套件與 Composer 安裝。
- [Mailpit Docker](https://mailpit.axllent.org/docs/install/docker/)：容器化的本機郵件驗證。

2026-10-18 U-15：確認密碼在註冊、會員／管理員重設與本人更改密碼，以及既有 CLI 確認提示均必填且逐字一致；密碼仍為8–128 Unicode字元，不修剪空白。後端共用 Input.confirmedPassword，前端 required 與一致性錯誤同步。管理員新增維持單一初始密碼欄；確認欄不落資料庫。

2026-10-19 再次檢查已修正固定 root 編輯頁及過時首登文案，帳號輸入長度交由 API 依 Unicode 字元判斷。008 migration 加強既有固定身分保護；不新增業務功能，詳見 [再次檢查](FULL-RECHECK-20261019.md)。

2026-10-19 再核對階段二的每項業主功能、限制與驗收條件；來源、程式與真實失敗／越權測試見 [階段二、三業主要求對照](PHASE-2-3-OWNER-ALIGNMENT.md)。正式TLS與完整讀屏驗收仍需在交付環境完成，不用本機HTTP測試替代。
