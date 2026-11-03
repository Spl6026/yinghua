# 規劃與已實作範圍複查

**目前狀態（2026-10-21）：R1–R3 已修正，電影場次介紹亦完成原型對照。下方 10 月 20 日內容保留原始發現，最新實作與驗證見文末。**

檢查日期：2026-10-20。檢查版本：`main`，`1298a42`。範圍為實作計畫、階段二至四、資料庫與 Docker；階段五以後僅檢查規劃。本次產出審查與修正計畫，尚未修改應用程式或既有計畫。下列待修項目應併入後續回歸驗收，不能只引用先前的通過紀錄判定已解決。

基準為原始 [Excel](../../映畫竹塹_後台工程串接功能規格.xlsx)、`website/` 及使用者指示；程式與測試只作實作證據。S／D／U／E 的定義沿用 [設計追溯](../DESIGN-TRACEABILITY.md)。問題嚴重程度使用「高／中／低」，與產品 P0／P1／P2 優先級分開。

## 檢查結果

確認兩項實作缺陷與一組文件落差。整體階段安排及 PHP 分層方向合理；測試通過不能涵蓋下列新重現情境。

| 編號 | 嚴重程度 | 問題 | 狀態 |
| --- | --- | --- | --- |
| R1 | 高 | 發布紀錄寫入失敗時，API 回 500，但景點／電影已保存；重送造成重複新增 | 兩個模組均已在隔離 MariaDB 重現 |
| R2 | 中 | 只有新增權限的帳號缺少可用導覽，保存景點後又被送至無權限清單 | 真實 API 與瀏覽器均已重現 |
| R3 | 低 | 全站導讀仍說電影沒有業務表，Docker 服務表漏列 publisher | 文件與程式／資料庫直接比對確認 |

## R1：已保存資料被回報為失敗

位置：

- `backend/src/Services/ContentPublication.php:38`：讀取投影及 `PublicationRepository::begin()` 在 `try` 外；`:46` 的失敗紀錄寫入也可能再次拋出例外。
- `backend/src/Services/AttractionManagement.php:101`、`FilmManagement.php:93`：內容與稽核交易已提交後，才呼叫發布同步。

重現只使用 `app-test`／`yinghua_test`。在 `content_publications` 建立暫時的 `BEFORE INSERT` 失敗 trigger，保持內容資料表可寫，再送出合法的新增請求。結果：

| 模組 | 第一次回應 | 同名稱有效紀錄 | 同樣內容再送一次 | 同名稱有效紀錄 |
| --- | --- | --- | --- | --- |
| 電影 | 500 `INTERNAL_ERROR` | 1 筆 | 500 `INTERNAL_ERROR` | 2 筆 |
| 景點 | 500 `INTERNAL_ERROR` | 1 筆 | 500 `INTERNAL_ERROR` | 2 筆 |

影響是操作者看到失敗而重試，表單尚未取得新 ID，造成第二筆資料。更新與刪除也共用此發布邊界，需列入修正後驗證；本次重送實證是新增。

來源：S「功能規格」L21 要求保存後前台同步；具體「保存與發布結果分開、失敗可重試、不重複新增」是既定 E 設計，見 [階段四計畫](PHASE-4-PLAN.md) 的交易邊界與發布故障驗收。**HTTP 202 是本專案的契約設計，並非 Excel 指定的狀態碼。**

修正應把「交易已提交」後的投影、建立發布紀錄、渲染、完成紀錄納入完整錯誤處理；保留已保存的 ID／version，依既定契約回傳保存成功但同步失敗的結果。紀錄失敗不可再把業務保存結果變成一般 500；仍須留下不含機密的錯誤日誌，公開版本檢查亦不得被繞過。

驗收需新增真實 MariaDB 的發布紀錄 INSERT／UPDATE 故障案例，並覆蓋景點與電影的新增、更新及刪除。確認重新操作不再次 POST 新資料、同步可重試、故障期間未公開內容不可讀。既有 renderer 失敗案例已通過，但沒有涵蓋這個紀錄層故障。

## R2：獨立新增權限沒有完整操作路徑

來源：S「功能規格」E27 明定逐管理員、逐模組設定 `view/create/edit/delete`。現有後端也允許四項獨立設定；不能以自動增加 `view` 權限解決前端導航。

位置：

- `frontend/assets/js/admin.js:24`：側欄只依 `view` 顯示入口。
- `frontend/assets/js/core/session.js:7`：管理員預設返回總覽；沒有依可用權限選擇目的頁。
- `frontend/assets/js/pages/attraction-form.js:146`：同步成功後一律前往 `/admin/places.html`。
- `frontend/assets/js/pages/admin-form.js:75`：同樣無條件返回管理員清單；需一併做適用權限組合的回歸驗證。本次瀏覽器實證限景點。

重現：以測試帳號僅授予 `attractions.create`。

1. 正常登入後落在總覽，顯示「無法存取」，側欄沒有景點入口。
2. 直接開啟 `/admin/place-add.html` 可以填寫。
3. 新增 API 回 201；獨立清單 API 回 403，符合後端權限設定。
4. 瀏覽器保存後跳到 `/admin/places.html`，顯示「無法存取」。查詢測試資料庫確認該筆草稿已存在。

修正方向：依權限提供實際可用的入口及登入目的頁；只有新增權限時，保存後留在明確成功狀態，提供「再新增一筆」與合法離開位置。已有電影表單的相關處理可供共用設計參考。沒有檢視權限時仍須維持 GET 清單／詳情 403。

驗收至少涵蓋只有新增、檢視＋新增、唯讀模擬，以及登入返回、取消、成功保存、同步失敗。前端景點測試目前預設帶有 `view`，因此 88 項前端測試通過仍未發現此組合。

畫面證據存於本機被 Git 忽略的 `artifacts/review-20261020-create-only.png`。為避免把本機產物當作永久附件，重現步驟與實際結果已完整記錄於本文件。

## R3：導讀文件落後於實作

`docs/PROJECT-GUIDE.md:498` 仍寫「電影、消息、優惠、收藏與行程尚無正式業務表」。目前電影已有 `films`、`film_screenings`、`film_aliases`，公開 API、管理 API 與頁面亦已實作。應將電影移至已完成描述，保留消息／優惠／收藏／行程的未完成界線。

同檔 `:502` 的「八個服務」表格只有七列，未列 `publisher`。後文雖有說明 publisher，但作為環境總覽仍不完整，應補上職責、掛載與啟動方式。這是文件缺漏，實際服務有運行。

## 規劃、架構與資料表核對

| 項目 | 本次判定 |
| --- | --- |
| 優先級 | Excel「功能規格」J5–J34 實際為 P0 16 項、P1 11 項、P2 3 項。計畫的 P0 階段 2–8、P1 階段 9–12、P2 階段 13–15 與此界線一致 |
| 階段狀態 | 2–4 有既有實作與本機驗收，但需補修 R1／R2；5–15 明確未開始。P0 尚未完成 |
| 下階段 | 階段五的消息／優惠公開資料、受控匯入及首頁整合有來源。完整後台留階段九，符合 P0 公開列表與 P1 管理的切分 |
| 收藏／行程 | 依 U-08，必要保存／載回在階段六；完整編排及推薦路線套用在 P1。現階段不能將展示按鈕當作正式持久化功能 |
| 電影場次 | U-16 指定只比較日期與開始時間；未實作結束時間／場地是已確認的範圍，不能因資料表草案列出便當作缺漏 |
| 商家／路線表 | merchants 目前是關聯 attractions 的標記；routes／route_attractions 是引用保護前置。後續商家優惠及路線管理仍須完善，資料庫對照文件已有揭露 |
| PHP 分層 | 抽查 Controller → Service → Repository、DTO／Policy、交易與 Adapter 的責任符合既定架構；PHPStan level 10／PHPat 本次通過 |
| 認證規則 | 確認密碼在註冊／修改／重設的適用頁面為必填，API 的缺漏／空值／不一致案例通過；依 U-13／U-15 保留已確認政策 |
| 實際資料庫 | 主庫及測試庫均為 24 表、211 欄、12 份 migration；測試庫回報 MariaDB 12.3.3 |
| 查詢與索引 | 代表性電影清單使用 `film_admin`；場次重複查詢使用 `screening_start` 並以電影主鍵 join。未發現需立即新增索引的證據；這不是大量資料壓測結論 |
| 原始交付 | 對初始提交 `56733e5` 比對，原始 Excel 與 website 沒有差異 |

階段五細設仍須將優惠有效期、店家關聯、後續條款必填等規則逐項連回 Excel，並重用既有發布／媒體流程。這屬未開始階段的設計工作，不列為階段二至四的實作缺陷。

## 本次實際驗證與限制

| 驗證 | 結果 |
| --- | --- |
| Docker 內 PHPUnit | 55 tests、233 assertions 通過 |
| Docker 內 PHPStan level 10／PHPat | 無錯誤 |
| Docker 內前端 Node 測試 | 88 通過、0 失敗 |
| MariaDB／HTTP 整合 | 20 組通過 |
| OpenAPI 契約 | 54 個操作，驗證 768 個回應與 247 個成功請求，0 契約錯誤 |
| PHP 應用依賴 | `composer audit --locked --no-interaction` 未發現已知安全公告 |
| 新增邊界測試 | R1 在兩模組重現；R2 的 API 與瀏覽器流程重現 |
| 清理 | 暫時失敗 trigger 已移除；本次一次性帳號及內容已在測試環境停用／軟刪，保留稽核紀錄；主庫未被本次故障測試改寫 |

完整整合命令為 `python backend/tests/openapi_contracts.py --dependency-path artifacts/audit-jsonschema --include-films`。摘要證據在本機 `artifacts/review-20261020-contract-validation.json`、`review-20261020-probes.json`、`review-20261020-database.json` 及同日期測試 logs；這些產物均被 Git 忽略。

本次沒有重新逐頁完成全部桌面／手機／讀屏驗收，不能宣告所有畫面與原型完全一致。Google 外部 API 呼叫為 0，本次驗證解析資料契約及既有失敗流程，沒有重新確認金鑰／配額／即時 Google 回應。正式 HTTPS、SMTP、備份還原及大量資料效能亦不屬本次已驗證結論。Performance Schema 目前未啟用，本次只讀取代表性 EXPLAIN，未為審查重啟資料庫或變更監測設定。

介面審查套用專案 web-design-guidelines skill，查閱 [Web Interface Guidelines](https://raw.githubusercontent.com/vercel-labs/web-interface-guidelines/main/command.md) 的狀態回饋與導覽原則；業務權限仍以 Excel E27 為準。

## 建議處理順序

1. 先修 R1 共用發布錯誤邊界，新增真實故障與不重複保存驗證。
2. 修 R2 的權限導覽及保存後狀態，涵蓋景點、電影與管理員的適用情境，保留後端權限隔離。
3. 修 R3 導讀並更新對應模組／驗收紀錄；不能把先前通過的日期改寫為已通過新案例。
4. 重新驗證受影響流程，再進行階段五細設與後續授權範圍內的實作。

R1／R2 是既有實作修正，依 [Git 流程](../GIT-WORKFLOW.md) 備份後歸回對應功能提交，不另冒充新階段進度。本次沒有建立階段五分支、重寫歷史或推送遠端。

## 2026-10-21 修正與驗證

- R1 已修正：`ContentPublication.synchronize` 包含 projection／begin／render／finish 的失敗邊界，失敗紀錄寫入再次出錯亦被捕捉。已提交的景點／電影回 202 與保存結果，不回誤導的 500。新增真實 MariaDB INSERT／UPDATE trigger 故障測試共 18 項，驗證兩模組新增、更新、刪除、版本及公開隔離；finally 移除故障並重試發布。
- R2 已修正：登入、側欄與表單離開位置共用權限導覽。僅新增景點／管理員成功後停留結果並提供再新增，禁止重複提交；API 權限保持獨立。電影既有保存流程保留，補齊登入與側欄入口。
- R3 已修正：PROJECT-GUIDE 列明電影三表／API，補上 publisher 服務，移除「電影尚無業務表」的過時敘述；沒有新增 schema、migration 或 API。
- 電影介面：`film-view.js` 恢復首頁／片單不同海報遮色；單頁不顯示分頁。後台頁首的新增按鈕及映演清單標題回到原型位置。場次介紹改用原型電影頁殼，恢復 IMAGE MUSEUM / SCREENING、公告段落、類型／日期資訊列及返回本週映演；多場次逐列呈現真實日期，不帶入原型示意日期。移除額外更新日期與場次大標題。

| 實際驗證 | 結果及日期 |
| --- | --- |
| PHP／架構 | 10/20 Docker PHPUnit 55 項／233 斷言、PHPStan／PHPat 通過；ContentPublication 格式 dry-run 通過 |
| Rector | 10/20 dry-run 僅建議把既有 tick 閉包換為箭頭函式；與本修正無關，已檢視而未套用，不稱為零建議 |
| 前端 | 10/21 最終 renderer／導覽／表單的 Node 95 項通過 |
| MariaDB／HTTP／契約 | 10/20 修正後完整 21 組通過；54 操作、788 回應、260 成功請求，0 契約錯誤；10/21 電影公開流程另驗 22 項通過 |
| 片單版面 | 10/20 桌面 1440×1000、手機 390×844：hero／篩選／電影列／海報／日期／文字／時間尺寸與原型相同，無橫向溢位 |
| 場次介紹版面 | 10/21 對照原型光的形狀：桌面 1440×1000、手機 390×844，detail-page／cover／body／eyebrow／h1／lead／meta／actions 的寬、高、字級、行距與 margin 相符（浮點捨入除外），無橫向溢位；已檢視截圖 |
| 僅新增帳號瀏覽器 | 10/21 使用者明確同意後建立三個隔離帳號；景點／電影登入直達新增頁，保存成功留頁、出現再新增並停用重送；管理員帳號直達 admin-add，側欄與返回位置正確。管理員保存分支由 Node 測試覆蓋，本輪未另建第四個瀏覽器帳號 |

本機證據：`artifacts/review-fix-*-20261020.log`、`review-fix-node-20261021.log`、`review-fix-films-public-20261021.log`、`film-visual-measurements-20261020.json`、`film-detail-measurements-20261021.json` 與 `film-detail-fixed-*-20261021.png`。整合測試庫沒有自動 publisher，變更 renderer 後須先執行 `docker compose exec -T -u www-data app-test php bin/console.php content:publish` 再測，避免舊 generation 被正確拒絕為 503。

三個本輪測試帳號及瀏覽器新增的景點／電影已經由 API 軟刪，稽核保留。故障測試未寫入主庫；主庫原有電影與日期不變。Git 收整前已驗證 `artifacts/before-review-history-20261021.bundle`；修正按相應功能歸回，保留原始交付、各階段分支與 main 的 DBeaver 連線環境提交，不推送遠端。

仍存在的有意差異：原型「繼續閱讀／前後篇」留階段九；安排進旅行的必要保存留階段六。後台多場次數量／發布狀態欄保留已實作功能，列表內容與時間來自資料庫，不複製固定四筆示意清單。`island-slow.html` 原型使用城市散策標題／返回位置，本次電影頁統一採其餘三部電影的專用版型。以上不是全站所有頁面的像素一致宣告；後續階段與正式部署待驗項維持未完成。
