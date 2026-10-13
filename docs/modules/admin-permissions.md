# 管理員管理程式閱讀

S-026 管理員管理；E-09 軟刪除、版本衝突；一般登入 account 可修改（U-12）；E-10 防越級。

列表與表單 → admin-api.js → AdminController → AdminWriteInput → AdminManagement → AccountRepository。Service 在同一交易重新驗證操作者、比較版本與追加稽核；root 保護同時由 Policy 與 DB 約束執行。角色起始值來自原規格矩陣；逐人矩陣介面於下一個功能提交加入。

admin-form.js 使用 updateControls 與 loadVersion 控制讀取、重試、保存和草稿確認；admin-state.test.mjs 驗證相應失敗與延遲回應。

`admin-form.js` 在成功重新載入資料後呼叫共用 `formErrors(form, { fieldErrors: {} })`，清除舊草稿的欄位錯誤及 `aria-invalid`／`aria-describedby`（E-14）。例如重複 Email 保存失敗，再確認重新載入時，正確的 Email 不會繼續被標成無效；讀取失敗仍保留錯誤與重試入口。

[E-01] AdminManagement 透過共用 RoleTemplates 取得起始矩陣；PermissionController 提供角色清單。API 使用 AccountSummary／AdminPage，不直接傳出內部 Account。AccountRepository 將 duplicate key 轉成 AccountConflict，交易先回滾再由 Service 對應欄位衝突。

[U-13] 一般帳號依實際授權可自我停用、軟刪除或降權，新帳號無首登強制改密碼；固定 root 與最後管理權仍受保護。005／007 分別校正字元集及舊旗標；Unicode 帳號唯一性保留。

E-23：008 migration 對固定身分採 BINARY 比較，不讓大小寫／尾端空白變更繞過 trigger。root 編輯頁替換表單時先保留按鈕參照，避免 finally 存取已移除 DOM；對應測試確實移除節點。一般帳號輸入不使用 UTF-16 maxlength 截斷，API 仍限制 60 個 Unicode 字元。
