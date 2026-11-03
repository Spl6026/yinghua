# 系統管理員唯讀模擬：操作與程式閱讀

這是 `U-06` 使用者補充需求：系統管理員可模擬其他管理員，用途為「唯讀驗收：查看介面與權限，不能修改資料」。Excel 沒有要求此功能；`E-16` 是我採用的雙身分、session token 輪替及交易稽核實作。

## 如何操作

固定帳號 `admin` 登入後，左下沿用原版 `.admin-session-switch` 外觀，顯示「模擬登入身分（唯讀）」。選擇啟用中的一般管理員後，目前頁面重新載入，選單、內容存取及 API 依該帳號權限判斷。沒有一般管理員時提示先建立；不提供虛構展示帳號。

畫面上方顯示唯讀模擬對象，左下說明真正登入者。選「本人」或按「退出模擬」可恢復原本的 `admin`；退出入口位於內容區外，即使該帳號無頁面權限也保留。未完成的業務模組仍是待串接畫面，不能因此宣稱完整內容功能已可驗收。

新增／編輯頁可檢查欄位與版型；儲存、刪除及權限修改停用。API 也統一拒絕修改，回 `403 PREVIEW_READ_ONLY`；直接傳 JSON、修改前端 disabled 或打開改密碼網址，都不能代改資料。搜尋、分頁、查看權限矩陣與重新載入仍可使用。

一般管理員即使擁有全部 40 權限，也不能開始、切換或退出其他人的模擬。模擬僅限原本登入 `admin` 的 session，不取得對方密碼、不替對方建立登入 session。U-13 取消首登門檻；新管理員可直接依實際權限驗收，唯讀模擬仍禁止所有業務寫入。

## API／資料表／頁面

| 方法與路徑 | 用途 | 授權／回應 |
| --- | --- | --- |
| GET /api/v1/admin/auth/preview | 啟用中的一般管理員選單 | 真正登入者須為固定 root；僅 id／account／displayName |
| POST /api/v1/admin/auth/preview | 開始或換模擬對象 | root＋CSRF；JSON `{ "adminId": "UUID" }`；輪替登入 Cookie／CSRF |
| DELETE /api/v1/admin/auth/preview | 退出模擬 | root＋CSRF；JSON `{}`；輪替登入 Cookie／CSRF |
| GET /api/v1/admin/auth/me | 顯示有效身分與真正操作者 | 增加 preview.active／readOnly／targetAvailable／targetId／operator |

沒有新增業務表或 HTML 網址。`002_admin_preview.sql` 為 sessions 加 `preview_admin_id`、FK 與管理員 session 限制；`admin_id` 始終是原本登入者。原有 sessions 時限、撤銷、Cookie 政策沿用；切換不重設最長登入期限。HTML 入口為每個後台頁的共用 sidebar／admin.js。

原本 26 個端點加本次 3 個，共 29 個；業務表仍為 13 張。OpenAPI 版本 2.1.0 記錄完整契約。

## 資料流與各層責任

1. `components/admin-preview.js` 取選單與送出切換，使用安全 DOM／textContent，沒有 localStorage 假身分。
2. `AuthController` 驗證輸入白名單與 UUID，呼叫 `AdminPreview`；HTTP Cookie／CSRF 留在 Controller。
3. `AdminPreview` 在交易內驗真正登入者仍為 root、鎖定有效 session／對象，輪替 token 並追加 start／stop 稽核；紀錄失敗則整筆回滾。
4. `SessionRepository` 只處理查詢／持久化；`SessionIdentity` DTO 保存 session id、擁有者 id、驗收對象 id，沒有明文憑證。
5. `Authentication.current` 回 `AuthenticatedIdentity`，區分 owner（真正登入者）與 effective（目前驗收對象）。每次請求重新查帳號狀態，權限由原本 `Authorization`／`PermissionRepository` 取得。
6. `Kernel` 在呼叫 Controller 前統一強制唯讀，連管理員登入、申請重設與重設密碼寫入也拒絕；CSRF／me／模擬控制／登出保留。拒絕事件記真正操作者，避免把 root 的操作誤記在一般帳號。

若驗收對象被停用或軟刪除，me 回空權限與 targetAvailable=false，業務 API 拒絕；不自動回退 root 權限。退出仍可操作。原本 `admin` 密碼重設／復原會撤銷其所有 session，包含模擬中的 session。其他獨立登入 session 不受單次切換影響；同一瀏覽器各分頁共享同一 Cookie，切換後需重新載入以更新畫面，API 以每次請求的有效 session 為準。

## 設計參考與驗證

參考 [Symfony 官方身分模擬](https://symfony.com/doc/current/security/impersonating_user.html) 的受限切換、保留 original user、可辨識模擬狀態與退出機制；本案沿用 PHP API-only，不引入 Symfony。依 [OWASP Session Management](https://cheatsheetseries.owasp.org/cheatsheets/Session_Management_Cheat_Sheet.html) 的權限變更 token 輪替與伺服器端控制原则，再加入使用者指定的唯讀範圍。

`backend/tests/admin_preview.py` 只碰 yinghua_test，以真實 HTTP／MariaDB 驗越權、唯讀、即時權限、停用／刪除退出、session 輪替、並行切換及稽核失敗回滾。驗收結果見 [PHASE-2-ACCEPTANCE.md](../archive/PHASE-2-ACCEPTANCE.md)。
