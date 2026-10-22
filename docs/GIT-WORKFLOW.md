# Git 階段分支與功能提交

更新日期：2026-10-22。依使用者指定：一個階段一條分支、完整功能分開提交、自身錯誤併回原功能。階段四交付依同一規則；下方另保留 10 月 19 日先前三階段整理的歷史紀錄。

## 目前歷史

2026-10-22 階段五細節計畫作為 main 的規劃提交，沿用階段四先規劃、後開實作分支的方式。本次只有工程文件；`phase/05-public-content-home` 待使用者授權實作後才由最新 main 建立，不把規劃記成完成實作。

2026-10-22 Compose 整理使用 `maintenance/compose-cleanup`，將設定、隔離／啟動驗證與閱讀說明作為一筆完整維護提交；驗證及備份後以 merge commit 回 main，保留分支。沒有改寫 PHP 安全更新或既有階段歷史。

2026-10-21–22 PHP 安全維護使用 `maintenance/php-8.5.12-security`，由最新 main 分出；建置、驗證及環境文件合成一筆安全更新，驗證後以 merge commit 回 main 並保留維護分支。這是新發布漏洞的維護工作，沒有改寫既有階段提交或建立階段五分支。

階段四驗收交付後，`main` 保留原始交付、階段四規劃及四個階段合併點；本機保留 `main` 和四條階段分支。未開始的階段五以後不提前建立分支。

```text
原始交付
  ├─ phase/01-project-setup：規劃、skills、品質工具、Docker 與環境驗證
  └─ merge 階段一
       ├─ phase/02-auth-permissions：認證、密碼、管理員、權限與唯讀模擬
       └─ merge 階段二
            ├─ phase/03-attractions-media：優先級規劃、景點、媒體、地圖與驗收
            └─ merge 階段三
                 ├─ 階段四規劃（實際規劃提交，保留）
                 ├─ phase/04-films-screenings：資料層、公開電影、管理／海報、多場次及驗收
                 └─ merge 階段四 → main
```

| 階段 | 分支 | 提交與實際範圍 |
| --- | --- | --- |
| 1：共用準備 | `phase/01-project-setup` | 7 筆規劃／安裝／環境提交；保持不變 |
| 2：認證與權限 | `phase/02-auth-permissions` | 18 筆前置、完整功能、驗證與全站導讀；保持不變 |
| 3：景點與圖片 | `phase/03-attractions-media` | 12 筆規劃、功能、驗證與最終文件；已完成本機驗收，P0 尚未完成 |
| 4：電影與場次 | `phase/04-films-screenings` | 5 筆資料層準備、公開電影、電影／海報管理、多場次、整合驗收與導讀；已完成本機驗收，P0 尚未完成 |

原始第一提交 `56733e5c566009b02fb8d23c8949eec0c61f80c6` 固定不改。`website/` 與原始 Excel 的 69 檔完整保留。

階段三依序為：

1. P0／P1／P2 優先級與階段重排；這是規劃，不算新增功能。
2. 景點與媒體的 schema／DTO／Repository 共用準備。
3. 公開景點 API、版本化內容與 HTML 發布。
4. 景點與圖片管理、範例試填、電話及權限流程。
5. Google Maps 安全解析與候選匯入。
6. 原始重啟持久化驗證工具與初次交付說明。
7. 手動／Google 共用結構化營業時間。
8. Google 候選欄位與原文參考簡介。
9. Google 地點關聯與位置變更確認。
10. 景點摘要與介紹選填；自動補位仍延後。
11. 獨立 OpenAPI 驗證工具及對應規格／複查文件。
12. 業主要求對照、最終驗收與全站導讀。

後續追加功能各自保留完整功能提交；重複複查、基準校正及自身錯誤不再各開一條文件分支／合併節點。報告檔案保留原本內容與日期，收在相應功能及驗收交付中。

階段四五筆依序為：

1. `build(films): prepare typed film and screening persistence`：三表、DTO／Repository、型別及資料層檢查。
2. `feat(films-public): publish weekly listings and film detail pages`：公開 API、HTML／SEO、舊網址、分頁及受控 metadata seed。
3. `feat(films-admin): manage films and poster media`：六個管理 API、電影資訊／海報表單與媒體分權。
4. `feat(film-screenings): edit screening starts without duplicates`：動態場次、U-16、整組交易、衝突／並行與票務。
5. `test(phase-04): accept films and document delivered architecture`：跨模組完整回歸、空庫／保留資料升級、容器重建持久化、桌面／手機及導讀。

開發期間的日期邊界、公開換頁／安全編碼、海報預覽與僅新增權限修正併回相應功能，不留下 fixup 或額外進度提交。階段四具體結果見 [驗收](PHASE-4-ACCEPTANCE.md)。

## 後續階段

| 階段 | 優先級 | 預定分支 | 狀態 |
| --- | --- | --- | --- |
| 5：公開消息／優惠與首頁 | P0 | `phase/05-public-content-home` | 已授權，5A–5D 資料層／消息／優惠／店家／首頁已接入，整合驗收進行中 |
| 6：收藏與基本行程保存 | P0 | `phase/06-favorites-basic-itinerary` | 未開始 |
| 7：SEO 設定與 HTML head | P0 | `phase/07-seo-settings-head` | 未開始 |
| 8：P0 驗收與部署基線 | P0 界線 | `phase/08-p0-validation` | 未開始 |
| 9：消息／優惠管理與前後篇 | P1 | `phase/09-content-management-details` | 未開始 |
| 10：推薦路線 | P1 | `phase/10-recommended-routes` | 未開始 |
| 11：完整會員旅程 | P1 | `phase/11-member-itineraries` | 未開始 |
| 12：結構化資料／索引與驗收 | P1 界線 | `phase/12-seo-indexing-p1-validation` | 未開始 |
| 13：全站搜尋 | P2 | `phase/13-site-search` | 未開始 |
| 14：一般設定與營運後台 | P2 | `phase/14-settings-operations` | 未開始 |
| 15：全站驗收與交付 | P2／交付界線 | `phase/15-delivery-validation` | 未開始 |

階段分配依 [IMPLEMENTATION-PLAN.md](IMPLEMENTATION-PLAN.md)。完成 P0 界線驗收才進入 P1，完成 P1 才進入 P2；未開始的分支不提前建立。

## 提交流程

- 每階段從當時最新 `main` 開始；階段分支保留，驗收完成以 `--no-ff` 合併。
- 每筆功能含必要後端、前端、API 契約、測試及中文指南；共用前置環境另作明確準備提交。不要只按目錄／副檔名拆分，也不要把整階段擠成一筆。
- 原型公開頁的提交明示保留原型，不冒充後續模組的正式 API。
- 真正新增的需求另作完整功能提交；代理自身失誤歸回原功能。階段內的文件、規格對照及複查隨功能／驗收交付，不另造進度分支。
- 尚未分享的本機階段歷史，可在備份與驗證後收整為一個完整階段合併點。已分享的歷史不自行覆寫遠端。
- 完成階段前更新 [PROJECT-GUIDE.md](PROJECT-GUIDE.md)，對齊實際頁面、API、資料表、環境、檔案用途與驗收狀態。
- 合併只表示紀錄中實際通過的範圍，不代表正式 SMTP、HTTPS 或其他正式環境待驗項已完成。

## 2026-10-19 先前三階段收整與備份

當時原本 16 個 merge commit 收為三個階段合併點；14 條本機分支收為四條。階段一、二與原始交付提交保持不變。優先級規劃保留為階段三分支的第一筆規劃提交；其餘功能依相依順序排列，沒有把後續階段列為完成。

整理前以 `artifacts/history-before-stage-compaction-20261019.bundle` 備份全部 refs 並驗證；舊的文件分支及每輪提交均可由備份復原。已合併的十條 `docs/` 分支不留在目前分支圖。

各功能與測試工具的提交快照，均與先前交付的整棵 Git tree 完全相同；重啟及獨立契約驗證保留各自的測試工具提交。最終只有本 Git 流程文件及階段三驗收文件的歷史說明更新，應用程式、測試、契約、資料庫與環境內容不變。本次 Git 驗證不冒稱重新執行功能測試；歷次實際驗收結果保留於原報告。

原始 69 檔、最終應用程式內容、階段分支、merge 父子關係及備份完整性須核對；不修改 `.env`、資料庫、遠端 refs，也不推送遠端。

查看階段主線：`git log --first-parent --oneline main`。查看某階段的功能：`git log --oneline phase/02-auth-permissions..phase/03-attractions-media`。

## 階段四歷史保護

開發中已建立並驗證 `artifacts/phase4-before-history-20261019.bundle`。最終 autosquash 前另保存全部 refs 的 `artifacts/phase4-before-autosquash-20261020.bundle`；合併前再保存 `artifacts/phase4-before-merge-20261020.bundle`。均為本機忽略檔，不提交大型備份或任何憑證。

只收整從 `74b881f8148c7e42c0d0a9738dcee77f59f6b312` 起的本機階段四提交，不改階段一至三、第一提交或遠端 refs。比較 autosquash 前後整棵 tree，再以 `--no-ff` 合併並保留 `phase/04-films-screenings`；核對 main／階段分支 tree 相同、原始 69 檔完整及工作目錄乾淨。系統的 `refs/codex/` 復原紀錄不當成專案進度分支，也不刪除。
