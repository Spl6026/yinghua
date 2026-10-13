# Git 階段分支與功能提交

來源：使用者於 2026-10-14 指定「依某個功能完善做細項 commit」及「每一個階段開一個 branch」。本規則補充 AGENTS.md，不改變功能範圍或驗收標準。

## 分支

`main` 保留原始交付與已驗收階段的合併紀錄。每階段從當時最新的 main 建立分支；驗收後使用 `git merge --no-ff` 合併，保留階段分支供查閱。未開始的階段等開始時才建立，避免沿用過時基底。

| 階段 | 分支 | 狀態 |
| --- | --- | --- |
| 1：需求與環境 | `phase/01-project-setup` | 已有成果，重整為細項提交並合併 |
| 2：認證與權限 | `phase/02-auth-permissions` | 已有成果，重整為細項提交並合併 |
| 3：景點與圖片 | `phase/03-attractions-media` | 開始實作時建立 |
| 4：電影與場次 | `phase/04-films-screenings` | 開始實作時建立 |
| 5：消息、優惠與路線 | `phase/05-news-offers-routes` | 開始實作時建立 |
| 6：收藏與旅程 | `phase/06-favorites-itineraries` | 開始實作時建立 |
| 7：搜尋、設定與 SEO | `phase/07-search-settings-seo` | 開始實作時建立 |
| 8：整站驗收與交付 | `phase/08-delivery-validation` | 開始實作時建立 |

例如進入階段三時：

```powershell
git switch main
git switch -c phase/03-attractions-media
# 完成一個功能，執行相應驗證後提交；重複至階段驗收完成。
git switch main
git merge --no-ff phase/03-attractions-media
```

合併訊息要寫出實際成果與驗證範圍；合併不代表正式 SMTP、部署或客戶條款等外部條件已通過。分支與提交保持本機，未另獲授權不推送或覆寫遠端。

## 提交單位

- 一筆對應一個能說明目的、結果及驗證的功能；例如管理員新增／編輯／停用的完整流程，或個人權限矩陣。不以「所有 PHP」「所有 HTML」分開，避免後端完成卻缺少介面／契約。
- 功能使用的 Controller／Service／Repository、前端、OpenAPI、相應回歸測試與中文閱讀指南一起納入；共用 schema、初始化 CLI、HTTP 管線或模板可先作清楚命名的前置提交。
- 跨模組整合／故障驗收可另作 `test:` 提交，註明實際範圍；不能把新增測試稱為新業務功能。
- 原型公開頁按首頁／景點／映演／消息／優惠／旅程示意分組，提交訊息明示保留原型；不冒充階段三以後的正式內容 API。
- 一套第三方 skill 的原始安裝檔保持完整，以一次安裝作提交單位，不拆散它的參考文件來壓低檔案數。
- 自己實作失誤的更正併回原功能提交，不額外留下「修正代理錯誤」的進度提交。新功能仍應另作功能提交。

例如 `feat: manage admin accounts with version checks` 應包含管理 API、列表／表單、版本衝突與 root 保護、契約及對應測試；下一筆 `feat: edit per-admin permission matrices` 再加入矩陣讀寫與介面。

## 既有歷史重整

原始交付第一提交 `56733e5c566009b02fb8d23c8949eec0c61f80c6` 固定不改。此次將既有階段一／二成果按功能重新分組，並保留兩個階段的分支與合併節點；這是歷史整理，不表示新增業務功能或重新執行所有既有驗收。

階段一分為七筆：規劃、PHP skill、安全 skill、查詢／介面 skills、品質工具、Docker 環境及環境驗收。階段二分為十七筆：schema、固定管理員初始化、HTTP 共用規則、首頁與公開原型各模組、認證、密碼流程、其餘後台存取門檻、管理員 CRUD、個人權限矩陣、跨模組驗收、唯讀模擬及本次 Git 工作流程。

拆分副本已在 Docker 驗證各版本 PHP 語法與 PHPStan level 10；有 HTTP 入口的版本，其已註冊路由與該版 OpenAPI 一致。各前端版本建置、imports 及當時已納入的 Node 測試通過；當次終點是 64 頁與 16 項測試，密碼／認證導覽修正加入十項至 26 項，再次複查加入四項至 30 項；2026-10-17 驗收新增首登登出與重載錯誤三項，目前共 33 項，見 PHASE-2-ACCEPTANCE.md。拆分檢查沒有重新計入先前 88／58 項 HTTP 整合驗收；2026-10-17 本輪則實際重跑 88／58 項並通過。路由組裝只連接測試庫，不執行 handler 或寫入資料。

先將所有 refs 備份為本機忽略的 `artifacts/history-before-feature-split-20261014.bundle` 並驗證，再建立新歷史。更新 main 前比對各階段最終 tree、全部原始交付檔案與新分支依賴；更新後確認工作樹、原始提交及最終應用檔案完整保留。未修改 `.env`、資料庫或已驗證的應用程式。只新增本次 Git 工作流程文件／規則。

2026-10-17 PHP 分層改善依認證、管理員帳號、個人矩陣、整合驗證與唯讀模擬歸回對應功能提交，階段二維持十七筆。重整前備份為 `artifacts/history-before-layering-20261017.bundle` 並驗證；七個相依改變的 PHP 快照通過語法、PHPStan level 10 與適用單元測試，並以反例確認新增的 Service／PDO 相依限制生效。最終內容、原始交付、階段分支與 merge 邊界均有檢查；本輪實際 HTTP／MariaDB 驗證見 PHASE-2-ACCEPTANCE.md，沒有把內部改善寫成後續階段進度。
