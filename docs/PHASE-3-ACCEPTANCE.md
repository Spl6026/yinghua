# 階段三驗收記錄

## 2026-10-18 新增要求 U-09：共用營業時間

人工建檔與 Google 候選使用相同七天編輯器及 OpeningHours DTO，支援未知、公休、全天、午休多段及跨日。舊文字保留備註，不推測每天相同。新增004 migration的 opening_hours JSON；API仍44操作、業務表仍20，主庫／隔離庫均先備份後升級及發布。完整設計、相近欄位問題及本輪結果見 [營業時間計畫](OPENING-HOURS-PLAN.md)，逐檔閱讀見 [營業時間指南](modules/opening-hours.md)。

本輪實際通過：PHPStan level10／PHPat、PHPUnit 27 tests／131 assertions、PER-CS dry-run零差異、前端53項／64頁建置；HTTP既有65項、契約9項／44操作、先前修正21項、新週表21項（真實MariaDB、保存／公開HTML、越權、唯讀、版本及錯誤不改資料）。Rector僅dry-run，14檔風格提案已檢視、未套用。未重跑Google實連、正式外部服務或獨立掃描器。

實際桌面操作：重疊時段顯示具體錯誤、焦點與保留輸入；修正並保存後重開和公開詳情一致。桌面寬1745；手機請求390×844、實際355 CSS px／scrollWidth341，週表欄位與公開詳情可讀、無水平溢出、錯誤記錄空，尺寸已還原。截圖 artifacts/opening-hours-admin-desktop.png、opening-hours-admin-mobile.png、opening-hours-public-mobile.png。自有隔離驗收筆完成後軟刪除；原始website／Excel不動。後續舊章節為各次歷史結果，不把舊測試數量當成本輪數量。

日期：2026-10-17 至 18；完成記錄更新於 2026-10-18。範圍：P0 階段三 3.1–3.5，分支 `phase/03-attractions-media`。不代表 P0 或全站完成。依據與範圍見 [階段三計畫](PHASE-3-PLAN.md)。

## 實際執行結果

| 驗證 | 結果 |
| --- | --- |
| PHPStan level 10＋PHPat 五項相依規則 | 通過，無錯誤 |
| PHPUnit（PHP 8.5.11） | 15 tests／73 assertions 通過 |
| PER-CS dry-run | 通過，files 為空；檢查工具在 Docker 內執行 |
| Rector dry-run | 已執行，有 14 檔語法建議；以去括號／closure 簡化為主，已檢閱，未套用無必要的整站改寫；非零結束代表有提案，不冒稱無差異 |
| 前端 Node 行為與真實 HTML 產生測試 | 36 項通過，含原有 33 項、新增 HTML 產物兩項及 slug 返回網址一項 |
| 階段三 HTTP／MariaDB／上傳／發布 | 65 項通過；一般回歸不耗 Google 配額 |
| OpenAPI 與實際 Router／內容輸出 | 44 個操作一致，9 項欄位契約檢查通過；不是完整 JSON Schema validator |
| 內容重啟持久化 | 6 項通過；重啟 app-test／web-test，HTML／圖片逐位元組一致、管理版本保留，刪除後 HTML／匿名圖片皆 404 |
| 階段二回歸 | 認證 88、唯讀模擬 58、回應欄位 32、交易／例外 6、SMTP 失敗恢復 4 項通過 |
| 標準 Host／轉向 | 17 項通過，不使用憑證 |
| Nginx | nginx -t 通過；app／web／db／測試服務健康，frontend 建置 Exit 0，publisher 執行中 |

HTTP 測試使用 yinghua_test、8081、隔離 root／測試資料與各自 media／publication volumes；不重設正式 admin、不清空正式資料、不寄外部郵件。例外／故障注入只存在 CLI 測試入口，無 HTTP 後門。產物與測試憑證放忽略的 artifacts，密碼與金鑰不寫入本文件。

## 內容與安全流程

實際通過：新增草稿、唯一 slug／歷史路徑、分類／行政區／搜尋／分頁、發布後 API 與完整 HTML、SEO／canonical／OG、純文字 XSS 編碼、版本衝突 409、欄位白名單、錯誤座標 422、舊 slug／legacy_id 301、未知／下架／刪除 404、排程未到期不公開及到期 tick。

使用真實 MariaDB 的 route_attractions 建立引用後，刪除景點回 409；解除引用後可刪除。這只驗證 P0 關聯保護，沒有完成 P1 路線管理。

發布失敗驗收將隔離 generation 目錄設成不可寫：資料保存回 202、journal failed，公開 API／舊 HTML 都回 503；恢復寫入後授權重試成功，下架網址 404。避免將「DB 成功」當成「HTML 已同步」。短暫同步期間可能出現 503，是目前一致性策略的可見行為。

媒體驗收包含真實 multipart／GD 解碼／重新編碼及去除 metadata，拒絕 SVG、偽 JPEG、無 alt 與尺寸超限；草稿圖片匿名 404、管理員可讀，發布後匿名可讀。未登入 401、缺 CSRF／跨 Origin／越權 403，唯讀模擬拒絕寫入、上傳與地圖解析。已審查本次管理 API、SQL 參數化、輸出編碼、檔案路徑與外部請求邊界；未執行 Semgrep／Trivy／Gitleaks，不把人工審查稱為掃描器通過。

## Google Places 實連

使用者已提供 .env 的伺服器金鑰。完整 Google Maps 名稱查詢網址及真實 maps.app.goo.gl 短網址均成功取得候選（HTTP 200，各至少一筆）。短網址取自 [新竹市環境教育設施公開交通頁](https://xipuzi.hccepb.gov.tw/howgoto-01.php)。測試不儲存外部候選資料、不下載 Google 圖片。

瀏覽器也實際解析出候選、開啟確認對話框並帶入名稱／地址；候選確認不觸發儲存，之後仍可人工修改。

單元測試另外驗證惡意重新導向、循環、錯誤 JSON、配額錯誤、缺金鑰及私有 IP。正式 Google 帳戶的使用／保存條款、正式額度與正式素材授權仍由客戶確認；本機實連不是正式營運條件的驗收。

## 實際瀏覽器與原型顯示

在隔離後台使用原生檔案選擇器選取本機自製 PNG（640 × 400），填 alt／來源／授權，按上傳後顯示尺寸及圖片預覽；儲存發布後，另一個公開分頁直接開啟新 slug，顯示相同圖片與內容。再編輯正文、保存及重新開啟，修改確實保留；列表刪除確認後，公開網址最終回 404。同步中的短暫 503 亦有實際觀察。

公開列表也實際驗證「看風景」篩為兩筆，再搜尋「博物館」只剩一筆，清空條件後恢復六筆；沒有更動正式內容。

原型字體、導覽、內容寬度、卡片／詳情及 footer 沿用既有 frontend／website 樣式；階段三必要欄位、上傳、發布與同步提示為新增互動。驗收發現的 UTC 更新日期已改台北時區，增加跨日產物測試。原始 website 未改寫。

桌面實際 innerWidth 1745；手機覆寫請求 390 × 844，但瀏覽器回報實際 innerWidth 355，故本紀錄以 **355 CSS px** 驗收，不宣稱精確 390。前台與後台窄螢幕均無水平溢出（documentElement.scrollWidth 341），表單可載入，已保存圖片預覽可讀；瀏覽器錯誤記錄為空。截圖位於 artifacts/phase3-public-desktop.png、phase3-public-mobile.png、phase3-admin-mobile.png。

本輪 UI 審查使用 web-design-guidelines 與 browser skill，範圍為本次景點表單、清單及公開內容資產。已核對 label、原生欄位、按鈕 disabled、狀態區、確認對話框及替代文字。沒有宣稱已完成全站 WCAG 認證或所有未修改模組的像素比對。

## MariaDB 索引驗證

MariaDB 12.3 實際 EXPLAIN：管理列表使用 attraction_admin（ref），狀態篩選 range；公開投影使用 attraction_public（range）；路線反查 route_item_attraction（ref）；媒體分頁 media_module_created（ref）。樣本為少量開發／測試資料，不能推論大量資料效能。名稱／地址包含搜尋本來需要掃描候選，不為小型資料另加無效 B-tree。完整結果保存在 artifacts/phase3-explain.json。

## 保留界線與正式差項

- 已完成景點／店家、博物館、首頁景點精選、媒體、地圖候選、公開 HTML 與本模組 SEO。收藏／加入行程的本人持久保存留階段六，功能規格列 13 仍是部分完成。
- 電影／場次、消息／優惠、完整路線、會員旅程、SEO 全站設定、JSON-LD／動態 sitemap 與後續管理仍按計畫，沒有提前完成。
- 正式 SMTP、HTTPS 網域、正式內容／素材權利、外部服務營運配額仍待正式部署驗收。
- 目前公開內容採整集合重新產生；每 10 秒檢查排程，更新期間容許 503。歷史 generation 及未引用媒體不對匿名公開，但尚無自動清理／大規模增量發布。

原始交付比對：第一提交 56733e5 的 69 個檔案逐位元組相同（website 68＋Excel 1），規格 SHA-256 為 d445ae7f8478b10fc938f91d9bcf85802dc48bcc3487dd01664ae06cfbaeeef5。

全站逐檔／資料庫／環境指南已更新 [PROJECT-GUIDE.md](PROJECT-GUIDE.md)，並提供景點、媒體與地圖中文閱讀指南。後續完成階段仍須同步更新。

## 功能提交可獨立檢查

共用準備、公開發布、管理／媒體、地圖匯入四個程式快照，各自以隔離來源副本執行 Docker PHPStan、適用 PHPUnit、64 頁前端建置、當時 Node 測試及實際路由／OpenAPI 對照，均通過。API 操作依序為 29／34／43／44；不是只有最終工作樹可建置。第五筆為整合／重啟驗收與最終文件交付。

## 2026-10-18 使用者要求再次複查

本輪重新執行 PHPStan／PHPat、PHPUnit 15 tests／73 assertions、前端 36 項、階段三 HTTP／MariaDB 65 項與契約 9 項／44 操作，均通過。沒有重跑 Google 實連，也沒有沿用舊報告冒稱本輪結果。

複查當時發現下列既有案例未覆蓋的問題；後續修正結果見本文件末節：

| ID | 實際發現 | 證據與界線 |
| --- | --- | --- |
| R3-01 | 圖片 alt／來源／授權欄位只在上傳新圖時保存；單改說明後按儲存會遺失修改 | 隔離後台原始展示圖片已實際重現；一般上傳資產的 UI 同類情境尚需擴測 |
| R3-02 | 原始六筆景點順序被反轉、個別文案標籤統一改為「在地推薦」 | 原始 app.js、目前 Repository／產生器及實際公開列表對照 |
| R3-04 | DB CHECK 接受只有一側座標為 NULL | 真實 yinghua_test 交易已驗證並回滾；API DTO 仍會拒絕此輸入，非已確認的外部越權 |

R3-03 是景點表單新增失敗／競態案例的測試計畫，未將尚未重現的情境列為確定程式錯誤。歷史通過結果保留；當時狀態為「有待修項目」。完整設計及後續結果見 [複查計畫](PHASE-3-REVIEW-PLAN.md)。

## 2026-10-18 修正後驗收

R3-01／02／04 已修正，R3-03 的行為測試已補齊。圖片說明由景點個別保存、共用資產原始來源不變；六筆原型順序與標籤、清單營業時間欄恢復。新增 003 migration（四欄與座標 CHECK），沒有新增端點或業務表。

| 本次修正後實際檢查 | 結果 |
| --- | --- |
| PHPStan level 10／PHPat | 通過 |
| PHPUnit | 19 tests／83 assertions 通過 |
| PER-CS | dry-run 無差異 |
| Rector | 僅 dry-run，14 檔風格建議，未套用 |
| 前端 | 64 頁建置、45 項 Node 測試通過（新增表單九項） |
| 原有 HTTP／MariaDB | 65 項通過，未重跑 Google 實連 |
| 圖片說明／排序回歸 | 21 項真實 HTTP／MariaDB 通過 |
| 座標 DB 約束 | 8 項隔離交易通過、回滾 |
| API 契約 | 9 項通過，44 操作一致 |
| 持久化 | app-test／web-test 重啟後 6 項通過 |
| 實際桌面／手機 UI | 不換圖改說明、儲存、重開讀回一致；清單營業時間表頭正確；公開順序／標籤與手機表單正常 |

新增測試檔、資料語意、CLI 預覽／套用及部署命令見全站導讀與景點／媒體指南。main 與隔離庫均套用 migration；受控標籤補入各六筆，重跑零變更。PHP 更新後已重啟 publisher，避免常駐程序繼續使用舊類別。資料庫及 Git 歷史皆先備份，未改原始 website／Excel。

最新完整公開查詢 EXPLAIN 使用 attraction_admin（ref）且含 filesort；樣本少，不推論大規模效能，未增索引。人工安全審查及越權／唯讀／型別／長度／版本失敗測試通過，不等於執行獨立掃描器。

桌面實際 innerWidth 1745、scrollWidth 1732；截圖 artifacts/phase3-corrected-admin-desktop.png。瀏覽器逾時恢復後，本輪另完成手機公開列表及管理編輯頁複驗：實際 innerWidth 355、scrollWidth 341，無水平溢出，後台側欄收合後表單可見、載入值正確，錯誤記錄為空。截圖 phase3-corrected-public-mobile.png、phase3-corrected-admin-mobile.png，臨時尺寸已還原。原型版型沿用，必要的新欄位及發布流程仍依已確認範圍，不宣稱精確 390 或全站像素一致。P0 後續功能及正式外部條件仍依前述界線處理。
