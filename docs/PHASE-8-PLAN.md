# 階段八：P0 整合驗收與部署基線計畫

設計日期：2026-10-24。**本文件只有規劃，尚未授權實作／建立階段八分支，也沒有完成驗收或正式部署。** 後續實作從當時最新 main 建立 `phase/08-p0-validation`。原始 Excel／website 不修改，新工程文件放 docs。

目前基線為階段一至七的本機成果：71 個 API 操作、67 固定頁、33 表／316 欄／18 migration。階段七合併點為 `c34ff11`；本次規劃不改 backend／frontend／Docker／資料庫。較早階段的通過證據保留，不能直接改日期充當本輪重驗。

## 1. 目標、來源與完成界線

本階段將已交付 P0 串成可重現的驗收結果，補健康檢查，準備正式部署設定與備份／還原／回退流程。新設計標 **E-29**；S 為 Excel 明文、D 為建議／草案、U 為使用者指定。具體 HTTP 狀態、類別、檔名、逾時與部署做法均屬工程設計，不改寫客戶的業務必填規則。

| 來源 | 本階段責任 |
| --- | --- |
| S：功能規格 J5:J34 中的16筆 P0；相應 E／G／L 欄 | 按每列的流程、限制、驗收逐項核對，不用串接總覽 B17 的「建議上線順序」覆蓋逐列優先級 |
| S：功能規格 E34:H34／L34、串接總覽 B9:B15 | 鍵盤／讀屏／RWD、表單錯誤、安全與健康監控；重驗 TLS／Cookie、授權、輸入及輸出邊界 |
| D：API草案 C43:H43 | 補 GET /api/v1/health；F43 建議成功含 status／version，G43 要求不暴露機密或內部診斷 |
| S：功能規格 G30／L30、G32／L32、SEO規格 E15:E17 | 正式 HTTPS canonical、分享圖與設定同步，保護頁 noindex，無 JS 仍有 head；完整索引／JSON-LD 仍依階段十二 |
| U-01／U-02／U-05、U-08 至 U-17 | PHP API-only 分層、MariaDB／Docker、原型互動、必要收藏／行程保存、既定欄位／密碼／掃描／場次政策及本機 DB 查看方式 |
| E-29 | 發布版封裝、獨立正式 Compose、最小健康資訊、隔離災難復原與操作指南；Excel 未指定雲端、Linux 發行版、RPO／RTO 或備份保留天數 |

成果分列「本機功能驗收」「部署設定隔離演練」「正式環境待驗」。正式主機／網域／SMTP／業主文案未提供時仍可完成不受影響的程式與演練，但相應列維持未驗證，不宣告正式上線或整個 P0 全部通過。依既定規則完成 P0 界線驗收後才進 P1；本計畫不授權階段九。

不提前實作消息／優惠後台 CRUD、前後篇、推薦路線、完整多份會員旅程、全站搜尋、會員查詢、一般設定或營運儀表板。既有消息／優惠維持受控 CLI 維護；未開放頁面／按鈕必須誠實提示。

## 2. 執行順序

| 子步驟 | 工作與交付 | 通過條件 |
| --- | --- | --- |
| 8A 既有穩定性與 P0 差項盤點 | 先固定診斷資料、還原註冊／CSRF 與非預期發布拒絕；對照16列建立缺口清單，按根因修正及補行為回歸 | 失敗可被解釋；修正有針對性證據，沒有用重跑到成功或忽略403／503掩蓋問題 |
| 8B 健康檢查 | 一個匿名唯讀 API、DTO／分層、DB／schema 失敗邊界與契約 | 正常200、DB／schema故障503，無 Cookie／敏感診斷，HEAD／405正確，沒有依賴完整認證組裝才能回健康狀態 |
| 8C 發布版與部署基線 | 正式映像／Compose／TLS與Cookie／服務排程／日誌；以獨立 Docker project 演練 | 來源與依賴在映像內，主測試隔離，未帶開發工具／測試服務／來源掛載，冷啟動及受控升級成立 |
| 8D 備份、還原與回退 | DB＋媒體＋版本清單與還原驗證、失敗不開流量 | 新隔離 volume 確實還原資料／圖檔並重新發布；回退不復活下架內容，主庫與既有 volume 不受演練影響 |
| 8E 整合驗收與交付 | 16列來源矩陣、實際介面／無障礙／API／DB／SEO回歸、操作指南與全站導讀 | 固定清單逐項有本輪結果，缺陷及外部差項完整，清理 fixture，備份歷史後按規則合併 |

8A 的診斷先做；8B–8D 各自可驗證後，再做8E整合。共享 yinghua_test 的寫入及故障測試依序執行，避免測試互相修改發布版本。沒有新證據時不反覆跑整站找問題。

### 8A 優先診斷的既有問題

[階段六驗收](PHASE-6-ACCEPTANCE.md)／[階段七驗收](PHASE-7-ACCEPTANCE.md) 已記錄偶發 CSRF_INVALID、舊測試會員註冊失敗及先前非預期 PUBLICATION_PENDING。階段七有一次直接執行舊會員工具失敗且未捕捉回應碼，不能把該次原因直接寫成 CSRF，也不能把後續通過當成根因已消除。

1. 保留失敗的 requestId、HTTP code、應用 error code、操作順序、是否使用單次 CSRF 更新、當時發布／資料版本；先修補測試觀測缺口，不記錄密碼、Email、Cookie、原始 token、完整 body 或 API Key。
2. 對 CSRF 的來源拒絕、新匿名 Cookie、期限／未來值及簽章不符分別建立受控測試；核對 PHP／容器時間跳動、同秒邊界與並行請求／Cookie 輪替。這些是調查假設，現在未確認根因。
3. 對發布拒絕核對 transaction commit、鎖順序、內容／SEO／rendererVersion／generation、worker 重試與 API 讀取時機；區分真正過渡503與錯誤拒絕，不修改「失敗不能供應過期公開資料」原則。
4. E-29 驗證初案：每種可重現邊界有固定案例，再以預先固定的100輪短流程觀察與分類。輪數是工程測試配置，不能保證沒有未知缺陷；首次失敗保留證據，修正後才重跑受影響範圍。不自動重試網路／業務錯誤，CSRF 仍最多沿現有明確拒絕更新一次。

若仍無法解釋正常流程的非預期拒絕，8A 保持未通過；不能將其改列外部條件來結案。

## 3. 全部16項 P0 的驗收對照

每列均要看原始工作表的 E／G／L 內容與原始 website；下表為驗收安排，未勾選通過。欄位政策沿 [規格校正](SPECIFICATION-ALIGNMENT.md)、使用者指定及 [資料表對照](DATABASE-SPECIFICATION-MAP.md)，不因 F 欄列出欄位就新增 required。

| 功能規格儲存格 | 功能／既有頁面 | 本輪必須證明 |
| --- | --- | --- |
| E5／G5／L5 | 首頁 index | 館舍／景點／電影／消息與成功公開快照同步；空、失敗及圖片失敗替代狀態 |
| E6／G6／L6 | 博物館 museum | 地址、電話、網址、開放時間／交通／最後更新資訊；桌面手機一致，維護後更新 |
| E7／G7／L7 | 本週電影 cinema、films/* | 海報alt／類型／場次／週界與跨日、零／多場、草稿不公開；不猜原始電影缺少的年份 |
| E9／G9／L9 | events／activities／city-news | 全部文章可達、分類／分頁與空狀態正確，無優惠混入；保留原型列表導覽 |
| E11／G11／L11 | offers、基本詳情 | 有效期間、日期邊界、店家狀態、草稿排除；優惠與消息分離，基本閱讀可直接開啟 |
| E13／G13／L13＋U-08 | explore／food／places/*、favorites／planner | 分類／區域／關鍵字與發布篩選、獨立詳情；收藏／加入目前行程需登入並保存本人資料，重登載回，跨會員隔離 |
| E16／G16／L16 | register、terms／privacy | Email唯一、八字政策及必填確認一致（U-13／U-15）、驗證碼限時一次性／限次／限速、同意版本、欄位錯誤；正式條款仍待業主 |
| E17／G17／L17＋串接總覽B9 | 會員與管理員登入／登出、忘記／重設／更改密碼 | session輪替／期限／撤銷／停權、兩種身分隔離、CSRF、鎖定、重設一次性、SMTP失敗；登入失敗不揭露帳號存在 |
| E19／G19／L19 | admin/places | 清單／搜尋／狀態／獨立編輯／刪除確認；軟刪與真實路線引用保護、授權／audit |
| E20／G20／L20 | admin/place-add／place-edit | 既有範例試填、人工欄位、Google完整／短網址候選、SSRF／轉址／失敗不自存、手動修正與確認、結構營業時間／參考簡介；不啟用延後的文字自動補位 |
| E21／G21／L21＋U-16 | admin/films／film-add／film-edit | 電影／海報／場次CRUD、並行與交易、前台照片／日期同步；只查同日期與開始時間重複，結束時間／場地仍延後 |
| E26／G26／L26 | admin/admins／admin-add／admin-edit | 固定admin／root不可改刪、最後管理權保護、其他帳號依既定權限可操作；無刪除專頁或假成功 |
| E27／G27／L27 | admin/permissions、唯讀模擬 | 個人矩陣保存、刷新／下個請求生效、範本只是起始值、API真驗權、模擬不能寫入；保留既定防越級策略的工程標記 |
| E30／G30／L30 | 已交付公開固定頁／所有生成詳情與分頁 | 無JS仍有唯一title／摘要與分享標籤、正確圖片／favicon、正式HTTPS自指canonical、追蹤參數正規化、保護頁noindex |
| E32／G32／L32 | admin/settings SEO | 全站／逐頁／範本／單筆、清除繼承、版本409、權限／唯讀、audit、跨登入保存、公開API與HTML同版、故障待發布及復原 |
| E34:H34／G34／L34＋串接總覽B15 | 上述完整流程＋health | 語意／鍵盤／讀屏、焦點／錯誤／對比／縮放、context escaping／CSP、401／403／404／409／422／429／503正確，健康檢查無機密 |

媒體上傳雖非另列 P0 功能，仍依串接總覽 B12、功能規格 G7／G21 與 U-14 重驗：真圖片內容檢查／重編碼、MIME／大小／alt、私有草稿與跨模組授權、公開引用；不將既定「內容掃描」擴張成已裝病毒掃描服務。

## 4. 預計新增 API、資料表與前端影響

| 項目 | 規劃 |
| --- | --- |
| 新API | **GET `/api/v1/health`，1個操作**；沿既有GET支援HEAD，不另算操作；匿名、無CSRF／session依賴，唯讀且不發Cookie |
| 成功200 | `{status:"ok", version:"<應用發布標籤>", requestId:"..."}`；version不是PHP／MariaDB版本、映像digest或完整內部診斷 |
| 失敗503 | `{status:"unavailable", version:"...", code:"SERVICE_UNAVAILABLE", message:"服務暫時無法使用。", fieldErrors:{}, requestId:"..."}`；固定安全摘要，統一錯誤欄位；精確schema於實作更新OpenAPI |
| 方法／標頭 | GET／HEAD同狀態，HEAD無body；其他方法405及Allow；no-store、JSON與requestId。只匹配確切路徑，不能以health前綴繞過其他API授權 |
| 檢查範圍 | PHP回應、核心設定有效（含既有APP_KEY／正式Origin與Secure檢查）、DB連線／最小查詢、發布版所需migration存在且checksum相容；設定／DB不可用／schema未就緒回503，不自動migration／seed／修復資料 |
| 不納入health | SMTP寄信、Google付費呼叫、完整公開頁渲染及業務資料完整性。這些另以整合／發布就緒探測，不每次健康請求都呼叫外部服務 |
| 新表／migration | **預計0張／0份**；沿用33表／18份。system_logs出現在規格欄位不代表本階段必須新增同名業務表；沿既有安全日誌，稽核查詢介面留階段十四 |
| 新HTML | **預計0頁**；health只回JSON，固定頁仍67。已實作頁只依驗收缺陷修正，不重排整站或提前開放P1／P2 |
| 完成後預估數量 | 72個操作／67固定頁／33表／18 migration；若實際缺陷必須改schema，另以有來源的新migration記錄，不改舊檔、不先宣稱數量成立 |

### Health 的分層與入口調整

擬採 `HealthController → ApplicationHealth → DatabaseHealthProbe`，結果為明確 `HealthReport`／有限健康狀態；version從唯一應用設定取得。DB probe屬基礎設施Adapter，連線與最小SQL不放Controller／前端；只有確實需要替換與測試時才加interface，不引入Actuator、ORM或通用監控框架。

目前 `public/index.php → Composition::kernel()` 在路由執行前就 `Database::connect()`。健康路由若直接加在一般 Controller 中，DB故障會先被外層轉成500，無法正確回503。實作須將**確切health路由的組裝提前至強制DB連線之前**，由probe內處理連線失敗；沿共用Request／Response／錯誤及方法語意。若需小型health入口adapter則限於該路由，普通API維持原本session／CSRF／授權管線，不為health大改全套依賴注入。

E-29初始預算：DB連線／查詢各2秒、health端到端5秒內結束，實作時驗證PDO驅動及FPM的實際效果。SQL只做最小連通與一次migration清單比較，不掃整庫／逐表N+1。PHP／上游完全停止時由Nginx回非200，不偽稱PHP仍能回JSON。內部容器存活、API核心就緒、公開發布就緒是三種檢查，health200不等於Google／郵件／全部內容均正常。

## 5. 部署基線與冷啟動

### 5.1 正式封裝方案（E-29）

保留現有 `compose.yaml` 作本機開發及DBeaver查看；新增**獨立使用**的 `compose.production.yaml`，執行時不與開發檔疊加，避免繼承來源掛載／測試服務／開發port。這是依現況選擇的工程設計；正式環境預設以單機Linux Docker主機規劃，實際主機仍待提供。

- app／publisher重用同一個發布版映像，沿既有Dockerfile的runtime方向封裝code、正式Composer依賴及Node renderer；排除開發品質工具、tests／fixtures、原型／Excel、.env、artifacts與本機舊產物。web映像封裝本次前端dist與Nginx設定；不把整個專案目錄掛進正式容器，也不複製一份PHP View。
- 正式常駐服務以web、app、publisher、db為基線；Mailpit／app-test／web-test及開發frontend建置服務不啟動，建置在release流程完成。資料庫／媒體／publication用獨立named volumes，FPM只在容器網路，DB預設無宿主機port；本機的127.0.0.1:3306查看能力保留。
- runtime／web綁定同一份release manifest，記錄公開應用版本、內部commit／image digest／migration checksum／產物hash。映像保留既定digest固定做法，實作時核對可取得的官方安全版本；不用浮動latest，也不在計畫中宣稱現在已完成安全掃描。
- 新增非機密設定 `APP_VERSION` 的初案：本機預設development，正式發布必填公開release標籤。沿用既有APP_ENV／APP_URL／SESSION_SECURE／SMTP／Google設定。範例不放秘密，實際.env維持忽略；秘密不寫入映像、log或完整Compose展開輸出。
- 正式HTTPS預設由web掛唯讀憑證提供443，80僅轉HTTPS；若主機已有TLS反向代理，部署時改接既有終端並限制內部入口，不能無條件信任外部X-Forwarded-*。SESSION_SECURE=true、HttpOnly／SameSite、正式Origin／canonical一致，HSTS只在正確HTTPS入口設定。憑證取得／更新方式待主機確認，不先綁雲端或新增ACME服務。
- 原publisher `content:watch` 的10秒輪詢先沿用，驗排程到期、週界、worker停止／重啟及失敗重試；不再加另一套重複發布cron。容器running只證明程序存在，另以公開API／HTML同版探測驗證發布就緒。
- 日誌沿requestId與安全錯誤摘要，加入Docker輪替設定及操作命令；初案10MB×3為E值，可調。核對Nginx／FPM／CLI輸出都不洩漏query、憑證、token或原始個資。正常audit／auth_events不自動清除，保存天數待營運決策；稽核查詢／進階營運留階段十四。

### 5.2 冷啟動順序

先驗設定／版本 → 建置鎖定映像 → DB可連 → 受控執行migration一次 → 明確執行固定管理員初始化與核准的內容／媒體匯入 → 初次公開發布成功 → 啟動worker與對外web → health／公開就緒與登入流程驗收。

空庫尚未migration時health必須503；不能由HTTP健康請求偷偷建表或建立admin。啟動腳本／depends_on需核對實際順序，避免web等公開首頁、publisher又等web而形成循環。既有庫升級不重設密碼／APP_KEY、不覆蓋客戶內容或重新seed原型；資料不足列內容待補，不猜年份／授權／正式條款。

隔離演練使用唯一Docker project與全新DB／媒體／publication volumes及loopback測試port，禁止共用現有yinghua資料。可用測試網域與自建測試CA做驗證憑證的HTTP測試，不改使用者hosts／信任庫、不略過瀏覽器安全警告；這不等同正式公網TLS驗收。

## 6. 備份、還原與回退（E-29）

| 對象 | 備份／復原規則 |
| --- | --- |
| DB | 使用MariaDB對應版本的logical dump，包含migration、trigger、帳號／權限、內容、收藏／行程、SEO及audit。以維護窗口暫停寫入及publisher，InnoDB用single-transaction／quick並禁止同時DDL；不直接打包正在寫入的DB資料目錄 |
| 媒體 | 同一維護窗口複製media volume與檔案清單／hash，確認每個DB引用存在。單獨DB dump不能當作完整網站備份 |
| 發布資料 | 記錄generation／release／renderer版本供追溯；主要復原方式由還原後DB與媒體重新產生HTML，不直接把舊current symlink當作安全回退 |
| 設定與金鑰 | APP_KEY與必要秘密透過獨立安全備份保存，憑證依部署方管理；版本清單只記非機密識別，不將.env或DB dump提交Git。備份存取／加密、異地位置及保留期依實際環境設定 |
| 還原演練 | 將自有隔離測試資料備份還原至全新獨立volume，驗帳號hash／權限、收藏／站點順序／版本、內容狀態／別名／SEO、正常audit及真圖片hash；重產發布後核對API／HTML與下架404 |
| 對外復原前 | 撤銷還原點帶回的舊session／重設token／驗證挑戰，避免已撤銷憑證復活；保留帳號／內容／收藏／行程／audit，告知需要重新登入。主庫實際還原另確認目標與時間點，不藉演練授權覆蓋現有資料 |
| 程式回退 | 先驗舊映像與目前schema相容；可相容時用目前DB重新發布並驗health／公開一致性。不相容時走已驗證的整組復原程序；不自動down migration或只切舊HTML以復活草稿／下架內容 |

必須包含損壞備份／缺圖／版本不相容／發布失敗的拒絕路徑；驗證未過不打開對外流量。記錄實際備份量與復原耗時，RPO／RTO、停機窗口與保留期是待確認的營運需求，不自訂保證值。備份保留及刪除政策不在本次規劃自動啟用。

## 7. 實際介面與無障礙驗收

| 範圍 | 預定頁面／操作 |
| --- | --- |
| 公開內容 | index、museum、cinema、explore、food、events、activities、city-news、offers；實際生成places／films／news／offers詳情、全部生成分頁、legacy／舊slug／404 |
| 會員 | register、login、forgot-password、reset-password、change-password、favorites、planner、terms、privacy；匿名待辦→登入→確認／取消→本人保存→重登載回 |
| 已開放後台 | admin登入／忘記／重設／更改密碼、places／place-add／place-edit、films／film-add／film-edit、admins／admin-add／admin-edit、permissions、settings；root／一般管理員／僅新增／唯讀模擬各自驗權 |
| 後續頁面 | admin/index、news／offers／routes管理、members與前台routes／search等，只驗未開放提示、導覽權限、noindex及不假成功；不宣稱後續業務完成 |

先建完整頁面／互動矩陣，再執行桌面1440×900、手機390×844與中間斷點；記錄實際CSS viewport。逐種已實作表單驗初始／載入／保存／成功／欄位錯誤／409／503／斷線重試／取消重讀／未保存離頁／空清單／長值，不以一張代表頁截圖代替所有流程。

使用原始website做唯讀對照：共用導覽／側欄／面板、卡片／照片比例、場次介紹、篩選／分頁、按鈕與返回流程；差異逐項附S／D／U／E原因，不將原型localStorage或假登入當成應保留的真實作動。

按WCAG 2.1 AA適用條件核對：鍵盤無陷阱、可見焦點／對話框返回焦點、標籤與錯誤關聯／狀態播報、alt、對比、200%文字縮放、320 CSS px重排／相當的400%縮放、reduced-motion。自動檢查配合實際讀屏操作；工具缺少則列未驗，不能只憑DOM／axe就宣稱符合全部AA。須使用者完成的圖形驗證碼或正式條款同意不代為操作；隔離API測試與真實使用者同意分開記錄。

## 8. 預計檔案與功能提交

以下是預計新增／調整，不表示檔案已存在。細部名稱以實作及導讀核對；不用先建立空類別或假驗收報告。

| 預計位置 | 用途 |
| --- | --- |
| backend入口／Composition／health路由、Controllers/HealthController.php、Services/ApplicationHealth.php、DTO/HealthReport.php、Adapters/DatabaseHealthProbe.php | 限縮的健康入口、明確結果與受控基礎設施探測 |
| backend/resources/openapi.json、相關Unit／health_integration.py | 一個新操作、200／503／HEAD／405／不洩漏資訊／故障復原與真MariaDB契約 |
| backend/tests/phase8_regression.py、診斷／部署／復原測試 | 串接既有分組測試、固定情境、失敗記錄、fixture清理；不能以自動重試掩蓋業務錯誤 |
| compose.production.yaml、docker/php/Dockerfile、擬新增docker/nginx/Dockerfile與正式Nginx模板 | 發布版app／worker／靜態web封裝、TLS與資料volume、實際啟動順序；不改本機查看DB的設定 |
| scripts/ops/預檢、部署、備份、還原、公開就緒探測 | 最小可重現命令與目標保護；Linux／容器POSIX工具執行，Windows入口沿Docker／WSL，不假定主機已安裝PHP或直接可執行sh |
| docs/PHASE-8-ACCEPTANCE.md、P0-ACCEPTANCE-MATRIX.md、P0-OPERATIONS.md、modules/operations.md | 實際結果、16項／儲存格矩陣、部署與復原步驟／排錯／正式差項；僅於實作／執行後填寫成果 |
| PROJECT-GUIDE、REQUIREMENTS-MAP、DATABASE-SPECIFICATION-MAP、IMPLEMENTATION-PLAN、ARCHITECTURE、DESIGN-TRACEABILITY、README／AGENTS | 核對API／頁面／表／migration／環境／操作命令與真實完成範圍 |

提交按可獨立驗證功能拆：8A按實際根因及其回歸、8B健康API、8C發布版／部署、8D備份還原／回退、8E整合驗收及導讀；不只按副檔名拆，也不將整階段壓一筆。自己的失誤按GIT-WORKFLOW歸回對應功能，不另留複驗進度提交；舊提交是否已分享須先核對，不自行覆寫遠端。每筆包含相應測試／契約／中文說明，最終備份歷史、驗完整tree與原始69檔後再--no-ff合併，保留階段分支。

## 9. 開始實作前固定驗收清單

| ID | 必須留下的結果 |
| --- | --- |
| 8-T01 | 原始69檔／原CSS不變、71→預計72操作、67頁／33表／18migration實況、舊checksum、.env忽略、現有工作樹差異保留 |
| 8-T02 | 16列P0逐一有儲存格、程式／API／DB／頁面、成功／拒絕／失敗證據；不憑歷史測試總數勾選 |
| 8-T03 | 註冊／CSRF／發布偶發問題的失敗資料與根因／修正／邊界測試，固定輪次流程；無原因仍失敗則未通過 |
| 8-T04 | 匿名health200、正確應用version、no-store／requestId、HEAD空body、405／Allow、精確路徑、無Set-Cookie／診斷或SQL寫入 |
| 8-T05 | 核心設定無效、隔離DB斷線／認證錯誤／schema缺漏與checksum不符均非健康，503及逾時有效；復原後200，不影響主DB |
| 8-T06 | 撤權／停用／session過期／logout、root與最後管理權、僅新增及唯讀模擬；所有管理操作真驗權 |
| 8-T07 | 確認密碼必填一致、挑戰／reset過期與重用、限速與SMTP失敗；兩命名空間與TLS Cookie實際行為 |
| 8-T08 | 圖片內容／重編碼／alt／MIME／大小、私有草稿與公開引用、跨模組授權、XSS與下載路徑 |
| 8-T09 | Google允許網域／短網址／redirect／SSRF及失敗不自存；隔離測與必要少量實連分列，API Key不出前端／log |
| 8-T10 | 景點／電影寫入到前台、同開始時間檢查、排程／週界／有效期、消息優惠分離、首頁空／失敗／替代圖 |
| 8-T11 | 收藏／目前行程本人隔離、版本／重送／並行、下架／發布過渡、重登載回，私人保存不改公開generation |
| 8-T12 | SEO繼承／清除／409／權限、全公開HTML及分頁無JS head、HTTPS canonical／OG／匿名圖、私人noindex及敏感query排除 |
| 8-T13 | DB／renderer／發布紀錄故障、安全503／已保存待重試／原子切換／復原，管理修復入口可達，無舊dist旁路 |
| 8-T14 | 真實desktop／mobile原型對照與所有已實作表單狀態，差異有來源；後續入口不假成功 |
| 8-T15 | 鍵盤／讀屏／對比／縮放／重排／reduced-motion適用結果，工具不可用或未測明列，無假AA宣告 |
| 8-T16 | 正式Compose解析無開發來源掛載／測試服務／DB對外port，code與依賴／web為同release，secret不進映像／log |
| 8-T17 | 新隔離project／全新volumes冷啟動、migration一次／重跑、受控初始化、首次發布／對外開放無依賴循環 |
| 8-T18 | 停用／重啟publisher、DB與HTTP服務恢復，資料／媒體持久、排程到期與公開就緒探測，不能只看running |
| 8-T19 | 同維護窗口DB／媒體／release備份，含trigger／checksum；新volume真還原及舊授權token失效 |
| 8-T20 | 損壞備份／缺圖／schema不相容拒絕開流量；映像回退重產公開內容，不復活草稿／下架，不對主庫演練覆蓋 |
| 8-T21 | PHPStan10／PHPat、適當PHP／JS測試、格式dry-run及Rector提案審閱、真OpenAPI／MariaDB、建置與全頁資產完整性 |
| 8-T22 | 依賴／映像與秘密檢查的實際工具／版本／結果，未安裝或未執行不可當通過；風險修正與外部待驗分列 |
| 8-T23 | 真正式域名／憑證／SMTP／內容與授權／條款版本逐項證據；平台收錄驗證屬階段十二，P0分享與HTTPS仍需驗 |
| 8-T24 | fixture／故障／暫時project清理、原庫保留、正常audit保留、文件連結及導讀同步、歷史備份／原始提交／最終tree核對 |

適用skills：PHP改動用php-modernization；授權／health／部署與輸入輸出用security-audit；實際索引／診斷查詢時用mariadb-query-optimization；本次修改介面用web-design-guidelines及可用browser；Excel只讀核對用spreadsheets。PHP／Composer品質工具在Docker跑，Rector只dry-run；不用skill範例新增不需要的框架／服務或大量索引。

## 10. 正式條件、設計參考及目前交付

正式待提供／確認：Docker主機與OS／架構、對外網域及憑證／更新方式、SMTP與寄件身分、Google服務／配額、正式內容與素材權利、業主條款／隱私及版本、維護窗口、備份位置／保留期／RPO／RTO。秘密放部署設定，不貼文件；目前不因缺資料而停止本機規劃，也不把它們列成已驗證。

以下官方資料於2026-10-24核對，支援E-29的成熟做法，不能取代Excel要求：

- [Spring Boot Health](https://docs.spring.io/spring-boot/reference/actuator/endpoints.html#actuator.endpoints.health)：借鑑最小公開健康資訊與200／503語意，不導入Java或Actuator框架。
- [Docker Compose正式環境](https://docs.docker.com/compose/how-tos/production/)：依環境封裝來源／設定；本案選獨立正式檔以避開既有開發掛載繼承，不宣稱官方要求只能採獨立檔。
- [Docker volume備份與復原](https://docs.docker.com/engine/storage/volumes/#back-up-restore-or-migrate-data-volumes)：驗證持久資料的備份與新volume還原；資料庫一致性另按MariaDB方式處理。
- [MariaDB mariadb-dump](https://mariadb.com/docs/server/clients-and-utilities/backup-restore-and-import-clients/mariadb-dump)：InnoDB一致快照與DDL邊界，不能把運行中的整個DB目錄tar當作已驗證備份。
- [W3C WCAG 2.1](https://www.w3.org/TR/WCAG21/)：依客戶指定的AA檢核適用條件與完整流程，不將自動檢查當成全部符合證明。

**本次只有這份計畫及工程索引更新。** 尚無health API、正式Compose、備份／還原工具、階段八測試結果或驗收通過文件；API／schema／頁面／環境實作基線不變。
