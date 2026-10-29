# P0 完整驗證：Excel 與原始 website 對照

2026-10-26 最新完整 AA 審查見 [50 項準則表](P0-AA-VERIFICATION-20261026.md)：37 通過、2 不通過、10 不適用、1 已廢止。已補真實 NVDA／反向 Tab／確認框最外側循環、16 受保護頁面的實際 200%／400%、62 次文字間距及 12 個正常樣式直橫向狀態；修正欄位邊界、首頁持續裝飾、英文語言標示及模態循環。159 前端測試通過；剩餘 AA-01／AA-02 是已重現的網站差項。下文是先前輪次，不能再用當時『Windows NVDA 未驗』覆蓋本輪實際證據，也不能宣告整體 AA 通過。

## 2026-10-26 登入後與確認框補驗

依使用者「試／keep」，延續 U-19 的背景隔離驗收；凌晨及下午為不同的 Orca 工作階段。保留 `artifacts/isolated-a11y-followup-20261026/` 與 `artifacts/isolated-a11y-resume-20261026/` 的原始採證、實際畫面和失敗，不加總各份累積語音快照作為案例數。前者的 `admin-login-success.json` 名稱是執行前預期，實際內容為輸入錯位後的拒絕登入，**不列成功**；後續才取得真正登入結果。

| 本輪實際範圍 | 結果與證據界線 |
| --- | --- |
| 會員登入／私人收藏 | 鍵盤登入成功；Orca 讀出收藏頁 heading 1／2、「本頁已載入 0 筆收藏」及空收藏說明。自有景點收藏按鈕變為已收藏，但成功 toast 的自動播報尚未取得證據；實際發布頁具有 status／aria-live，未據此推定朗讀通過 |
| 加入目前行程 | 自有景點加入並保存在本人行程，畫面重新載入仍有 1 個地點；Orca 已讀出重新載入按鈕及「讀取本人行程中…」。不把完成畫面當成每一則動態通知均朗讀 |
| 管理員登入／列表 | 完整輸入後成功到達景點列表；Orca 讀出列表／表格摘要、14 筆結果及頁數。輸入被 Browse mode 快捷鍵攔截時的拒絕登入亦有錯誤語音；該次不是後端登入缺陷 |
| 確認框 | 補上警示角色、modal 與說明關聯，保留取消初始焦點、原生模態、外觀及業務規則。直接瀏覽器確認標題／完整刪除對象說明、Tab 到確認、Escape 返回原刪除按鈕；Orca 可手動讀出標題及兩行完整說明。自動開框仍只先讀取消，未列完全通過 |
| P0 後台表單 | 直接背景瀏覽器實際驗景點、電影、管理員新增：空白送出分別聚焦 place-name、film-title、account；41／17／8 個可見控制項皆有名稱。權限與 SEO 等待真 API 載入後分別驗 46／210 個控制項，皆無未命名或失效描述 ID；受檢桌面無水平溢出。沒有輸入新密碼或新增帳號／內容 |
| 後續功能界線 | 消息／優惠後台新增頁仍為 P1 階段 9 的尚未開放，符合 IMPLEMENTATION-PLAN.md 的既有界線；本輪測試選擇器等待消息表單逾時是驗收清單範圍錯誤，不是 P0 應用缺陷，未提前開發 |

前端修正只在 `frontend/templates/dialog.html`，來源／檔案用途見 [共用導覽指南](modules/frontend-accessibility.md#2026-10-26-確認框語意補驗)；WAI 模式是工程參考，不新增 Excel 業務條件。DOM 可存取關聯、實際語音及人工聽音分開；這次沒有主機音效或 Windows NVDA。確認框 Tab 末端曾量到 BODY，未確認其瀏覽器／文件焦點界線前，不宣稱完整圈選通過。

工具及執行限制：Orca 的 Browse／Focus 是不同操作模式；明確使用 Insert+A 後才能可靠輸入。反向 Tab 在隔離鏈路仍有不一致，沒有用網頁程式補丁掩蓋；無網站腳本的 data URL 診斷被瀏覽器協定政策擋下，未改用其他方式繞過。自動核准審查曾因額度無法完成命令，恢復後才繼續。下午 Docker 引擎原已停止，官方 CLI 恢復後才執行檢查，均不算網站缺陷。

最終重新建置 67 頁、Node 152 項通過；HTTP 第一次有 32 個公開路徑 503，發生於修改及最後建置後、測試發布版尚未再次同步的期間；只對 app-test 執行 content:publish 後，`frontend-integrity-final.json` 記錄 **67 頁／14 資產／81 HTTP／0 issues**。這證明本輪同步後恢復，不追認所有歷史 503 的相同根因。沒有執行主站發布，沒有 API、資料表或 migration 變更，PHP／資料庫回歸仍沿用先前範圍。

本輪沒有將所有後台／會員情境、Windows NVDA、反向 Tab、確認框／收藏通知的完整自動播報或完整人工 AA 列為通過，階段八仍不合併 main／不進入 P1。收尾清理自有 yinghua_test fixture、保留正常稽核，停止隔離桌面並關閉自有分頁；狀態及原始檔完整性另作收尾核對。

## 2026-10-26 隔離讀屏補驗

使用者反映 Windows 前景／NVDA 操作干擾 LOL，依 U-19 改為 E-30 的獨立 Docker 桌面：背景 IAB 的 noVNC → 私有 Xvfb／Firefox → AT-SPI／Orca。沒有使用 Windows 視窗或主機輸入掛鉤，Windows NVDA 程序查核為 0。這是新增驗收工具與準備工作，不是新網站業務功能；`website/`、應用來源、API／DDL／主站發布不變。操作與檔案用途見 [隔離讀屏指南](modules/isolated-accessibility.md)。

| 實測場景 | 真實 Orca 語音證據／判定 |
| --- | --- |
| 首頁 | 載入網站標題；8 個地標、8 個標題；H 結構導航讀到「在城市裡… heading 1」、「老戲院的光… heading 2」與電影區 heading 2 |
| 會員登入 | 載入「會員登入」；4 地標／1 標題／1 表單；「電子郵件 entry required」、「密碼 password text required」；空白送出產生 `alert` 與原生 `Please fill out this field` |
| 註冊表單 | 顯示名稱、電子郵件、密碼 8–128 字元、確認密碼皆有具名及 required 輸出；未輸入密碼、未解 CAPTCHA、未建立帳號 |
| 同意框 | X 結構導航讀到完整開發測試條款／隱私標籤、`check box not checked required`；畫面亦為未勾選，未接受條款 |

原始證據在 ignored `artifacts/isolated-a11y-20261026/`：`home-headings-initial.json`、`member-login-arrival.json`、`member-login-required.json`、`register-fields.json`、`register-checkbox.json` 與 `register-reader.png`。後四份是同一存活工作階段的累積快照，最後 49 筆語音，**不能加總為獨立案例數**。初次首頁檔為前一個容器工作階段。採證擷取 Orca 實際 SPEECH OUTPUT，不是把 DOM 可存取名稱合成朗讀；原始鍵盤 debug 不匯出，沒有主機音效連接，因此沒有宣稱人工聽音品質。

實裝 Orca 48.1／AT-SPI 2.56.2／Firefox 153.5.1esr。執行時採證確認 UID 1000、唯讀根目錄、capabilities 全移除、禁止提權、非 host network、無 devices／主機 X11／音效／Docker socket；只開 `127.0.0.1:6091`，限 0.75 CPU／1 GiB RAM／512 程序及執行緒。Windows NVDA 未重啟。工具初建時 Firefox 曾退出、服務有資源不足日誌；後續觀察存活執行緒數超過原 256，已提高有限配額並排除 zombie 假健康。最終 `run.ps1` 的重建／等待 healthy 流程亦實際通過，沒有停用 seccomp 或新增瀏覽器 sandbox bypass；Firefox user namespace EPERM 仍如實列管，不將此環境警告當網站缺陷。

本輪只補上述公開／認證讀屏場景。noVNC 快速連續鍵出現非預期的焦點／朗讀跳動，故**不以此次連續 Tab 宣告完整循序焦點通過**；前輪直接瀏覽器鍵盤證據保持原範圍。後台／會員登入後的完整讀屏、Windows NVDA 專屬相容性、人工聽音及全站完整 AA 仍未通過，不合併 main／不進入 P1。既有 PHP／API／Node 回歸沿用前輪，本輪未重跑或冒稱新通過。

自有 `yinghua_test` fixture 已由既有 helper 清理，保留正常稽核；沒有主站資料或帳號變更。工具僅供短期本機驗收，完成後停止自有桌面釋放資源，工程文件與驗收工具同一筆交付。

收尾驗證：兩個 shell 在實際容器通過 `bash -n`，PowerShell parser 通過；採證輸出目錄越界、非法標籤、既有證據覆寫三個失敗邊界均被拒絕；文件本機連結與原始交付 69 檔逐一核對。最終 `run.ps1 -Stop` 已實際停止桌面，臨時 fixture 的 cleaned 狀態確認為 true，NVDA 程序 0。新增工具未重跑全部應用套件，也未合併階段分支。

## 2026-10-26 01:20 實際 400% 縮放補驗

接續使用者設定 400% 的確認，完成原 31 個公開／認證固定頁的第二組實際縮放巡查：**DPR=4、468×227 CSS px、html CSS zoom=1**，另保留初始狀態，共 32 次主要觀察；與前一組 200% 合計 64 次。首次確認時高度為 241，逐頁紀錄的實際高度為 227，以紀錄為準，不把視窗尺寸固定推定為同一值。所有受檢頁的整頁水平溢出、文字裁切候選、控制項缺少名稱、失效描述 ID、缺少 img alt 皆為 0。

2026-10-26 原測試分頁已關閉、既有服務已停止，故先恢復原容器及測試頁，再補互動；沒有重建／搬移主庫、改寫業務資料或手動 content:publish。跨週後首頁出現 10.26–11.01 及「目前沒有本週映演場次」，依當週資料顯示，沒有補造原型示範日期。恢復後的互動視窗為 **DPR=4、480×241 CSS px**，與前一組 468×227 分開記錄；被最小化的背景視窗 outerWidth=160 不作比例換算證據。

- 公開手機選單可用 Enter 開啟、連續 Tab 到第五項「旅行計畫」；捲動完成後該項 top=125.4、bottom=181.0，位於 241 高的視窗內。Escape 收合並返回開關；穩定後開關 top=14.5、bottom=49.5、scrollY=0，焦點可見。
- 註冊空表單仍聚焦 displayName，捲動完成後 top=127.5、bottom=178.5，完整在視窗內。下一次 Tab 到 email；241 高的短視窗中 email top=204.7、bottom=255.7，輸入欄下緣部分在視窗外，沒有宣稱整個元件全部可見或把即時平滑捲動位置當成最終結果。沒有輸入密碼、解驗證碼、同意條款或提交新會員。
- 本輪沒有新應用缺陷修正或 PHP／API／DDL 變更，沒有重跑整套單元／HTTP 套件；前輪 Node 152／67 頁完整性沿用其來源與證據，不冒充今天再跑。登入後後台／會員頁的實際縮放仍未納入這 31 頁；保留前輪 320 CSS px／功能驗證的界線。

證據在 `artifacts/p0-a11y-20261025-211402/zoom-reader/`：observations、summary、actions、[400% 選單](../artifacts/p0-a11y-20261025-211402/zoom-reader/menu-400.png)、[選單收合](../artifacts/p0-a11y-20261025-211402/zoom-reader/home-400.png)、[表單 Tab](../artifacts/p0-a11y-20261025-211402/zoom-reader/form-tab-400.png)。原生工具的網址辨識失敗與前次 NVDA 視窗存取授權逾時，不拿來判定網站缺陷，也不繞過工具權限。NVDA 維持停止，目前只詢問使用者何時方便回應工具存取提示，未在等待期間再啟動。**200%／400% 的上述公開頁補驗完成；實際讀屏及完整人工 AA 未完成，不合併 main／不進入 P1。** Docker／正式環境仍依 U-18 另列交接。

## 2026-10-25 22:02 實際 200% 縮放補驗

使用者手動設定 Brave 縮放後，本輪讀取 **devicePixelRatio=2、outerWidth=1872、innerWidth=936、innerHeight=454、html CSS zoom=1**，沒有使用 viewport 覆寫冒充放大。來源 `1f826e4`；本輪只增加驗收紀錄，沒有新應用變更或重跑 PHP／DB／Node 套件。

- 31 個公開／認證固定頁於實際 200% 巡查，另保留初始首頁，合計 32 次主要觀察；整頁水平溢出、控制項缺少名稱、失效描述 ID、img 缺少 alt、受檢元素裁切均為 0。範圍不含登入後後台與會員頁的實際 200%；那些頁面沿用前輪 320 CSS px 與功能驗證，不能相加成所有 50 頁都做過實際縮放。
- 四個電影發布詳情的 main 沒有 tabindex，跳轉後 activeElement 是 BODY，不能單憑這點判定失敗。實測 shape-of-light 的下一次 Tab 已進入 main 的「回到本週映演」；原生錨點的循序焦點起點有效。其餘三筆原始候選保留，不冒稱各自都補按過 Tab。
- 註冊空表單會聚焦 displayName。原生必填提示時欄位下緣部分在視窗外，標籤與游標仍可見；Tab 可移至 email 並捲動，完成後欄位 top=246、bottom=297，位於 454 高的視窗內。第一張即時觀察仍在平滑捲動途中，後續穩定量測另存，沒有把途中位置當成最終遮擋或宣稱初始欄位完整可見。
- NVDA 2026.2 官方簽章 portable 已實際啟動，獨立設定、silence 語音輸出、無 add-on、無統計、INFO 日誌及 no-sr-flag；語音檢視器的原生視窗存取授權逾時，沒有取得可驗證的讀屏輸出，**不算讀屏通過**。已透過官方 --quit 關閉自有 PID 43808，確認停止，未留下背景讀屏工具。

證據：`artifacts/p0-a11y-20261025-211402/zoom-reader/` 的 observations、actions、reader-attempt；[200% 首頁](../artifacts/p0-a11y-20261025-211402/zoom-reader/home-200.png)、[Tab 捲動完成](../artifacts/p0-a11y-20261025-211402/zoom-reader/form-tab-200.png)。已另請使用者調至 400%；本輪末次觀察仍為 DPR=2，因此 **400% 與實際讀屏尚未完成**。不重試原生網址辨識或繞過視窗存取授權；依 U-18，環境差項繼續另列。本輪未合併 main／未開始 P1。

## 2026-10-25 21:40 網站介面補驗

依 U-18，預計交付至已架設的 server；Docker 映像 CVE 與正式主機設定另列部署事項，不作為本次網站功能驗收的阻擋條件。原掃描與風險紀錄保留，沒有因此降低應用 API／session／輸入驗證，亦未推定主機已驗證安全。

本輪補驗來源為 `8a1f8d9` 加下述兩份 CSS 修正；沒有重跑全部 PHP／DB／部署套件或修改原始 website／Excel。

| 項目 | 實際結果 |
| --- | --- |
| 重排與語意 | 50 個固定路由（`/` 與 `/index.html` 算同頁）、78 次 DOM 觀察，主要為 320×844；另有 390／640／1280／1440 封面與桌面圖片區量測。整頁水平溢出、可見控制項缺少名稱、失效描述 ID、缺少 img alt 均為 0。後台另補 12 頁身分／編輯資料／權限載完的觀察，不把 skeleton 當最終頁面。 |
| 鍵盤與錯誤 | 跳至 main、公開選單 Enter／Tab／Escape 與返回焦點、關閉選單略過、後台側欄 Shift+Tab／Escape、註冊與景點必填聚焦、Google 原因／aria-invalid／aria-describedby 與解鎖後聚焦，均實際操作。未代填驗證碼、註冊或改密碼。 |
| 確認框 | 初焦為取消；Escape 未刪資料並回到同筆刪除按鈕。Shift+Tab 一次回傳 BODY，未據此宣告焦點圈選通過或直接判為應用缺陷；需區分瀏覽器 chrome 與頁面底層控制項。 |
| 首頁封面 | 原先 1280 寬時 CTA 底為 760.4、caption 頂為 746.4，確實重疊。phase2.css 將內容納入正常排版並預留底部空間；五組 320×225、390×844、640×450、1280×900、1440×900 皆無 CTA／caption 重疊或整頁溢出。320 CSS px 不冒稱實際 400% 縮放。 |
| 圖片文字 | 保留原圖片與排版，調整深色漸層及 caption 不透明度。館舍 inline background-image 原會覆蓋漸層，現由 museum-photo::after 獨立遮罩。核對 computed style 後，以任意圖片最亮／最暗界限計算 12 類代表樣式：普通文字最低 4.6837、金色大標最低 3.8161、館舍標籤最低 6.4543、圖片 badge 最低 6.0155；愛心圖形以 3:1 計算。僅為穩定狀態及量測區域的對比界限，不是所有動畫影格／hover／輔具的 AA 認證。 |
| 回歸 | Docker Node **152／152**；建置完整性 **67 頁／14 資產／81 HTTP／0 issues**。長海報網址的單行 input 內部捲動單獨辨識，不當作整頁文字被裁掉。 |

證據：`artifacts/p0-a11y-20261025-211402/` 的 observations、admin-loaded、keyboard-actions、hero-layout、image-backgrounds、contrast-bounds、node-tests、frontend-integrity；[桌面封面](../artifacts/p0-a11y-20261025-211402/hero-fixed-desktop.jpg)、[手機封面](../artifacts/p0-a11y-20261025-211402/hero-fixed-mobile.jpg)。初次影像紀錄的兩筆尺寸仍為 320，未充當桌面結果；1440 已另確認實測。工具的等待錯誤、未儲存導頁防護及初次僅算最亮圖片，與最終結果分開；最後 bounds 已同時計算最暗／最亮圖片。

自有 fixture `p8ui27af937f` 已清理、帳號停用／session 撤銷，正常 audit 保留，未改主庫業務資料。實際瀏覽器縮放正在協助將測試分頁置前；NVDA 官方簽章套件及獨立 portable copy 已準備，未啟動／未算讀屏通過。完整人工 AA 不以 DOM 名稱檢查或對比數值替代。本輪未合併 main／未進入 P1。

## 2026-10-25 20:15 修正後結果

依使用者「有差異那你就修改」完成 V-01／V-02／V-03。下方保留修正前的失敗與 14:12–15:21 驗證，不改寫成當時已通過。

| 差項 | 修正與實際驗證 |
| --- | --- |
| V-01 | style.css 的 43 處中性色前景共用 `--muted:#5b5f55`，淺底眉題使用既有深綠，深色區側註提高不透明度；背景、字型、字級及主版型不變。50 固定頁桌面／手機、非空會員內容及四表單 320／390，共 117 次觀察、5,105 個可計算文字樣本，低對比候選 0；muted 最低 **4.967:1**。108 個圖片／漸層／複合透明度樣本未自動判定，不等於全站 AA 證明。 |
| V-02 | build 依固定路由恢復四個景點／電影表單的上方返回列表，四頁實際返回成功。管理員表單仍查看網站；沒有 view 的帳號沿既有可用去向，不導向無權清單。320／390 頂列分行，頁名不直排；8 組頂列子項均在容器內，表單 panel 的 scrollWidth 等於 clientWidth，無局部裁切。 |
| V-03 | 解析用 `mapLookupUrl` 與保存用 `mapUrl` 分開。共用 formErrors 支援獨立欄位群組，將解析 API 錯誤映射至查詢欄，以純文字、aria-invalid／aria-describedby 呈現，解除 busy 後聚焦。重試不清除其他區塊錯誤；保存網址錯誤不誤指查詢欄。真非法網址顯示原因，真 Google 成功重試清除錯誤，有候選但未自動保存。 |

前端 **152 項測試通過**，含解析拒絕／重試／保存欄位分流，以及實際建置的四個返回標籤、管理員原入口、create-only 去向；建置 67 頁，本輪完整性 67 頁／14 資產／81 HTTP、0 issues。沒有 PHP／API 契約／DDL／Docker 設定變更，沒有將上輪全套 PHP／DB／部署冒充本輪重跑。

本輪證據：`artifacts/p0-ui-fixes-20261025-195151/` 的 browser-observations、contrast-results、responsive-layout、browser-actions、node-complete、frontend-integrity。首版色值在較深底色仍有兩個候選，48 次觀察另保留 browser-first-palette；最終色值重新量測，不抹掉首次結果。代表畫面：[桌面修正](../artifacts/p0-ui-fixes-20261025-195151/fixed-place-form-desktop.jpg)、[320px 返回與欄位](../artifacts/p0-ui-fixes-20261025-195151/place-return-320.jpg)、[非空行程](../artifacts/p0-ui-fixes-20261025-195151/planner-contrast-mobile.jpg)。

具名隔離 fixture `p8ui5edd443f` 已清理、帳號停用／session 撤銷，正常 audit 保留，未改正式 root／主庫業務資料。只做前端建置，未另手動執行主 app 的 content:publish；該步驟被自動核准審查以主環境發布授權不足拒絕，未繞過。此次共用資產與固定後台頁修正不需手動重發公開業務內容。

**三類差項已關閉本機修正範圍；人工讀屏、實際 200%／400% 縮放、複雜背景、正式環境與映像安全待辦仍保留，不合併 main／不宣告 P0 全面通過。** 以下為修正前原始驗證。

日期：2026-10-25；主要測試時間台北 14:12–15:21；應用來源 `343bd7ec9035cc5ca2ca6345644c0ef46b6649b3`。**P0 功能已實作，但本輪仍不能判定全部驗收通過，也不能說介面與原型完全相同。** 已確認文字對比、景點／電影表單返回動線、Google 網址欄位錯誤關聯三類差項。本輪未修改應用、原型、DDL 或環境，未合併 main／進入 P1。

基準為 [原始 Excel](../映畫竹塹_後台工程串接功能規格.xlsx) 及 [website](../website/)。Excel SHA-256：`d445ae7f8478b10fc938f91d9bcf85802dc48bcc3487dd01664ae06cfbaeeef5`。完整讀取七工作表，以「功能規格」J 欄 **16 列 P0**，連同串接總覽 B7:B15、SEO 規格及資料表建議核對；B17 是建議順序，不能覆蓋逐列優先級與 U-08。F 欄清單不直接等於必填。

## 確定差項

| 編號 | 重現、依據與影響 | 處理方向 |
| --- | --- | --- |
| V-01 | **一般文字對比未達 AA。** 詳情 `.detail-lead` 為 `#74766c`／`#f5f1e8`，16px，約 **4.094:1**；`.detail-meta` 為 `#8b887c`／同背景，約 **3.152:1**；列表 `.place-area` 為 `#858477`／`#f9f7f0`，約 **3.521:1**；後台表頭為 `#858477`／`#fbfaf5`，約 **3.611:1**。這些一般文字需 4.5:1。來源：G34、串接總覽 B15；[style.css](../frontend/assets/css/style.css) 與模組樣式。 | 補齊詳情、列表及後台相關文字色並逐背景核對。原型也有低對比，不能因照原型豁免 Excel AA；前輪三類小字修正仍有效，但範圍不完整。 |
| V-02 | **四個內容表單的頂列動線不同。** 原型 place-add／place-edit 為「← 返回景點與店家」，film-add／film-edit 為「← 返回本週映演」；現行 [admin-topbar.html](../frontend/templates/admin-topbar.html) 都是「查看網站 ↗」至首頁。底部取消仍能回列表。來源：原型四頁＋U-05，非 Excel 新業務規則。 | 依模組恢復上方返回。管理員新增／編輯原型本來就是查看網站，不列為差項。 |
| V-03 | **Google 網址錯誤未關聯欄位。** `https://example.com/not-google` 被伺服器拒絕且不保存，但前端只顯示「請檢查輸入內容」，沒有具體欄位錯誤。`GoogleUrlPolicy` 回傳 `fieldErrors.mapUrl`，而 [attraction-form.js](../frontend/assets/js/pages/attraction-form.js) 的解析 catch 只呼叫 `handleAdminError`，輸入欄亦缺少可供此錯誤對應的 name／data-field-name。來源：E34／G34、B15；G20／L20 的非法網域拒絕本身通過。 | 將解析網址輸入與 mapUrl 錯誤對應，顯示原因並建立可及性關聯。不是 SSRF 防護失效。 |

文字測量共產生 1,387 個低對比候選樣本，含原型／現行、不同尺寸及重複文字，**不是 1,387 個獨立缺陷**。上表是已核對的代表性失敗；品牌／停用例外與圖片、漸層、複合透明度不能直接由此判為通過。門檻依 [W3C Contrast Minimum](https://www.w3.org/WAI/WCAG21/Understanding/contrast-minimum.html)。

## 16 列 P0 逐項結果

「功能通過」限下列本機成功／拒絕流程；各列仍共同受 G34、上述差項及正式環境限制約束，不等於全面 AA／正式交付合格。

| 功能規格來源 | 本輪證據與結果 | 差項／界線 |
| --- | --- | --- |
| E5／G5／L5 首頁 | 聚合、公開／排程、精選／消息同步、空態／圖片替代；桌面手機原型對照，功能通過。 | 正式素材／內容待提供。 |
| E6／G6／L6 館舍 | 電話／網址、更新日、地址／交通／外連與週表刷新；電影頁參觀區同源，功能通過。 | V-01。 |
| E7／G7／L7 本週片單 | 真 DB 當週兩場次，海報／12:00／18:00、列表→場次介紹同源；草稿／週界排除，功能通過。 | V-01；舊示範日期缺年份未自行補年。 |
| E9／G9／L9 消息 | 白名單、全篇／分頁、日期／草稿、優惠隔離；GUI 分類、上一頁及重整，功能通過。 | 完整管理／前後篇留 P1。 |
| E11／G11／L11 優惠 | 有效期、店家／優惠狀態、草稿／過期排除、獨立詳情；本輪首次執行通過。 | V-01；歷史未捕捉完整診斷的 503 不追認根因。 |
| E13／G13／L13 景點美食 | 分類／區域／關鍵字、空結果、slug／座標／公開狀態；新發布內容與會員收藏／加入／載回／移除，功能通過。 | U-11 摘要／介紹選填；自動補位延後，完整旅程留 P1。 |
| E16／G16／L16 註冊 | 真伺服器驗證碼、重複 Email、過期／錯碼／重用／限速、條款版本、確認密碼拒絕；GUI 空表單聚焦必填，測試通過。 | GUI 未代解 CAPTCHA／同意條款提交新帳號，不冒稱本輪人工正向註冊通過。 |
| E17／G17／L17 認證 | 兩身分登入／登出、CSRF／rotation、期限／撤銷／停權／限速、reset 重用與 SMTP 失敗；GUI 無效訊息及正向登入／登出通過。 | 本機 Mailpit 不等於正式 SMTP。GUI 未改密碼；正反向變更由真 DB／HTTP 測試。 |
| E19／G19／L19 景點清單 | 搜尋／狀態／分頁、獨立編輯、軟刪／引用保護、version／audit、越權拒絕，功能通過。 | GUI 未執行永久刪除；隔離 API 驗刪除／引用拒絕。 |
| E20／G20／L20 景點表單 | 既有試填／人工週表；一次真 Google 搜尋、確認帶入、人工改名稱／時間／解除關聯；選檔上傳 320×240 PNG＋alt，空摘要介紹發布／重開／公開詳情核對通過。 | V-02／V-03。真 Google 只驗一個公開地點；SSRF／重導向／非公開 IP 由隔離測試覆蓋。 |
| E21／G21／L21 電影管理 | alt／slug／交易／版本／權限；GUI 第二場改同日 12:00 被拒，欄位保留並標錯，改回 18:00 保存通過。 | V-02；U-16 只驗日期＋開始時間，結束時間／場地延後。 |
| E26／G26／L26 管理員 | root PUT／DELETE 403、最後管理權、U-13 一般管理員自我操作、audit／持續授權，API 通過；新增／編輯版型比對。 | GUI 未輸入新憑證／新增帳號或移除正常帳號。 |
| E27／G27／L27 權限 | 真 DB 模組動作／範本、持續與下個請求生效、防越級；GUI root 40 格停用、唯讀模擬 38 個表單控制項停用、退出恢復本人，通過。 | GUI 未修改一般帳號權限。 |
| E30／G30／L30；SEO E5:E9／E15:E17 | 無 JS head 74 HTML、title／description／canonical／OG／Twitter／favicon／noindex；真保存覆寫後分類／上一頁／重整仍保持，本機通過。 | 正式 HTTPS 網域／匿名分享圖與平台抓取待驗；本機 localhost 不冒充正式 canonical。 |
| E32／G32／L32 SEO 設定 | view/edit、HTTPS／robots、私人頁 noindex、409／同版／audit／公開白名單；API 保存後 GUI 驗分類不蓋覆寫，通過。 | 本輪 GUI 讀取／展開設定，保存由隔離 HTTP 完成；版本 0 的殘存覆寫未充當本輪保存證據，另建具名 fixture 後驗證。 |
| E34／G34／L34；B15 共用安全／AA | health／DB 故障、輸入／輸出／CSP、真 DB 越權；鍵盤、手機選單 Escape／焦點返回、320 CSS px 重排與文字測量。 | **整體界線未過：V-01／V-03；人工讀屏／實際縮放與安全／正式差項未關閉。** |

## 原型介面與交互覆蓋

原始 website 有 **57 HTML**，現行 **67 固定來源頁**。本輪比對 **40 個有原型對應的頁面 × 桌面／手機＝80 組成對狀態**，另驗新增認證、政策與收藏頁；不是所有 67 頁的逐像素／逐狀態驗收。

| 組別 | 桌面與手機成對比對頁面 |
| --- | --- |
| 公開主題 11 | index、museum、cinema、explore、food、events、activities、city-news、offers、login、register |
| 景點詳情 5 | dongmen-market、hsinchu-park、or-arts、or-coffee、shanfang |
| 電影詳情 4 | island-slow、window-sunday、wind-city、shape-of-light |
| 消息詳情 4 | slow-market、street-photo、park-concert、old-town-notice |
| 優惠詳情 3 | autumn-coffee、or-crafts、shanfang |
| 後台 12 | index、places、place-add、place-edit、films、film-add、film-edit、admins、admin-add、admin-edit、permissions、settings |
| 會員行程 1 | planner；只驗目前行程保存。原型日期／名稱／排序／推薦套用留完整旅程階段 |

目標尺寸：桌面 1440×900、手機 390×844。Brave localhost 縮放／捲軸使少數實際 CSS 寬為 1438–1440 或 389–390，原始數值保留。另驗現行 320×844 的主題頁、四種詳情代表、全部 P0 後台表單與認證／會員／政策頁；未見整頁水平溢出，局部表格捲動另辨。**320 CSS px 不等於已實測 200% 文字放大／400% 瀏覽器縮放**，見 [W3C Reflow](https://www.w3.org/WAI/WCAG21/Understanding/reflow.html)。管理員更改密碼頁本輪為桌面／320，未另補 390；其餘新增頁有桌面／390／320 證據。

共記錄 242 次 DOM／樣式觀察，含載入中、重複及合成狀態，並非 242 個不同頁面通過。後台採資料載完的表單結果；原型 `favorites.html` 不存在，其 404 排除成對結果。十個新增固定頁為收藏、密碼輔助／管理登入／政策頁，依已記錄的認證與 E-27／E-28 設計。

### 有來源的差異與一致部分

- 公開頁保留字體、紙色、導覽、封面／內文、卡片與電影橫列的主要版型。從列表實際進電影介紹，維持原型封面＋介紹＋場次＋返回的結構；多場次列出為 E21／U-16。不宣稱逐像素相同。
- 卡片數／日期／排序依真 DB 的公開、排程、當週與有效期判斷，不照搬靜態示範。館舍週表同源是 U-09，取代舊寫死兩行資訊。
- Google 關聯／原文參考為 U-10；摘要介紹選填為 U-11；媒體、alt、狀態、slug、SEO 欄位為 S 及已列 E。表單因此較高，不能一概算成任意改版。
- 確認密碼依 U-15 必填、條款依 G16、root 模擬依 U-06 唯讀；一般管理員不顯示 root 模擬選單正確。
- 消息／優惠詳情「繼續閱讀／前後篇」、推薦路線、完整會員旅程、消息優惠管理及營運儀表板仍屬 P1／P2，未以原型模擬 CRUD 冒充完成。
- 原型首頁「認識新竹市影像博物館」雖 href 帶 anchor，JS 實際導向 explore，現行一致，未誤列為缺陷。
- 原型與 IAB 捲軸模式不同約有 15px 內容寬差，縮放四捨五入約 1–2px；新增重載操作也會改變 flex 標題容器寬。沒有直接當成設計缺陷；V-02 為實際操作目標不同，獨立列出。

代表證據：[電影列表](../artifacts/p0-complete-20261025-141244/current-cinema-desktop-with-screenings.jpg)、[電影詳情手機](../artifacts/p0-complete-20261025-141244/current-film-detail-mobile.jpg)、[原型電影詳情](../artifacts/p0-complete-20261025-141244/original-film-detail-desktop.jpg)、[root 唯讀](../artifacts/p0-complete-20261025-141244/current-root-preview-readonly.jpg)、[現行景點表單](../artifacts/p0-complete-20261025-141244/current-place-add-desktop.jpg)、[原型景點表單](../artifacts/p0-complete-20261025-141244/original-place-add-desktop.jpg)。合成資料已清理；部分原型截圖有鍵盤測試的 skip-link 焦點，不是版面多出按鈕。截圖重繪延遲以 DOM 尺寸／穩定後畫面交叉核對。

## 本輪重新執行的測試

| 檢查 | 實際結果 |
| --- | --- |
| PHP／架構／格式 | PHPUnit **91 tests／318 assertions**；PHPStan level 10／PHPat 通過；PER-CS dry-run **261 檔／0 變更**。 |
| 前端／運維單元 | Node **150 項**、host unittest **4 項**通過。 |
| Rector | dry-run **40 檔建議／exit 2**，未套用，不當成零差異。 |
| 既有整合 26 組 | 其中 21 組獨立契約驗證 **788 回應／260 成功輸入／54 個直接觀察操作／0 契約錯誤**，另有消息、優惠、首頁、公開安全與原型資料測試。54 不是整份 OpenAPI 的操作數。 |
| 會員／SEO 5 組 | 會員 3 組 **467 checks／219 contracts**、5 輪發布；SEO 設定 **74／32**、發布 **60／12**；政策 13；無 JS head **74 HTML／0 issues**。各套件口徑不任意相加。 |
| 共用安全／發布 | health **18 項**、6 CLI／6 HTTP 契約；CSRF 100 輪皆預期 422；真 DB alias 順序 7／安全診斷 8／SMTP 4 項通過。 |
| 資料持續性 | 會員／SEO／電影／公開內容 schema 空庫、升級／重跑／rollback；會員重建 17／7 契約、SEO 重建 4、內容重建 9、全新 seed 通過。 |
| 完整性 | **67 固定頁／14 資產／81 HTTP／0 issues**，不取代實際畫面驗收。 |
| 隔離部署復原 | 新建 source 343bd7e 的 a／b immutable release；TLS 冷部署 **17**、真 DB／媒體還原 **25**、回退 **9** 項通過，含故障／拒絕／撤銷及媒體 hash。 |
| Composer | 應用 audit --no-dev：0 已知公告／abandoned。 |

共用 DB 的 API 套件順序執行；品質／獨立 production projects 可平行。本輪 API 首次執行均通過，沒有反覆跑到全綠；selector／等待／量測腳本錯誤與應用失敗分開。這輪正常不證明歷史偶發事件根因已解決。

## 資料表與架構

清理後查核主／測試 MariaDB 均 **33 表／316 欄／18 migration**，OpenAPI **72 操作**、前端 **67 固定頁**。原始 website＋Excel **69 檔完整保留**，舊 migration 未改。表／欄與限制逐項見 [DATABASE-SPECIFICATION-MAP](DATABASE-SPECIFICATION-MAP.md)、[SPECIFICATION-ALIGNMENT](SPECIFICATION-ALIGNMENT.md)。

資料表建議 A2 明示草案，不為同名強加 DDL。`publish_at` 為排程、`opening_hours` JSON 為 U-09，NOT NULL／空字串不等同使用者必填；film_screenings 無 ends_at／venue 是 U-16；routes 僅引用保護前置，完整 P1 未做。唯一／座標／外鍵／本人授權／交易有真 DB 測試，沒有從 F 欄自行推導新發布門檻。

仍為前端 HTML View＋PHP API Controller → Service → Repository、DTO／enum／建構子依賴與 Adapter／共用規則；DB 唯一來源，HTML／API 同版發布。Docker、端口與環境變數本輪不變。檔案功能／操作見 [PROJECT-GUIDE](PROJECT-GUIDE.md)；本輪新增文件只負責驗證追溯，不新增業務功能。

## 安全及尚未完成的驗收

- **Semgrep 1.182.0**，官方規則 commit `16b9e6d45f10649f6a531ec4838ed9924372c3bc`：300 檔／256 執行規則／**121 命中**（100 WARNING／9 ERROR／12 INFO）。逐筆判讀為 78 邊界核對、18 heuristic 不符、21 相容性選擇、1 工程選擇、3 強化待辦。未改來源比對 Git 正規化換行後內容，再沿用先前邊界說明；有改的 renderer／SEO／navigation 另讀本輪來源。
- **1 個 PartialParsing warning**：`frontend/scripts/publish.mjs:115` 的 regex delimiter 沒有完整解析。Node 執行／發布與人工 escaping 核對補充證據，不等於 scanner 全覆蓋；沒有 suppress 或修改來源消去警告。
- **Gitleaks 8.30.1**：全可達 refs、離線遮蔽 **18 命中／8 個唯一檔案行位置**，均為第三方 security-audit 教學範例，與前輪 rule／file／line 集合相同；應用路徑 0 命中，未對外驗 key，不保證從未存在秘密。
- **Trivy 0.76.1**：新 app `302d8e34…` **77 HIGH／3 CRITICAL**，web `361f11ac…` 0／0，同 DB `5d313c7a…` **25 HIGH／1 CRITICAL**；三者秘密命中 0。app 無 scanner 當前可修版本，DB 所列 26 個修補為 gosu 的 Go metadata，不代表官方二進位已修；保留 exit 1。
- 本輪再查 Debian：SQLite CVE-2025-7458 bookworm 仍 vulnerable／no-dsa；libxml2 CVE-2026-6653 仍 vulnerable／postponed；zlib CVE-2023-45853 有 contrib/minizip 未建入的 vendor 判定。不能統稱全為誤報。官方：[SQLite](https://security-tracker.debian.org/tracker/CVE-2025-7458)、[libxml2](https://security-tracker.debian.org/tracker/CVE-2026-6653)、[zlib](https://security-tracker.debian.org/tracker/CVE-2023-45853)；完整暴露限制見 [安全報告](PHASE-8-SECURITY-REVIEW.md)。

未完成：全站人工讀屏／實際 200%／400% 縮放／複雜背景全面 AA；正式 HTTPS 網域、可信 TLS、SMTP、Google 配額、正式政策／素材授權與分享抓圖；實際主機備份 ACL／加密／異地保留及 RPO／RTO；映像 CVE 與兩個 root master／一處 build pipeline 強化。a／b 為同一應用 commit 的兩個 release label，回退通過不證明任意歷史 schema 相容。

## 清理與證據

具名 `p8ui6629e2f1` 的新景點／電影／media 列與圖片檔、收藏／行程已清理；測試管理員／會員停用及撤銷 session，正常 audit 留存。隔離 root 原 password hash／must_change 已復原，root UI session 撤銷，未改正式 admin。SEO **site_settings 及 content_seo 兩表**逐列復原並重新發布；最後 8080／8081 health 均 200。

本輪三個 project `spl-p0-complete-141244`／`spl-p0-restore-3d82f8`／`spl-p0-pubfail-d3b713` 的 12 容器與專用 volumes，核對 label／config 路徑及主庫排除後移除。主庫 container ID 與 yinghua_db_data 保持，其他服務未動。原型預覽 8092 自有 PID 核對命令後停止，瀏覽器尺寸已 reset。

本機忽略證據目錄：`artifacts/p0-complete-20261025-141244/`。入口：scope／excel-all-cells／p0-source-rows、api-results／quality-results／inventory-results、browser-observations／browser-actions／layout-differences／contrast-results、production、static-scan/triage、image-scan/summary、final-check JSON。`previous-evidence/` 190 個舊檔只是歷史備份，未當成本輪重跑。私有 fixture、.env、備份、帳密與私鑰不對外提交。

後續先關閉 V-01／V-03／V-02，再驗影響頁面與完整人工 AA，最後補正式／安全門檻。通過前保留 phase/08，不將「功能已實作」寫成 P0 全面通過。
