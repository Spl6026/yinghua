# 階段十實作與驗收紀錄

2026-10-30。使用者授權「階段十實作」，在 `phase/10-recommended-routes` 完成推薦路線程式及自動化驗證。**最新：已補完本階段實際 Browser 本機範圍，功能與新增介面驗收通過；共用AA基線與正式差項分開保留。** 詳設與來源見 [計畫](PHASE-10-PLAN.md)，檔案閱讀見 [模組指南](../modules/recommended-routes.md)。

已交付：路線 CRUD、搜尋／分頁／狀態、路線顯示排序、候選景點搜尋、站點上移／下移、建議時間／備註、整份交易與版本衝突、軟刪與引用保護、公開原型卡片與有序站點、同版HTML／API、原型覆寫隔離。完整路線套用屬階段十一，公開卡片明示待開放，僅提供旅行計畫入口；不能將功能規格 L24 整列標完成。

## 初輪自動化與工具失效時的歷史紀錄

| 驗證 | 結果／界線 |
| --- | --- |
| PHPStan level10／PHPat | 通過；Controller不查DB、Service不依賴HTTP／PDO、Policy／DTO不依賴Persistence。 |
| PHPUnit | 99 tests／342 assertions；新增5項路線規則測試。 |
| 前端全套 | 198通過；新增6表單行為＋2公開卡片測試，包含202後重載同筆再更新，不重複新增。 |
| 真MariaDB／HTTP | 57檢查通過：CRUD、草稿、選填、空站／重複／不存在／下架拒絕、順序、時間保留、引用409、版本409、並行一勝一409、下架站過濾／全無效隱藏、權限與稽核。 |
| 獨立OpenAPI | 45回應、10操作（含全部7新增）、零schema錯誤；成功請求亦驗證，不用既有程式當規格來源。 |
| 空庫／舊資料升級 | public_content_schema_upgrade.py fresh／upgrade通過，各33表／19migrations；既有會員、景點及店家profile保留，外鍵／唯一失敗案例仍成立，無延後電影欄位。舊檢查改按來源migration清單，非固定18。 |
| 格式／Rector | 新增11個PHP檔案依dry-run差異作有範圍PER-CS格式修正。Rector僅dry-run：兩檔可選語法建議已檢視，保留明確null分支及new括號，不稱零建議。 |
| MariaDB查詢 | 12.3.3，public使用route_public，站點使用route_stop_order；performance_schema原為OFF，本輪非正式慢查詢診斷，不為技能重啟DB／增全域監控。不憑小樣本估計大資料效能。 |
| 建置 | 68固定頁；新增CSS後18個直接引用資產。 |
| 原始交付 | 原始第一提交69檔逐位元相同；website及Excel不改。 |

隔離工具的首次失敗包含：HTML未走發布Nginx路由，已補正式routes.html版本檢查；測試fixture誤用permissions欄名，已更正及清理；VM測試替身漏接import別名，修正後5項通過。這些是本次自身修正，不另留進度提交。編輯期間的一次HTTP完整性巡查遇6項503，當時前端來源及測試路線正在變更；保留 `artifacts/phase10/integrity-test.json`，最終固定來源發布後另記結果，不抹掉失敗紀錄。

完整性最終結果、清理及Git核對見下方追記。本機資料庫為33表327欄（包含schema_migrations的4欄），19migration；API 96操作，固定頁仍68。未新增外部套件／服務／環境變數。

## 介面與原型差異

沿用原型後台側欄／頁首、獨立新增／編輯／列表、admin-panel／admin-form-grid／表格、公開hero及route-card。新增的發布狀態、時間、排序與站點控制來自Excel；選填文案、錯誤／載入／空狀態、焦點與上移下移屬E-32，沒有把列表摘要／介紹變必填。路線名稱仍依原型必填。時間儲存分鐘，天數可換算；沒有憑空增加場地或結束時間。

依本輪 [官方Web Interface Guidelines](https://raw.githubusercontent.com/vercel-labs/web-interface-guidelines/main/command.md) 檢視來源，修正動態label、標題階層、手機表格容器、路線SEO protected狀態，並提供網站導覽及planner的路線入口。新增控制項沿用button樣式、原生鍵盤動作、可見焦點及錯誤關聯；這是原始碼審查及行為測試結果，不能取代實際畫面。

內建Browser清單及建立分頁均回報 `browser access host is unavailable`，重設後仍相同；因此尚無本輪真桌面／390／320畫面、鍵盤焦點與200%／400%重排證據。沒有啟動主機NVDA。共用P0有效基線仍保留，但不能據此把新增站點控制宣稱完整AA已驗。本輪不合併main，待工具恢復後補實測，不要求使用者改安全設定。

## 最終收尾

來源固定及發布後：主站 `artifacts/phase10/integrity-main.json`、隔離站清理後 `artifacts/phase10/post-cleanup-test.json` 均為68固定頁／18資產／86 HTTP，issues=[]。較早 `integrity-test-final.json` 曾有單筆優惠503，當時尚有fixture清理與發布；保留該報告，不把中間巡查寫成通過。最後一輪隔離巡查完成前未再更動公開來源或測試內容。

自有路線剩餘0、fixture已清理、暫時帳號有效session為0，正常audit保留。真實路由與OpenAPI一致共96操作；新增前的66條path及所有既有schema語意不變。新文件連結均有效，原始69檔逐位元未變。.env、帳號秘密與本機SQL備份未提交，原有防毒隔離差異維持不納入。

本機主庫先備份至忽略的 `artifacts/phase10/before-018-main.sql`，再套用018；沒有覆寫業務內容／帳號／權限。API及Nginx路線的既有服務已重新載入。完整正式部署演練未在本輪重跑；production_rehearsal 的固定67頁／18migration舊常數改成實際來源清單，此檔不列本輪執行通過。

本階段三筆：資料層準備、完整路線管理／公開功能、整合驗證與導讀。202重載自身修正已歸回功能提交；改寫前 `artifacts/phase10/before-feature-amend.bundle` 已verify。main 保持 `910dd6a`，未合併／推送。Browser待驗清單為三個後台頁及routes公開頁的桌面／手機、鍵盤排序／錯誤焦點、刪除確認與原型視覺比對；工具恢復後必須補完才可合併。

## 2026-10-30 使用者要求再次驗證

本輪沒有變更應用程式、DDL或環境設定。實際重跑PHPUnit99／342、PHPStan10／PHPat、前端198全部通過；路線57項整合檢查及45回應／10操作的獨立契約零錯誤。另重跑共用發布的消息／優惠116檢查及70回應／22操作，契約零錯誤。結果保留 `artifacts/phase10/reverify-frontend.txt`、`reverify-shared-content.txt`，本輪沒有重跑空庫升級、正式部署或完整86項HTTP清單，這些仍引用前節具體證據。

測試完成清理自有fixture及session；補做兩站的三個管理頁、公開routes.html及GET /routes，共10次唯讀HTTP均200，公開空狀態可讀，管理頁保持noindex與認證入口。這只驗證服務與HTML來源，不宣稱瀏覽器已執行JS或畫面正常。再次核對96路由契約、.env忽略、原始69檔與資料庫33表／327欄／19migration。

本輪重新讀取官方介面準則並核對原型表單與本次控制項來源，未重現功能失敗。內建Browser建立IAB分頁與列出瀏覽器均回報 `browser access host is unavailable`；當時Docker服務及本機HTTP可用，故阻擋位置是Browser工具連線。已請使用者在目前對話開啟內建Browser以重試，未要求安全權限或讀屏。實際桌面／手機、焦點及鍵盤尚未驗，**整階段仍待驗、不合併main**；不以本次自動化重跑關閉這些差項。

## 2026-10-30 Browser恢復後的最終本機驗收

此節覆蓋前節的工具待驗狀態。內建 Browser 恢復後，以自有 yinghua_test 帳號實測三個管理頁及 routes.html，並用臨時唯讀HTTP服務載入原始website四頁比對。**階段十本機功能與新增介面範圍通過**；完整會員一鍵套用仍為階段十一，不把 L24 跨階段流程標為已交付。

| 實際Browser情境 | 結果 |
| --- | --- |
| 匿名後台入口／登入 | 正確轉登入，登入返回路線清單；使用自有驗收帳號，不使用主庫密碼。 |
| 必填與錯誤 | 空名稱原生提示並聚焦route-title；有名稱但零站回錯誤，route-stops具aria-invalid／describedby並聚焦；主題、介紹、分鐘留白仍可保存。 |
| 站點／草稿／發布 | 選兩站，鍵盤Enter上移；時間10:30及備註跟隨原景點保存。草稿不公開，切已發布後公開卡片及站點連結正確。重複景點候選禁用。 |
| 路線排序 | B排序1、A排序8，前後台B先A後；A改0後兩邊都A先B後。 |
| 重載／確認框 | 取消重載保留輸入並返回原按鈕；確認才還原server值。框內正向Tab及反向Shift+Tab循環通過。 |
| 兩分頁衝突 | 舊版本保存收到衝突訊息，名稱輸入保留且聚焦route-form-status；未靜默覆蓋。 |
| 搜尋／狀態 | 無結果、恢復兩筆、草稿／已發布篩選皆正確；首末頁按鈕禁用。 |
| 手機導覽／清單 | 選單可開啟、Escape關閉返回按鈕；表格區可用方向鍵水平捲動，不造成整頁溢出。 |
| 權限畫面 | 使用者明確核准自有帳號切換後，僅view不顯示新增／編輯／刪除且直接edit無法存取；僅create清單無權，但新增頁可選景點並保存，不跳回無權清單、不重複送出。已依fixture建立時的四筆原值恢復，最後停用／清理。 |
| 刪除 | 取消不刪，焦點回原按鈕；確認後管理／公開同步，最後一筆刪除顯示空狀態。修正後聚焦route-list-status並有可見外框。 |

1440×900、390×844、320×844，各四頁，共12筆實際DOM／畫面觀察，皆無整頁水平溢出及未標示輸入欄位；另12筆原型版型觀察。保留原型hero、卡片、字型、後台殼、獨立新增／編輯及按鈕樣式。Excel新增的排序／發布／時間／備註控制、P0既有對比修正、網站導覽與尚未開放套用的明示文案不要求逐像素相同。原型列表標題h2在本版改善為h1；原型多選select改為能明確排序的選站按鈕及有序清單，以滿足E24/G24及鍵盤操作。

找到並修正兩個本次自身差項：公開路線缺旅行計畫的active／aria-current；刪除成功後焦點落BODY。兩項皆在內建Browser修後複驗，並補真HTML導覽回歸與兩項前端刪除行為測試。修正歸入完整功能提交，不另造修錯進度。尺寸能力第一次套到原型分頁，已依實際innerWidth辨識，另建專用分頁後取得正確12筆，不把1280誤記1440。

修後重跑：前端**200通過**、PHPUnit**99／342**、PHPStan10／PHPat通過；路線**58檢查**、**45回應／10操作，schema零錯誤**。畫面證據在 `artifacts/phase10/browser-*-1440/390/320.png`、`browser-matrix.json`、`prototype-matrix.json`、`browser-results.json`、`delete-focus-mobile.png`、`form-controls-mobile.png`。手機底部按鈕可捲到並操作，已實見save焦點外框。未以Playwright直接聚焦離屏控制項冒稱原生自動捲動已驗。

本輪是新路線頁面的功能／語意／鍵盤／窄版增量驗收；共用AA沿用已完成的P0有效證據。**沒有重新執行完整50準則、原生200%／400%縮放或NVDA／Orca，不稱新一次全站AA認證。** 本機階段通過與正式server、安全／上游映像及跨階段套用交接界線保持分開。

測試用三條Browser路線均經畫面刪除後清除自有資料，58項API測試資料亦清理；暫時帳號停用、有效session為0，正常audit保留。原始69檔仍逐位元相同。.env不提交，沒有新增schema／API數量。所有自建分頁關閉、尺寸還原，原型8092臨時服務停止，未操作主機讀屏或使用者其他視窗。

清理後最後全站HTTP巡查：8080主站與8081隔離站各68固定頁／18資產／86 HTTP，兩份 `browser-final-main.json`／`browser-final-test.json` 的 issues 都為空；本輪最終來源沒有503差項。最終路由96與OpenAPI一致，原66條path及既有schema未改，無壞文件連結。這些是服務／建置完整性，與上表真Browser操作證據分列。
