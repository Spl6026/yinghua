# Git 階段分支與功能提交

更新日期：2026-10-19。依使用者指定：一個階段一條分支、完整功能分開提交、自身錯誤併回原功能。此次整理實際收整提交與分支拓樸，沒有新增業務功能。

## 目前歷史

`main` 保留原始交付與三個階段合併點；本機只保留 `main` 和三條階段分支。

```text
原始交付
  ├─ phase/01-project-setup：規劃、skills、品質工具、Docker 與環境驗證
  └─ merge 階段一
       ├─ phase/02-auth-permissions：認證、密碼、管理員、權限與唯讀模擬
       └─ merge 階段二
            ├─ phase/03-attractions-media：優先級規劃、景點、媒體、地圖與驗收
            └─ merge 階段三 → main
```

| 階段 | 分支 | 提交與實際範圍 |
| --- | --- | --- |
| 1：共用準備 | `phase/01-project-setup` | 7 筆規劃／安裝／環境提交；保持不變 |
| 2：認證與權限 | `phase/02-auth-permissions` | 18 筆前置、完整功能、驗證與全站導讀；保持不變 |
| 3：景點與圖片 | `phase/03-attractions-media` | 12 筆規劃、功能、驗證與最終文件；已完成本機驗收，P0 尚未完成 |

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

## 後續階段

| 階段 | 優先級 | 預定分支 | 狀態 |
| --- | --- | --- | --- |
| 4：電影與場次 | P0 | `phase/04-films-screenings` | 未開始 |
| 5：公開消息／優惠與首頁 | P0 | `phase/05-public-content-home` | 未開始 |
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

## 本次收整與備份

原本 16 個 merge commit 收為三個階段合併點；14 條本機分支收為四條。階段一、二與原始交付提交保持不變。優先級規劃保留為階段三分支的第一筆規劃提交；其餘功能依相依順序排列，沒有把後續階段列為完成。

整理前以 `artifacts/history-before-stage-compaction-20261019.bundle` 備份全部 refs 並驗證；舊的文件分支及每輪提交均可由備份復原。已合併的十條 `docs/` 分支不留在目前分支圖。

各功能與測試工具的提交快照，均與先前交付的整棵 Git tree 完全相同；重啟及獨立契約驗證保留各自的測試工具提交。最終只有本 Git 流程文件及階段三驗收文件的歷史說明更新，應用程式、測試、契約、資料庫與環境內容不變。本次 Git 驗證不冒稱重新執行功能測試；歷次實際驗收結果保留於原報告。

原始 69 檔、最終應用程式內容、階段分支、merge 父子關係及備份完整性須核對；不修改 `.env`、資料庫、遠端 refs，也不推送遠端。

查看階段主線：`git log --first-parent --oneline main`。查看某階段的功能：`git log --oneline phase/02-auth-permissions..phase/03-attractions-media`。
