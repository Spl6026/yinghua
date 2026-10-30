# 階段十：推薦路線管理與公開列表

2026-10-30 使用者授權實作。分支 `phase/10-recommended-routes`，從當時最新 main 開始。此文件同時保留設計範圍與待驗界線；實際結果見 [驗收](PHASE-10-ACCEPTANCE.md)。

## 來源與欄位

| 來源 | 要求與本次處理 |
| --- | --- |
| 功能規格 E14/F14/G14/L14 | 公開主題、說明、時間與有序景點；至少一個有效景點，不帶入下架景點；路線順序前後台一致。 |
| 功能規格 E24/F24/G24 | 名稱、主題、天數／時間、說明、發布狀態、選站及排序；至少一站，每站存在且可用，順序不重複。 |
| 功能規格 L24 | 保存後列表與一鍵套用資料同步；完整會員套用依實作計畫留階段十一，不能標此整列驗收完成。 |
| 原始 website/admin/route-add.html、route-edit.html | 名稱必填；主題、介紹選填。沿用獨立新增／編輯頁、取消及列表返回動線。 |
| 資料表建議 C17:D18 | 參考 slug、summary、duration_minutes、版本、時間戳、軟刪除、有序關聯、建議時間與備註；這些是資料表建議，不是新增業務必填。 |
| API草案 E14:G14/E33:G33 | 公開主題／分頁及管理 CRUD；公開跳過下架景點。 |
| 權限矩陣 A25:F28、頁面清單 C36:G38 | routes 的四種動作、獨立 HTML、後端拒絕未授權；刪除使用確認框。 |

E-32：時間統一保存選填正整數分鐘，介面明示單位，天數可換算；SQL上限與100站限制是儲存／資源邊界。主題255、介紹20000、備註2000字亦是工程容量，不稱為 Excel 限制。路線排序為非負整數，越小越前，相同依穩定 ID。站點次序由陣列位置產生，不允許外部輸入重複 sortOrder。UUID 作穩定 ID 與內部 slug，不新增原型不存在的路線詳情頁。

## 架構及交付

10A 共用準備：擴充既有 routes／route_attractions、018 migration、typed DTO、Repository、資料完整性與中文指南。沒有新建表，舊 migration 不修改。

10B 完整路線功能：Controller → RecommendedRoutes → Repository，建構子注入；共用 Authorization、Transactions、ContentPublication、EventRepository。管理表單與列表、OpenAPI、前端行為測試同一功能提交。公開讀取 PublicRecommendedRoutes → 成功快照；站點過濾是純 Policy，前端 route-view 同時用於 HTML 建置。Nginx routes.html 走既有發布版本檢查。

10C 整合驗證與導讀：真實 MariaDB／HTTP、獨立 JSON Schema、PHPStan10／PHPat／PER-CS／Rector dry-run、JS回歸、原始69檔、全站建置及 Browser 桌面／手機。Browser 不可用就不標通過、不合併 main。自身修正合併進對應功能，不另造進度提交。

## API 與頁面

| 操作 | 用途 |
| --- | --- |
| GET /api/v1/routes | 公開路線及有序可用景點；theme、page、pageSize；只讀成功快照。 |
| GET /api/v1/admin/routes | 搜尋、狀態及分頁；routes.view。 |
| POST /api/v1/admin/routes | 新增；routes.create。 |
| GET /api/v1/admin/routes/attractions | 候選已發布景點；routes 任一 view/create/edit，不需要額外景點管理權。 |
| GET /api/v1/admin/routes/{id} | 編輯載入；routes.view 或 edit。 |
| PUT /api/v1/admin/routes/{id} | 整份更新及版本檢查；routes.edit。 |
| DELETE /api/v1/admin/routes/{id} | 版本檢查、軟刪主檔、解除站點引用；routes.delete。 |

頁面是既有 `admin/routes.html`、`admin/route-add.html`、`admin/route-edit.html`、`routes.html`，固定頁數不增加。管理排序以按鈕支持鍵盤，不依賴拖曳；建議時間／備註跟著景點移動。公開頁保留原型 hero／route-cards／route-card，列出真實站點連結；全部卡片直接寫入 HTML，無 JS 仍能閱讀。完整套用未開放的狀態明示，不用示範 localStorage 假裝成功。

## 資料、安全與失敗

儲存重新驗權，鎖定主檔、檢查 version，再依固定景點 ID 順序鎖定與驗證，主檔及整份站點同交易。公開發布共用 exclusive 邊界；並行更新一勝一409。保存完成但 HTML 失敗回202並禁止重複新增；409保留輸入，重新載入需確認。所有寫入有 CSRF、現有 session／唯讀模擬管線與稽核。查詢參數化；名稱、說明、時間及備註輸出轉義。

景點仍被未刪除路線引用時維持既有409保護；刪除路線解除站點引用，路線主檔軟刪保存識別。公開景點下架會重新產生快照，跳過該站；一站都不剩就不公開路線。編輯畫面保留不可用站提示，要求移除／更換，避免靜默改掉編輯資料。
