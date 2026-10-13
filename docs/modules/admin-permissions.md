# 管理員與權限程式閱讀

`S-026` 是管理員管理、`S-027` 是逐人矩陣，`S-SEC-14` 是授權控制。軟刪除／版本衝突是 E-09、防越級策略是 E-10、額外摘要 API 是 E-13；來源與原型網址逐項在 PHASE-2-PLAN.md／DESIGN-TRACEABILITY.md。

清單、雙欄表單與矩陣沿用原始 website 的後台版型，新增欄位沿用 admin-field；來源與前端閱讀見 [frontend.md](frontend.md)。

`admin-form.js` 在成功重新載入資料後呼叫共用 `formErrors(form, { fieldErrors: {} })`，清除舊草稿的欄位錯誤及 `aria-invalid`／`aria-describedby`（E-14）。例如重複 Email 保存失敗，再確認重新載入時，正確的 Email 不會繼續被標成無效；讀取失敗仍保留錯誤與重試入口。

## 新增管理員的完整資料流

1. `frontend/pages/admin/admin-add.html` 提供欄位；`assets/js/admin.js` 先取得 me，驗登入／admins.create。
2. `pages/admin-form.js` 共用新增與編輯，透過 `api/admin-api.js` 發 JSON。身份與權限不來自頁面、localStorage 或使用者傳的角色。
3. 路由標記 admins.create；Kernel 驗登入、CSRF 與權限。
4. `AdminController.create` 用 AdminWriteInput 明確校驗允許欄位，禁止 isRoot、password_hash 與任意額外欄位。
5. `AdminManagement.create` 透過 `RoleTemplates.forAssignment` 取得角色起始矩陣、雜湊初始密碼；在交易內鎖定並重新驗證操作者，PermissionPolicy.grantable 拒絕越級。
6. AccountRepository 建一般管理員；PermissionRepository 複製完整 40 個個人權限；EventRepository 同交易追加稽核。
7. Service 回 `AccountSummary`，Controller 明確轉為原有 JSON 格式並回 201；列表使用 `AdminPage`。依 U-13 不強制首登改密碼；既有 mustChangePassword 回應固定 false，保留相容。

## 角色範本與有效權限

`backend/resources/role-templates.json` 是依原始 Excel「權限矩陣」四個角色、十個功能、四個動作整理的 160 個起始值。以來源文件為準，例如內容編輯者在文件中有管理員管理能力，不能自行猜測成僅能編輯景點。

角色是起始模板，建立或換角色時複製到個人矩陣；之後某人的逐人異動不回寫模板，也不自動影響其他人。PermissionRepository.effective 對一般帳號讀個人矩陣，缺少值預設拒絕；固定 root 由後端身分取得完整 40 個權限。

PermissionPolicy 是不依賴 PDO 的共用規則：操作者須為有效管理員、module/action 精確配對、不能寫 root、非 root 不能授出自己沒有的值。U-13 明確允許本來具權限的一般管理員自我停用、軟刪除或降權；操作後仍立即依新狀態／權限驗證，固定 root 保留最後管理權。

列表、表單與 sidebar 隱藏未授權入口是 UI 行為；真正資料讀取／修改仍由 PHP 驗權。直接呼叫 API、偽造 id、只靠會員 Cookie 都不能繞過後台認證。

## 編輯、版本、交易與稽核

編輯帶 `version`，矩陣保存帶 `permissionsVersion`（API 欄位為 version）。Service 鎖定帳號後比較當前值；相同版本的兩個修改只有一個成功，另一個回 409，不覆蓋新資料。前端保留輸入並提供重新載入。

帳號編輯／軟刪除／個人矩陣與 audit 同交易；稽核失敗則整筆回滾。停用／刪除同時撤銷 sessions 與重設 tokens。共用 PdoTransactions 最多重試三次 SQLSTATE 40001 死鎖，交易內沒有 SMTP 等外部副作用。

稽核保留 UUID、動作、角色／狀態／版本與 before/after 權限；帳號、Email、名稱採用途分離 HMAC，不保存明文個資、密碼／雜湊、Cookie、答案或重設 token。DB trigger 禁止稽核 UPDATE／DELETE；本階段不提供稽核查詢 API。有 DDL 權限者（包含本機初始化用 spl 與 DB root）仍可改變 trigger；正式部署時分離 schema 管理與執行帳號的權限，不能將本機 trigger 當成對特權 DB 帳號的完整防護。

一般管理員 account 可修改，依原型與 U-12 移除建立後固定政策。005 將字元集改 utf8mb4_unicode_ci、保留唯一性，僅固定 root 仍不可改名。權限選單的最小摘要加入 account，沿用原版「名稱（帳號）」以區分同名對象，仍不回傳 Email。

表單狀態集中在 `updateControls`：只有成功取得有效版本才能保存，載入／保存／唯讀時停用欄位；`loadVersion` 拒絕較舊回應。重新載入先確認是否放棄草稿。權限保存先記住對象、版本與讀取序號，途中換人時不拿前一人的回應覆蓋新矩陣；寫入成功但重讀失敗會明示並停止再次保存舊版本。共用 `submitForm` 的 `canSubmit` 在 finally／限速等待結束後重新判斷，`onError` 統一處理登入逾時。

固定 root 的帳號、系統旗標、Email／名稱／角色／啟用狀態等受 CHECK 與 trigger 保護；本人密碼及正常登入時間更新允許。角色模板初始化只補缺資料、不覆蓋既有值。

## 如何確認分層

`backend/tests/ArchitectureTest.php` 以 PHPat 檢查四條相依規則：Controller 不查 PDO／Repository，Service 不依賴 HTTP，Repository 不回呼 Service／Controller，Model／DTO／Policy 不依賴資料庫或 HTTP。`Composition.php` 明確組裝各層，沒有通用 BaseService、Singleton 或全域服務容器。

PHPStan level 10 檢查型別邊界，PHPUnit 測純規則，HTTP 整合測試用真實 MariaDB 驗版本衝突、停權、越級、root 約束與稽核失敗回滾。逐項結果見 PHASE-2-ACCEPTANCE.md。

E-23：008 migration 對固定身分採 BINARY 比較，不讓大小寫／尾端空白變更繞過 trigger。root 編輯頁替換表單時先保留按鈕參照，避免 finally 存取已移除 DOM；對應測試確實移除節點。一般帳號輸入不使用 UTF-16 maxlength 截斷，API 仍限制 60 個 Unicode 字元。
