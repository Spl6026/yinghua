# 階段八：實作與驗收紀錄

2026-10-29 AA 最終判定：依 U-20 不以 NVDA 為必要工具；內建 Browser／隔離 Orca 補驗及有效基線逐項覆核後，WCAG 2.1 A／AA 為39通過、10不適用、1已廢止，沒有未處理失敗準則。到期／錯誤摘要焦點、正反向Tab、草稿保留及174項前端回歸通過；[完整50項矩陣與證據界線](P0-AA-FINAL-20261029.md)。不宣稱本輪重跑所有真縮放或取得Orca完整錯誤字句的自動語音；已聚焦的文字錯誤依W3C 3.3.1／4.1.2判定，沒有改寫工具結果。主機讀屏未啟動，測試資料與臨時環境清理；API／DDL不變。這是目前P0工程AA驗收，不是正式server或第三方標章認證；未合併main／未開始P1。下方日期段落保留歷史。

2026-10-29 內建 Browser 補驗完成：40 個原型頁面桌手機成對對照、10 個核心頁面載入後 320px 重排，以及登入、權限、景點／圖片、電影、收藏／行程、SEO、到期草稿與鍵盤操作已實測。修正電影週期沿用範本日期的缺陷，171 項前端回歸通過；[逐項結果、檔案用途與 AA 證據界線](P0-BROWSER-VERIFICATION-20261029.md)。已清理隔離資料並恢復 SEO／TTL；API／資料表不變。新提示的 Windows NVDA 即時完整播報未列通過，完整 AA 及正式 server 簽核不冒稱完成；未合併 main。下方保留歷史結果。

2026-10-26：依使用者指示修正 AA-01／AA-02，新增登入到期預警／原頁重新驗證與網站導覽（分類及名稱尋找）。修正與實測範圍見 [AA 差項修正](P0-AA-REPAIRS-20261026.md)。72 API／68 固定頁／33 表 316 欄／18 migration；原型與 Excel 不動。新提示的 Windows NVDA 採證未完成：啟動主機讀屏造成干擾，已依使用者反映停止並確認程序結束，後續不再自行啟動主機讀屏；不冒稱完整 AA 已通過。

2026-10-26 AA 逐項驗收已執行完成，整體未通過：37 通過／2 不通過／10 不適用／1 已廢止；[50 項準則與兩個失敗重現](P0-AA-VERIFICATION-20261026.md)。NVDA 真實完整提示／狀態／英文切換、反向 Tab 與模態循環、受保護頁面實際兩種縮放及文字間距已有證據。四類前端修正與 159 項測試歸回 P0 驗證提交；沒有 API／DDL／正式部署變動。剩餘 AA-01 到期提示／延長、AA-02 完整內容尋找途徑是確認的功能差項，不再籠統寫成讀屏工具未驗。原型保持，測試資料與自有 NVDA 已清理，未合併 main／未進 P1。

2026-10-26 登入後補驗：會員保存流程、管理員登入與列表、景點／電影／管理員必填焦點、完整權限／SEO 動態控制項已實際補驗；共用確認框補角色與說明關聯，原型版面不變。最終 Node 152、67 頁／81 HTTP 通過；[細項及未過界線](P0-COMPLETE-VERIFICATION-20261025.md#2026-10-26-登入後與確認框補驗) 包括 Orca 自動播報、反向 Tab／模態末端及 Windows NVDA，完整 AA 不宣告完成。測試發布同步、工具審查額度及 Docker 停止分開記錄；沒有主站發布／API／DDL 或 P1 新功能，仍不合併 main。

2026-10-26：依 U-19 新增背景隔離讀屏準備工具，實際 Orca／Firefox 已讀出首頁標題、登入欄位與空白警示、註冊確認密碼及同意框狀態，見 [補驗範圍](P0-COMPLETE-VERIFICATION-20261025.md#2026-10-26-隔離讀屏補驗) 與 [工具指南](../modules/isolated-accessibility.md)。Windows NVDA 保持停止；不將這些場景擴張為 Windows NVDA、全後台或完整 AA，仍不合併 main／不進 P1。主站、API、DDL 及 website 原始交付未改。

2026-10-26 01:20：31 公開／認證頁已補實際 400%（DPR 4、468×227 CSS px），無受檢頁水平溢出／文字裁切；恢復原服務後另以 480×241 補手機選單最後一項 Tab／Escape 與表單聚焦。與 200% 合計 64 次主要觀察，未把後台 320px 算成實際縮放；原生欄位部分下緣的可見範圍與工具失敗保留，見 [完整紀錄](P0-COMPLETE-VERIFICATION-20261025.md#2026-10-26-0120-實際-400-縮放補驗)。無新程式／DDL，NVDA 未再啟動，讀屏／完整人工 AA 未完成；未合併 main／進 P1。

2026-10-25 22:02：31 個公開／認證頁已做實際 200%（DPR 2、936×454 CSS px），無水平溢出或受檢文字裁切，表單 Tab 可捲至下一欄位。此輪沒有重跑全部套件或將後台 320px 寫成實際縮放。NVDA 視窗存取授權逾時，已停止自有 helper；400%／實際讀屏未驗完，未合併 main／進 P1。見 [補驗證據](P0-COMPLETE-VERIFICATION-20261025.md#2026-10-25-2202-實際-200-縮放補驗)。

2026-10-25 21:40：[介面補驗](P0-COMPLETE-VERIFICATION-20261025.md#2026-10-25-2140-網站介面補驗) 已補 50 頁、鍵盤／錯誤及圖片文字界限，修正封面重疊及 inline 圖片覆蓋遮罩；Node 152 與 67 頁／81 HTTP 通過。依 U-18，Docker CVE／主機配置另列部署交接，未改安全政策或抹掉掃描結果；完整人工 AA／實際縮放的狀態以補驗節為準。未合併 main／未開始 P1。

2026-10-25 20:15：依授權關閉 V-01／V-02／V-03 本機修正範圍。50 固定頁桌手機文字、四表單返回／窄螢幕、Google 拒絕／成功重試已操作，Node 152 項與 67 頁／81 HTTP 通過。保留首次色值與完整人工 AA／正式／安全差項；未重跑全 PHP／DB／部署，也未手動重發主 app 內容，未合併 main／進 P1。見 [修正追記](P0-COMPLETE-VERIFICATION-20261025.md#2026-10-25-2015-修正後結果)。

2026-10-25 15:21：按原始 Excel 七工作表／16 列 P0 及 website 重驗來源 `343bd7e`，見 [完整驗證](P0-COMPLETE-VERIFICATION-20261025.md)。40 個原型對應頁完成桌面／手機 80 組比對，另驗 320 CSS px、真 Google／圖片、電影衝突／保存、會員行程收藏、root 唯讀與退出、已保存 SEO 的分類／歷史／重整。API／DB、PHP／JS、67 頁／81 HTTP、74 head、冷部署 17／還原 25／回退 9 本輪通過；Semgrep 有 1 處 partial parsing，映像 CVE 保留。**對比、四個內容表單頂列返回連結及 Google 網址錯誤關聯仍有確定差項，完整 AA／正式門檻未過，不合併 main／不進 P1。** 應用與 DDL 未改；具名測試資料／SEO／隔離 root 復原，自有演練容器與 volumes 已清理。

## 2026-10-25 複驗差項修正

依後續授權完成 R01 小字對比、R02 館舍時間同源、R03 列表 SEO 修正；R04 已修正可重現的 alias 順序誤判並補 API／HTML gate 安全診斷，主站長駐 worker 已重載。PHP 91／318、Node 150、PHPStan／PHPat、格式 261、真 DB 發布順序 7、診斷 8、相關模組契約與真實桌手機驗收通過，見 [修正與閱讀入口](P0-IMPROVEMENTS-20261025.md)。前輪那一筆 503 仍不能追認根因；完整人工 AA、正式環境與映像安全差項未關閉，**P0 未宣告通過、不合併 main、不進入 P1**。下方為修正前各輪歷史。

## 2026-10-25 全 P0 再驗更新

使用者要求全 P0 複驗，本輪 10 月 24–25 日重新執行既有套件、真實瀏覽器、獨立冷部署 17 項、還原 25 項、回退 9 項及三種 scanner。完整成功／首次失敗／接續原因、16 列規格及介面限制見 [本輪報告](P0-REVERIFICATION-20261025.md)。**P0 仍未通過、不合併 main**：對比度、電影頁館舍時間同步、分類後 SEO 三個可重現缺陷，另優惠首次 503 尚未定位；映像／完整人工 AA／正式條件亦待處理。

本輪只改善 SMTP 自有 fixture 及優惠錯誤觀測兩個測試工具，沒有修改應用邏輯／UI／schema。Google Text Search 實連一次，未套候選或自動存資料；Semgrep／Gitleaks 確實已執行。以下原有紀錄保留 10 月 24 日該輪結果，其「實連 0 次／掃描未執行／沿用 UI」不能覆蓋最新結果，後續成功也不抹掉首次 503。

更新：2026-10-24。分支 `phase/08-p0-validation`，依使用者「實作」授權。**程式與隔離部署／復原基線已交付；8E 完整人工無障礙與正式環境尚未驗完，P0 界線未通過，未合併 main／未進入 P1。** 來源與設計見 [計畫](PHASE-8-PLAN.md)、[16列矩陣](P0-ACCEPTANCE-MATRIX.md)、[運維閱讀指南](../modules/operations.md)。工程數值均標 E-29，未增加業務必填。

## 實際範圍

- 8A：CSRF 拒絕原因分類與有限5秒時鐘回撥預算；保留 origin、HMAC、期限及身分命名空間驗證。12項受控案例、修正前後各100輪固定觀察，沒有重試到成功。舊註冊失敗未記回應碼，**無法追溯確認其根因**；不能把時鐘假設說成當時已證實原因。本輪沒有再現正常流程的未解釋拒絕。
- 8B：新增匿名唯讀 `GET /api/v1/health`，含隱含 HEAD／405、安全503、核心設定與18份 migration 名稱／checksum／完成狀態；不自動建表或寄信。普通 API 的初始 DB 連線失敗亦安全503。合計 **72 API、67固定頁、33表／316欄／18 migration**，無新DDL。
- 8C：獨立 `compose.production.yaml`、同版 app/web 映像與 manifest、TLS／308／HSTS、Cookie、DNS／上游逾時、發布就緒、日誌輪替及秘密過濾。正式配置只有 app／publisher／web／db，DB／FPM 無主機port；唯一來源掛載為唯讀TLS憑證。開發8080／測試8081及本機DB3306仍分開。
- 8D：同維護窗口的DB＋媒體＋release備份，另一個全空目標還原；重建公開HTML、撤銷舊session／reset／challenge；缺圖、毀損、schema錯誤、發布失敗不開流量。rollback保留目前DB，不切回舊HTML或自動down migration。
- 8E：跨模組回歸、健康契約、實際桌面／手機操作；修正公開／後台手機選單關閉後仍可Tab到隱藏連結及Escape焦點問題，保留原型CSS。完整人工讀屏／縮放／對比仍待驗。

## 固定驗收 8-T01–24

「通過」只指列明的本輪範圍；部分通過／待驗不等於需求不存在。主機正式項不可由自簽憑證或 Mailpit 代替。

| ID | 實際證據與結果 |
| --- | --- |
| 01 | 原Excel只讀核對16列P0及E／G／L；[來源矩陣](P0-ACCEPTANCE-MATRIX.md)。原型69檔完整性另由交付檢查核對。 |
| 02 | 矩陣列出頁面／API／表／成功及拒絕證據；業務本機回歸通過，正式內容及AA未勾選。 |
| 03 | 12受控CSRF、前後各100輪預期422／無重試；真會員3組及舊模組本輪無CSRF refresh。歷史缺失資料不能追認根因。 |
| 04 | health最小200、HEAD空body、405／Allow、精確路徑、no-store／無Set-Cookie；6個真HTTP回應由jsonschema4.26.0獨立核對。 |
| 05 | health18項、六種CLI故障；migration journal逐欄復原；正式隔離停DB health503在1.984秒。普通API連線邊界亦驗503。 |
| 06 | api_integration／admin_preview／specification_alignment／review_regressions真MariaDB：停用／撤權／root／最後管理權／唯讀模擬／僅新增。 |
| 07 | 認證回歸含U-13八字／U-15必填確認、reset過期／重用、限速／郵件失敗；獨立HTTPS登入實驗Secure／HttpOnly／SameSite。正式SMTP未驗。 |
| 08 | attractions／films admin／public_content_security及真選檔器上傳PNG320×240；內容檢查／重編碼／alt／授權與私有圖；U-14不是病毒掃描服務。 |
| 09 | map_fields／google_link／owner_requirements測允許網域、短鏈／redirect／SSRF及失敗不自存；瀏覽器非Google URL拒絕。本輪Google實連0次，正式配額另驗。 |
| 10 | 景點／電影／消息／優惠／首頁／排程與週界API回歸；實際景點草稿→發布、電影重複時間拒絕→有效保存→前台兩場次。 |
| 11 | 會員3組467項／219契約回應，含本人隔離、並行／409、5輪發布；實際收藏／加入／載回／移除／登出。 |
| 12 | SEO設定74項／32契約、發布60項／12契約、無JS head74回應零差異、政策13項；設定頁422／409保留與重新載入／保存已實測。正式canonical／社群抓圖未驗。 |
| 13 | publication_failures及正式演練停worker／到期／重啟；安全503不供應舊頁，管理修復入口仍可用。 |
| 14 | [本輪畫面紀錄](#實際介面及保留差項)；代表頁及主要寫入流程已測，沒有把67頁資產檢查說成67頁所有互動。原57網址逐一全流程未重做。 |
| 15 | 手機390×844／桌面1440×900、鍵盤／Escape／欄位錯誤已驗；**人工讀屏、200%文字、400%縮放、完整背景對比未完成**。不宣告WCAG AA。 |
| 16 | preflight、實際容器／manifest檢查及映像秘密掃描；4服務／3卷、無程式bind與DB外開；[安全報告](PHASE-8-SECURITY-REVIEW.md)保留CVE差項。 |
| 17 | r5全空project前三項：空庫health503、migration第一次／重跑、受控admin；同版首發布通過。後續使用明示resume，合計17個不同檢查，沒有冒充一次全程無中斷。 |
| 18 | r5停DB／FPM／publisher、排程到期及重啟均實測；DB恢復HTTP200後還要等Docker Health變healthy，再執行Compose相依啟動。 |
| 19 | r5最終25項還原，1張真圖；全業務表逐列hash、media hash、trigger與帳密保留；三種舊授權撤銷。備份3.500秒／還原4.172秒，非正式SLA。 |
| 20 | 損壞dump、缺圖（即使更新外層hash）、不相容migration、非空目標及發布失敗拒絕；9項回退通過。同source兩種release label，不承諾任意歷史程式相容。 |
| 21 | PHP87項／302斷言、Node145、PHPStan10／PHPat、格式257檔零差異；Rector dry-run40檔建議已閱，未套用。固定頁／資產完整性另列最終交付核對。 |
| 22 | Composer production依賴audit無已知公告；Trivy0.76.1真映像掃描有剩餘HIGH／CRITICAL，非全綠。Semgrep／Gitleaks未執行，不當作通過。 |
| 23 | **待正式環境**：主機／可信網域憑證更新、SMTP／寄件、Google服務配額、內容／年份／圖片權利、業主條款版本及備份營運政策。 |
| 24 | 本輪UI fixture清理／臨時project清理／原始樹及文件連結以交付檢查記錄；保留正常audit與忽略的私有證據。main不先合併，遠端不變。 |

## 本輪回歸與診斷紀錄

舊模組契約分兩批：前三組275回應／67成功輸入，content_contract因新增health未列入測試路由盤點而中止；修正盤點後只重跑剩餘18組，513回應／193成功輸入，零契約差異。**合計788回應／260成功輸入**，不是沿用舊phase7報告的789。另消息／優惠／首頁／安全／原型匯入五組均通過。health另6真HTTP契約；會員／SEO契約另計，未聲稱72操作所有可能狀態全覆蓋。

最初正式DB中斷測試曾觀察health超過6秒、普通API／首頁500。已補glibc DNS預算、普通PDO連線錯誤與Nginx gate／上游503映射；最終r5停DB後health／公開API／首頁分別503（16／15／32毫秒），DB恢復200。另pause無回應情境health503為1.984秒。保留前後資料，不把首次失敗抹去。

r5部署演練第一次後段因DB容器標籤仍unhealthy而Compose拒絕啟動FPM；當時health API已恢復200，兩種探測責任不同。測試工具增加30秒固定上限等候Docker DB健康，保留診斷／安全錯誤原因；未降低health標準。resume14項通過，先前冷啟動3項仍用其原結果。

Rector40檔為nullsafe／arrow、array_first／last／all及new後呼叫省略括號等語法提案；新readiness／recovery亦只是省括號建議。保留較易讀的明確形式，不自動批量改動。第一次格式命令誤用工具路徑未執行，其後以 `/var/www/tools/vendor/bin` 正確命令檢257檔通過。

## 實際介面及保留差項

Brave／localhost:8081，獨立合成會員與管理員，不使用正式帳號；實際內容日以台北時間2026-10-24建立，未替原型無年份電影猜年份。

| 頁面／流程 | 本輪人工結果 |
| --- | --- |
| 後台登入、景點列表／新增／編輯 | 空值焦點、錯誤登入、Enter登入；搜尋、刪除取消／Escape、真選檔上傳、可選摘要／介紹、結構營業時間、草稿／發布保存。 |
| 本週電影／獨立詳情 | 與原型desktop頂列82px／hero527.67px，mobile65px／464.7px；兩場次海報／照片、場次介紹到獨立頁可達。內容資料不同不假裝像素完全相同。 |
| 電影編輯 | 同日期12:00／12:00伺服端拒絕並關聯欄位；改18:00可保存，清單顯示2場。結束時間／場地依U-16保留。 |
| 網站SEO | 422欄位錯誤、409保留未存值、重新載入取消／確認、有效保存成功；390px無文件橫向溢出。 |
| 收藏／目前行程 | 登入→收藏→加入→重新載回；取消收藏顯示空態；移除行程保存、登出回匿名首頁。 |
| 公開／後台手機選單 | 已重現隱藏選單仍可Tab；inert修正後不能Tab入、Escape回觸發鈕，desktop斷點恢復。 |
| 權限頁 | 390px表格在自身auto容器橫捲；固定root40項全勾選且停用，儲存停用。未藉UI改其他既有測試帳號權限。 |

截圖與不含帳密觀察放忽略的 `artifacts/phase8-browser-observations.json`、`phase8-cinema-mobile.jpg`、`phase8-settings-mobile.jpg`、`phase8-favorites-mobile.jpg`。CDP在完整導向後偶發失聯，重新綁定同分頁即可確認結果，未誤記成網站登入失敗。DOM／AX可見訊息不是實際讀屏報讀結果。

## 證據與重現

私有artifacts不提交：phase8-health／health-contract、stability-observation、db-outage-before／after、production-rehearsal、r5-first-control-failure／health-label-race、recovery-rehearsal、rollback-rehearsal、validation-results、掃描final-r5與瀏覽器觀察。備份含密碼hash／個資，勿分享或提交；.env及演練帳密只留忽略檔。

工具操作見 [部署指南](../P0-OPERATIONS.md)。`phase8_validation.py`固定順序，首次失敗即停；`--start-at`只在保留前段證據及診斷後明示續跑，報告不可隱藏批次界線。沒有重跑的正式環境及未完成人工項，維持待驗。

## 交付核對（本輪實際結果）

開發app／publisher／app-test已用PHP8.5.12開發映像重建，web／web-test載入本次共用Nginx設定；未重建或清除主DB volume。67固定頁、14資產、81個唯讀HTTP完整性檢查零差異；這不是全頁視覺通過證明。主／測試health皆200，實際schema33表316欄18migration，原18份migration未改。

本輪具名UI fixture的景點／電影／媒體／收藏／行程已清理，SEO還原；兩測試帳號停用並撤銷session，正常audit保留。核對Compose label及本專案正式設定路徑後，清除12個自有spl-p0-* project與其資料卷；主DB容器ID及原資料卷名稱前後一致，其他使用者project未操作。備份、私有env、掃描與證據保留於忽略檔，不提交或刪除正常audit。Trivy公共CVE快取及已建映像可供後續重用。

1053個本機文件連結檢查無缺漏（已知防毒隔離的第三方skill參考檔維持原差異）；原始第一提交69檔、website與Excel完整，main與origin/main未改，發布來源262201d保留為祖先。.env／測試帳密／TLS私鑰／artifacts被Git忽略。復原私鑰等未輸出或加入Git。

較早驗證工具在resume後仍硬寫「21-suites」的摘要已改成實際批次數並附contractStartAt；舊原始證據保持，另以phase8-regression-batches.json記3＋18批／788回應／260輸入／零契約差異。本次metadata修正不冒充重新跑21組。

按功能交付本階段提交；完整人工AA及正式差項完成前留在階段分支。日後合併前仍須重新備份Git歷史、核對最終tree及原始交付，以merge commit合併並保留分支；本次沒有先合併再把待驗寫成已完成。
