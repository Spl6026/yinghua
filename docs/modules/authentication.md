# 認證程式閱讀與操作

階段二新增。`S-016` 是規格的註冊、`S-017` 是登入／登出、`S-SEC-09/10` 是認證與驗證碼要求。獨立後台流程、期限、Email 驗證碼替代入口、本人改密碼（首登強制已依 U-13 取消）與本機初始化方式屬 `E` 工程補充，逐項見 DESIGN-TRACEABILITY.md。

## 先看哪幾個檔案

1. `backend/config/routes.php`：29 個 API 的方法、路徑、身分及權限要求（原計畫 26＋U-06 模擬 3）。
2. `backend/src/Composition.php`：建構子 DI 的唯一組裝位置；同一請求的 Repository 共用同一 PDO。
3. `backend/src/Http/Kernel.php`：解析路由 → 匿名 Cookie → 必要登入 → 寫入 CSRF → 權限 → Controller；把錯誤轉成一致 JSON。
4. `backend/src/Controllers/AuthController.php`：把 JSON 轉成 DTO，呼叫 Service，再明確組合安全摘要與 Cookie。Controller 不寫 SQL。
5. `backend/src/Services/Authentication.php`：登入／註冊／登出與交易。`Captcha.php` 管挑戰，`PasswordReset.php` 管密碼流程。
6. `backend/src/Repositories/`：資料庫查詢與安全型別映射。`Account` 有內部密碼雜湊；API 使用不持有雜湊的 `AccountSummary`／`IdentityResponse`，驗證碼使用 `CaptchaResponse`。AccountRepository 把 duplicate key 轉成 `AccountConflict`，Authentication 在交易回滾後才轉為既有 409；Service 不判讀 PDO 錯誤碼。
7. `frontend/assets/js/auth.js`：共用會員／管理員表單入口；`api/auth-api.js` 與 `core/api-client.js` 管 HTTP／CSRF，`core/ui.js` 管錯誤與焦點。

前端保留原始 account-page／account-card 排版；共用版型、必要新增欄位與卡片程式閱讀見 [frontend.md](frontend.md)。

分層責任、Spring Boot 對照與輸出欄位白名單見 [PHP 分層閱讀指南](backend-layering.md)。

`core/session.js` 的 `redirectAuthenticated` 以對應 namespace 的 me 判斷登入／註冊頁：已登入返回通過白名單的原頁，未帶原頁時回首頁／後台；管理員同樣返回後台，不再依舊首登旗標轉向。只有 401 視為匿名，其他錯誤明示並保留登入重試。身分確認期間暫停提交，避免較舊 me 回應與另一筆登入交錯。`memberNavigationVersion` 排除舊回應，401 時恢復會員登入入口，不保留已登出的名稱。

`auth.js` 的 `checkingSession`、`submitting`、`completed` 控制讀取中、提交中及成功後的狀態；包含 Enter 的入口都經同一提交鎖。註冊還需 termsVersion 與有效 challengeToken，更新中或更新失敗不能送出，重新取得後才恢復；429 的 retryUntil 不因更新驗證碼而提早解除。`ui.js` 使用 WeakSet 記住已綁定的導覽節點，刷新會員狀態不會重複綁定手機選單／回頂端事件。

系統管理員唯讀模擬的雙身分與 session 流程見 [admin-preview.md](admin-preview.md)。Authentication.current 回傳 owner／effective；一般登入仍按本人權限，模擬時的 me 顯示被模擬者與真正操作者，Kernel 統一禁止業務寫入。

管理員更改密碼頁的 `data-auth-logout` 提供「登出並切換帳號」（E-07／E-14）。更改前可返回後台，也可透過既有 logout API 登出換帳號；不強制首登改密碼。`signingOut` 與 `submitting` 互斥，避免登出與密碼請求交錯；身分讀取中不能操作，網路失敗可重試，401 直接回登入，更改密碼成功後隱藏登出入口並顯示重新登入。這是操作流程補充，沒有新增 API 或放寬授權。

例如註冊：表單 → RegisterInput（欄位白名單、Email、密碼及同意）→ Authentication.register → 挑戰 row lock 與校驗 → 同交易建立 members／consents／session／事件 → 回 201、安全摘要與 HttpOnly Cookie。挑戰錯誤次數會提交；建立資料失敗則整筆交易回滾，不能留下半套會員資料。

## Cookie、期限與秘密

會員與管理員分別使用 `spl_member_session`／`spl_admin_session`，另有各自的簽章匿名 Cookie 與 CSRF。匿名識別不會變成登入識別；登入產生新的高熵 token，DB 只保存 SHA-256，登入、登出及密碼異動旋轉匿名 Cookie／CSRF。

Cookie 是 HttpOnly、SameSite=Lax、Path=/；本機 HTTP 不啟用 Secure，正式 HTTPS 必須設 `SESSION_SECURE=true` 與 HTTPS `APP_URL`。前端不讀 Cookie，也不把身分／權限存 localStorage。每次登入 API 讀取 DB 的 session／帳號狀態，權限 API 讀取最新個人矩陣。

密碼以 Argon2id 雜湊；8–128 Unicode 字元（U-13；確認密碼依 U-15 必填且逐字一致），允許空白，不修剪、不強迫字符組合。未知帳號也做同成本的 dummy 驗證。登入錯誤文字一致，限速用 DB 原子計數與 HMAC 識別，429 回 Retry-After；這是限速窗口，不永久停用帳號。

預設 session 最長 12 小時、閒置 30 分鐘；CSRF 一小時；挑戰五分鐘／最多三次錯誤；重設連結 30 分鐘／一次使用。期限在 `.env` 設定。圖片 JSON 只有 PNG、challengeToken 與期限；Email 模式另外綁定註冊 Email，答案只有郵件與圖像，不在 JSON／DOM。

## 管理員初始化與復原

本機後台入口是 http://localhost:8080/admin/login.html。登入帳號填 `admin`，不是初始化 Email，也不是資料庫使用者 `spl`；初始密碼可在根目錄、被 Git 忽略的 `.env` 中查看 `ADMIN_INITIAL_PASSWORD`。

網頁入口會由 Nginx 轉到 `.env` 的 `APP_URL`，所以從 http://127.0.0.1:8080/ 開啟會轉到 localhost，避免 Cookie 與登入 Origin 分屬兩個主機。不要混用 8080 開發站與 8081 隔離測試站；直接 API 呼叫仍需符合 APP_URL 的 Origin 與 CSRF。轉向使用設定中的固定目標，沒有放寬跨站登入。實作參考 [Nginx map](https://nginx.org/en/docs/http/ngx_http_map_module.html) 與 [return](https://nginx.org/en/docs/http/ngx_http_rewrite_module.html#return)。

```powershell
./scripts/setup-env.ps1
# 用編輯器開根目錄 .env，設定 ADMIN_EMAIL 和初始密碼。
./scripts/setup-admin.ps1
```

初始密碼由 setup-env 隨機建立，setup-admin 只經 stdin 傳給 CLI，不出現在命令參數／輸出。固定登入帳號是 admin，不是資料庫使用者 spl。初始化只建立缺少的固定 root；重跑不改 Email、密碼或已有帳號。`.env` 的 ADMIN_INITIAL_PASSWORD 是初始化輸入，並非 PHP 每次登入直接使用的密碼；本人改密碼後資料庫雜湊才是有效憑證。

固定 admin 不可透過一般 CRUD 編輯／刪除／停用／降權，只有本人目前密碼＋CSRF 的改密碼流程可以改密碼。固定 admin 不接收公開的忘記密碼連結；失去憑證時由有主機操作權的人用互動 CLI：

```powershell
docker compose exec app php bin/console.php admin:recover
```

輸入隱藏，完成後撤銷 root 全部舊 session 與重設 token。不要把密碼寫在命令參數、PR 或聊天中；一般管理員使用後台重設流程。

## 重設密碼與郵件

正常更改密碼頁底部在更改前是「回首頁」／「回後台」；U-13 取消首登門檻，一般管理員同樣可返回。API 更改成功並撤銷登入後，連結才改為「重新登入」，同時刷新會員導覽。`credentialsChanged` 避免較慢的初始 me 回應把成功後的連結改回舊狀態。這是 E-04／E-07 導覽修正；不是按返回連結就登出。

更改頁先等待 me 確認：期間停用送出並隱藏返回，成功讀取正常身分後才恢復；401 導登入、其他讀取錯誤維持停用並提示重新載入。唯讀模擬導回後台，不能送出更改。成功後維持 completed，避免重送。

忘記密碼一律回相同 200 訊息；有效一般帳號建立雜湊 token，撤銷舊 token。HTTP 送出後才寄 SMTP，帳號存在與否不因 SMTP 延遲而明顯不同。連結 token 放在 URL fragment，前端啟動時立即移除 fragment，只留記憶體；不經 HTTP access log、localStorage 或第三方資產。

寄送失敗會撤銷該 token 並記錄不含秘密的事件；使用者可於限速期後重新申請，沒有自動重寄／持久 outbox。重設／改密碼在交易內更新雜湊並撤銷該帳號全部 session，之後必須重新登入。

Mailpit 是本機攔信器，不對外轉寄。正式 SMTP、寄件人網域與信件送達仍需業主提供後驗證；測試條款不是正式法律文件。正式上線替換條款內容與 TERMS_VERSION，新註冊會保存新版本。

測試方式與實際結果見 PHASE-2-ACCEPTANCE.md。`integration-fixture.php` 只允許 APP_ENV=test／DB_DATABASE=yinghua_test，沒有 HTTP 測試後門。

2026-10-19：管理員帳號登入欄移除原生 maxlength=60，因瀏覽器以 UTF-16 計數；API 仍依 Unicode 字元限制 60 字，與 DB 一致。非 BMP 字元組成的 60 字帳號可完整保存並登入，61 字回 422。密碼與確認政策不變。
