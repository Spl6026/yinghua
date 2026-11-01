# Git 階段分支與功能提交

2026-11-01 最新：階段十四新版Browser補驗18筆與會員摘要鍵盤／行程保存重載通過，本機範圍確認；冷首幀CLS與正式服務不冒稱通過。詳見[階段十四最新結論](PHASE-14-ACCEPTANCE.md#最新補驗結論)。使用者已授權規劃並實作階段十五，依合併與獨立分支規則處理。下方帶日期記錄保留歷史。

2026-11-01 階段十四進行中：使用者已授權會員查詢、一般設定與營運後台，並追加U-22的排版／載入穩定及原頁行程操作改善。110 PHP API＋3爬蟲GET＝113 HTTP契約，68頁／34表338欄／20 migration；無DDL／env／runtime依賴新增。73項真DB／27契約、政策5項、252 JS／116 PHP（381斷言）、PHPStan／PHPat與P1 181契約已通過。新增本機字型資產。U-22最後畫面／鍵盤／冷載入仍待內建Browser恢復後實測；先前9＋9筆版型證據只代表追加改善前，不能冒稱最新介面通過。階段尚未完成、未合併main、階段十五未開始。 詳見[計畫](PHASE-14-PLAN.md)與[本輪證據及待驗](PHASE-14-ACCEPTANCE.md)。

2026-11-01 最新：階段十三依使用者授權完成全站搜尋，一個公開GET、同版MariaDB搜尋投影、五類分組／篩選／分頁、原型搜尋頁、錯誤／重試／返回。105 PHP API＋3爬蟲GET＝108 HTTP契約，68固定頁／34表338欄／20 migration；無新env／依賴／服務。230 JS／112 PHP（370斷言）、113真整合／28契約、P1 255／181契約與15筆Browser量測通過，測試資料已清理。詳見[計畫](PHASE-13-PLAN.md)、[驗收](PHASE-13-ACCEPTANCE.md)、[逐檔架構指南](modules/site-search.md)。正式差項沿U-21交接，階段十四／十五未開始；下方日期段落保留歷史。

2026-10-31 最新：階段十二依授權完成JSON-LD、同版robots／sitemap／分片及P1本機整合。104個PHP JSON API保持，另3個Nginx爬蟲GET，共107個HTTP契約；68固定HTML／33表327欄／19 migration，無新DDL／依賴／env。224 JS／107 PHP、P1 255項與181契約回應、索引369項、官方Validator七型別0錯誤／警告、Browser21筆及40個body保留通過。U-21指定網域未定先完成本機，正式Search Console／Bing／分享仍交接；P2階段十三未開始。詳見[計畫](PHASE-12-PLAN.md)、[驗收](PHASE-12-ACCEPTANCE.md)及[架構與檔案指南](modules/search-index.md)。下方帶日期段落保留歷史。

階段十一（2026-10-31）依11A共用資料／版本及DTO、11B完整本人編輯／保存與套用交易、11C公開指定路線預覽及原型套用入口、11D整合驗收拆分。每筆配對契約、測試及指南；資料層準備明列不冒充HTTP成果。自身排版／格式修正歸入功能，備份`artifacts/phase11/before-functional-commits.bundle`與合併前`before-merge.bundle`，比較完整tree與原始69檔，再--no-ff回main，保留phase/11-member-itineraries，不推送。

2026-10-31 最新：階段十一已依使用者授權完成本機範圍，包含本人多份行程、整份編輯保存、時間／備註／排序、推薦路線確認套用及P0資料相容。104 API／68固定頁／33表327欄／19 migration，沒有新DDL／環境。81項真實整合、66筆契約回應零錯誤、107 PHP／208 JS、P0收藏69與目前行程100通過；兩頁各1440／390／320實測並與原型對照。詳見[階段十一驗收](PHASE-11-ACCEPTANCE.md)、[計畫](PHASE-11-PLAN.md)及[檔案閱讀指南](modules/member-itineraries.md)。下方帶日期段落保留歷史；階段十二未開始，P1整體尚未簽核。

2026-10-30 Browser補驗：原型導覽及刪除後焦點修正、兩項JS回歸及58項真API測試歸入10B完整路線功能；10C保留獨立契約執行器、跨模組與schema檢查及最終驗收／導讀。收整前備份 `artifacts/phase10/before-browser-fold.bundle`，最終完整tree不變、原始69檔與其他refs保留；驗收後另備份 `before-phase10-merge.bundle` 再以merge commit合併main，保留階段十分支，不推送遠端。

2026-10-30 最終：階段十已補完內建 Browser 本機驗收；四頁各1440／390／320共12筆實測，另12筆原型比對。公開導覽選中及刪除後焦點兩項已修、實測與回歸通過。PHP99／342、前端200、路線58／45契約回應零錯誤；96 API／68固定頁／33表327欄／19migration。全站導讀與檔案用途見[推薦路線指南](modules/recommended-routes.md)，實際範圍與共用AA基線界線見[階段十驗收](PHASE-10-ACCEPTANCE.md)。本機範圍通過，依備份／完整tree核對程序合併並保留階段分支；完整會員套用仍留階段十一。下方帶日期段落保留歷史。


2026-10-30：階段十依使用者授權於 `phase/10-recommended-routes` 實作推薦路線管理與公開列表；96 API／68固定頁／33表327欄／19 migration。自動化結果見 [階段十驗收](PHASE-10-ACCEPTANCE.md)，程式閱讀見 [推薦路線指南](modules/recommended-routes.md)。內建 Browser 連線不可用，實際介面待驗，尚未合併 main；完整會員套用留階段十一。下方帶日期段落保留歷史。


2026-10-30：階段八依本機P0工程界線完成後已以merge commit d674baf合併，前置備份artifacts/phase9-planning/before-phase8-merge.bundle。階段九另分phase/09-news-offers-admin，按共用typed編輯準備、9A消息、9B優惠與共享店家、9C前後篇、9D整合驗收五筆交付。格式／按鈕／代理自身修正歸回相應功能；整理前artifacts/phase9/before-history-fold.bundle及合併前artifacts/phase9/before-merge.bundle、原始69檔與完整tree必須核對，保留階段分支，不推送。實際範圍見[驗收](PHASE-9-ACCEPTANCE.md)。

2026-10-26 AA-01／AA-02 修正依既有自身修正規則歸回 P0 驗證提交；工具準備提交仍保留，main／其他階段／原始 69 檔不變。改寫前備份於 `artifacts/p0-aa-repairs-20261026/history-before-aa-fold.bundle`，核對完整 tree。新提示的主機讀屏未完成，沒有合併／推送或提前宣告整體 AA 通過。

2026-10-26 AA 逐項驗收：欄位邊界／靜止裝飾、英文語言標示、確認框循環及其 159 項測試／閱讀指南與 [準則結果](P0-AA-VERIFICATION-20261026.md) 歸回既有 P0 驗證提交（原 7665b7f）；獨立讀屏工具的最新說明歸回工具提交（原 44c8256），不新增自身修正進度提交。改寫前備份於 `artifacts/p0-aa-full-20261026/history-before-aa-fold.bundle`，核對完整最終 tree、原始交付 69 檔及其他分支／main／origin/main。未合併或推送；尚未通過的兩項 AA 不提前視為 P0 完成。

2026-10-26 登入後補驗：確認框語意修正與對應閱讀說明歸回原 P0 驗證提交（舊 a7ff07f），隔離讀屏結果／工具使用說明歸回原工具提交（舊 451f1de），不另留自身修正進度提交。改寫前備份 `artifacts/isolated-a11y-resume-20261026/history-before-followup.bundle`，保留 parent 343bd7e、原始交付及所有其他階段／main／origin/main，最終 tree 以實際暫存內容逐一核對。下方「a7ff07f 不改寫」是前一工具交付當時的歷史狀態；本輪按使用者既有規則歸回修正，仍未合併或推送。

2026-10-26：依 U-19 建立 E-30 隔離讀屏桌面，是可獨立說明／啟停／採證的新增驗收準備功能，與工具指南及有限讀屏結果同一筆提交，留在 `phase/08-p0-validation`。既有 P0 驗證提交 `a7ff07f` 及其 parent `343bd7e` 不改寫；工具建立過程的配額／健康檢查修正不另列進度。沒有合併或推送 main，原始 69 檔保持，見 [工具指南](modules/isolated-accessibility.md)。

2026-10-26 01:20：400% 的補驗紀錄併回相同階段八驗證提交，改寫前備份 `artifacts/p0-a11y-20261025-211402/zoom-reader/history-before-400-amend.bundle`。本輪無新應用進度，保留 parent 343bd7e、原始交付與 main／origin/main；實際讀屏未完成，不提前合併或標 P1 已開始。

2026-10-25 22:02：實際 200% 與 NVDA 授權逾時的補驗紀錄仍併回階段八最新驗證提交；改寫前備份為 `artifacts/p0-a11y-20261025-211402/zoom-reader/history-before-zoom-amend.bundle`。無新應用進度、不改 main／原始交付／343bd7e parent；完整 400%／讀屏狀態保留未完成。

2026-10-25 21:40：本輪補驗的封面重排／圖片遮罩修正及驗收指南併回最新 P0 驗證提交，未另列自身修正進度。改寫前已建立並 verify `artifacts/p0-a11y-20261025-211402/history-before-a11y-amend.bundle`；只 amend 該階段分支尾端，保留 parent `343bd7e` 與原始交付，核對 staged tree、原始 69 檔、main／origin/main 不變。未合併 main 或推送遠端；實際縮放／讀屏未完成的狀態繼續保留。

2026-10-24 階段八細節計畫作為main的純工程文件提交，見 [PHASE-8-PLAN.md](PHASE-8-PLAN.md)。後續取得實作授權後，才從當時最新main建立階段分支；不把規劃當作階段八實作或P0驗收完成。

2026-10-24 階段七依授權從最新 main 建立 phase/07-seo-settings-head，按7A資料層、7B管理設定、7C同版公開head、7D政策頁、7E整合驗收五筆交付。自身修正歸回對應功能；合併前備份歷史與核對完整 tree／原始69檔，--no-ff 合併並保留分支，不推送遠端。階段八尚未建立。

更新日期：2026-10-22。依使用者指定：一個階段一條分支、完整功能分開提交、自身錯誤併回原功能。階段四交付依同一規則；下方另保留 10 月 19 日先前三階段整理的歷史紀錄。

## 目前歷史

2026-10-23 階段六細節規劃作為 main 的純工程文件提交；其後依使用者授權從最新 main 建立 `phase/06-favorites-basic-itinerary`。已按6A–6D完成，2026-10-24補完實際瀏覽器驗收後合併 main；自己的修正併回對應功能，原始檔及防毒隔離差異不納入變動。

2026-10-22 階段五細節計畫作為 main 的規劃提交，沿用階段四先規劃、後開實作分支的方式。該規劃提交當時只有工程文件；其後依使用者授權從最新 main 建立 `phase/05-public-content-home`，完成以下五筆功能與驗收。

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
| 5：公開消息／優惠與首頁 | P0 | `phase/05-public-content-home` | 已完成本機驗收；五筆功能／驗收提交，P0 尚未完成 |
| 6：收藏與基本行程保存 | P0 | `phase/06-favorites-basic-itinerary` | 6A–6D 完成本機驗收；合併前備份並核對 tree，保留階段分支 |
| 7：SEO 設定與 HTML head | P0 | `phase/07-seo-settings-head` | 7A–7E 已完成本機範圍；五筆功能／驗收提交，正式差項另列 |
| 8：P0 驗收與部署基線 | P0 界線 | `phase/08-p0-validation` | 已依授權實作；本機演練通過範圍見階段八紀錄，完整AA／正式差項待驗，尚未合併 |
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

## 階段五歷史保護

2026-10-22：以 `phase/05-public-content-home` 交付五筆：資料層／店家相容準備、公開消息與 CLI、公開優惠／店家與原型匯入、首頁同版整合、跨模組驗收與導讀。日期排版與測試來源的自身修正併回原功能，不保留 fixup 進度。

合併前保留並驗證本機忽略檔 `artifacts/phase5-before-history-20261022.bundle`，只收整本階段未分享的提交，不改 main 的既有歷史、第一提交或其他階段分支。收整前後完整 tree 必須相同；另備份 `artifacts/phase5-before-merge-20261022.bundle`，以 --no-ff 合併，保留階段分支並比較 main／階段 tree。

防毒隔離的 security-audit 參考檔刪除不納入功能，亦不為收整還原該檔；保留這項已說明的工作樹差異。原型 69 檔、migration 舊檔及 .env 忽略狀態仍須核對。沒有推送或修改遠端 refs。

## 2026-10-23：階段一至五複驗的自身修正

複驗不建立額外進度分支／合併點。手機頂列顯示歸回階段二後台存取、選填內容顯示及產物測試歸回階段三選填內容、電影按鈕歸回階段四多場次介面，閱讀指南隨所屬功能；跨階段實測報告及當前狀態校正併入階段五驗收。既有階段合併父子關係與功能筆數保持。

本機修改 refs 前以 `artifacts/review-phases1-5-before-history-20261023.bundle` 備份並驗證；另保留前一天的同名 20261022 備份。重建受影響的本機提交及下游父節點，先比對最終完整 tree，再一次更新 refs，不改遠端。第一提交、階段一、原型 69 檔、既有 migration、`.env` 忽略及防毒隔離差異均保持；沒有額外的修錯進度提交。

同日固定清單複驗的選檔重載修正、行為回歸與閱讀指南歸回階段三景點管理功能；完整性工具、固定清單與本輪結果歸回階段五驗收。更新前另備份並驗證 `artifacts/verification-closure-20261023-before-history.bundle`，不覆寫較早備份；維持原有功能筆數、分支／合併關係與遠端 refs，核對完整 tree 及原始 69 檔。

## 階段六歷史保護

2026-10-24：四筆依序交付共用資料層、收藏、目前行程及跨模組驗收。瀏覽器驗收發現的可及性、長文字、美食共用入口與登入意圖修正併入所屬功能，閱讀指南隨功能提交，沒有另留修錯進度提交。驗收及最新狀態集中於第四筆。

收整前備份並驗證 `artifacts/phase6-before-browser-fold-20261024.bundle`，只重建本階段未分享提交，不改較早階段、第一提交或遠端 refs。完整最終 tree 核對後，再備份 `artifacts/phase6-before-merge-20261024.bundle` 並以 --no-ff 合併，保留階段分支。防毒隔離檔保持原工作樹差異，不還原或納入提交；原始 69 檔、migration 舊檔及 .env 忽略狀態須再次核對，不推送遠端。

## 階段八歷史與發布來源

2026-10-25：最新完整驗收發現的三類前端自身差項及其回歸／閱讀指南，歸入本輪完整驗收提交，不另留修錯進度。修改前以 `artifacts/p0-ui-fixes-20261025-195151/history-before-acceptance-amend.bundle` 備份並 verify；只修整最後一筆尚未封入映像的驗收提交，保留其父 `343bd7e` 與更早 `262201d`，兩者均是已驗映像來源。原始第一提交、main／遠端、舊 migration 及防毒隔離差異保持，未 merge main。

2026-10-24：8A CSRF診斷、8B health與連線故障、8C發布版／TLS、8D復原、手機選單焦點及整合驗收按可獨立驗證功能提交。自身修正已折回所屬功能，修改歷史前保留並驗證 artifacts/phase8-before-fold-20261024.bundle、phase8-before-security-fold-20261024.bundle、phase8-before-availability-fold-20261024.bundle。

r5／r6與最後還原target映像的來源為262201dd3fea36cbb483fd892e95d5610b99b88d，該提交保留為本分支祖先，不再改寫而讓已驗映像失去來源。後續只整合未封入映像的驗證工具／文件。保留原始第一提交、main／遠端refs及防毒隔離差異；未宣告全階段驗收，尚未merge main。完整驗收後才再次備份、核對tree並 --no-ff 合併，保留階段分支。

2026-10-30 使用者再次複驗後，店家分頁狀態與3項行為測試歸回9B，30筆Browser及API／190項前端與環境恢復紀錄歸回9D；保留原五筆功能界線與單一階段九merge。改寫前備份artifacts/phase9/before-recheck-fold-20261030.bundle，核對原始69檔、最終tree及其他分支；不推送遠端。

## 階段十三交付

13A先交付同版公開搜尋投影準備；13B交付完整搜尋API與原型頁、契約／測試／中文指南；13C收錄本機整合、P1回歸、清理與全站導讀。自身錯誤併回所屬功能，不多留修錯進度提交。合併前備份verify、核對tree與原始69檔，保留階段分支與單一merge，不推送。原有防毒隔離參考檔差異不納入。
