# 階段十五驗收紀錄

最新補驗（2026-11-02）：依使用者要求按原始Excel逐列完成[30列核實](../EXCEL-FUNCTIONAL-VERIFICATION-20261102.md)。150個不同API／DB案例與Browser後，修復刪除後焦點、首頁各區塊故障／重試與首頁消息新分頁；278 JS／122 PHP（409斷言）／靜態分層通過。正式與AA基線界線不變，無新增API／DDL。使用者另明確核准本機主站更新，兩站10頁資產檢查通過；自有fixture／設定／館舍已清理還原。本段優先於較早數字，下方保留歷史。

2026-11-01。`phase/15-delivery-validation`，使用者本輪明確授權；[計畫](PHASE-15-PLAN.md)。**最新判定：本機階段十五收尾通過；正式平台／server安全依U-18／U-21交接，不表示正式上線。**

原15A–15C新增[30列矩陣](../DELIVERY-ACCEPTANCE-MATRIX.md)、固定交付執行器與[閱讀指南](../modules/delivery-validation.md)、[後台操作](../ADMIN-OPERATING-GUIDE.md)及[正式server交接](../PRODUCTION-HANDOFF.md)。原範圍沒有業務API／頁面／DDL新增；本輪U-23追加補位後為115 HTTP契約，詳見最後收尾節。

## 先前驗收歷史

以下保留當時結果；本輪最新判定以最後收尾節為準。

唯讀盤點已實跑：原始69檔逐位元一致、113 HTTP操作、68固定頁、20 migration、字型資產大小／SHA256與`.env`忽略通過。匿名邊界66操作及自有管理員／會員登入登出通過。

首次固定套件在P1行程測試停止：Browser先前手動建立行程，與舊套件初始current/version=0前提不同；套件finally已清理這兩位自有會員資料。這是fixture前置衝突，沒有修改業務規則或忽略失敗；首次report與log保留於artifacts/phase15/20261101-145718-21012。新匿名測試初稿曾錯把媒體auth_request的最小403套用一般錯誤欄位，已按現行契約修正；不修改網站回應以配合錯誤測試。

後續固定回歸、全站Browser、擴充復原與正式差項結果會於本文件續記，未取得結果前保持待驗。P0／P1既有有效AA／原型／安全證據沿用各階段紀錄，不宣稱全部在本輪重新執行。正式域名及第三方服務依U-21交接，仍不表示正式上線。

## 最新本機交付結果

已完成本階段工具／擴充復原／手冊實作與本機可驗範圍。**正式交付界線尚未簽核，保留階段分支，沒有合併main。** 階段十四已按本機範圍合併為576a6fa；階段十五新增工具不改應用API、DDL或環境設定。

| 項目 | 實際結果 |
| --- | --- |
| 固定執行器 | artifacts/phase15/20261101-150042-43640/report.json，13個固定套件皆exit0；首次失敗另保留，不改成成功。來源commit為啟動時576a6fa；之後兩筆僅新增交付測試／文件，應用內容與該基線相同 |
| PHP／架構／JS | 116 PHPUnit／381斷言；PHPStan最高層＋PHPat；252 JS；4項維運邊界測試通過 |
| API | 匿名66操作及2種登入登出；P1 181契約0錯誤；搜尋113檢查／28契約0錯誤；營運73檢查／27契約；政策5項通過 |
| SEO／索引 | 本輪368項、46個graph對官方字彙、70 HTTP回應通過；場景資料量使檢查數與歷史369不同，不把歷史數量複製成本輪 |
| HTTP完整性 | 68固定頁／24個直接引用資產，共92路由符合預期；四個無識別legacy detail為404，其餘200。另10頁字型／直接CSS資產檢查通過 |
| 真資料庫 | 34表338欄（含journal）／20 migration；沒有DDL。原始69檔逐位元一致，113 HTTP契約／68來源頁及字型SHA256／env忽略通過 |
| 新空庫封裝 | 17項通過；523235a來源的兩組不可覆寫release標籤。實測migration重跑、Secure／HttpOnly／SameSite、TLS／HSTS、故障503、排程與worker恢復，不等於正式server驗證 |
| 還原／回退 | 32項還原及9項回退通過；有真一般政策／聯絡、兩站排序路線、多份會員行程、圖片與公開搜尋投影。全業務表逐列hash；搜尋排除預期重建generation後逐欄相同；不是任意歷史版本相容保證 |
| Browser | 公開根頁67筆（含4個無識別404桌面觀察）、後台69筆、17詳情×三尺寸51筆、後台認證9筆，共196筆量測無整頁水平溢出。非像素相等／逐頁全部按鈕操作宣告；部分讀取發生載入中，已另確認設定表單就緒。詳情曾碰到並行SEO失敗注入，穩定後重做51筆皆有title／h1 |
| 原型與鍵盤 | 六個原始website代表頁×三尺寸18筆；主版型／導覽／卡片結構保留。資料與權限由真API提供、字級／一般設定表單及原頁選地點依E-36／U-22補充，不能稱像素完全相同。Enter搜尋、結果Enter到正確館舍、手機選單Escape返回按鈕通過；階段十四會員摘要Tab／Shift+Tab／Escape及行程保存重載有效 |
| 安全 | Composer正式lock零公告；映像掃描仍有HIGH／CRITICAL，**沒有通過全安全門檻**，見[安全差項](PHASE-15-SECURITY-REVIEW.md) |

本輪沒有重跑Windows NVDA或所有真200%／400%場景；有效P0 AA基線與後續增量分開引用，不宣稱重新取得全站AA認證。首頁普通採樣標題字級／寬高一致，原型進場動畫仍會位移；沒有真節流／首幀CLS，不能宣稱任意網路下零字型跳動。

自有yinghua_test帳號停用軟刪／session撤銷、內容與設定還原，正常audit保留；三個本輪自有演練project與資料卷清理，備份／env／憑證／掃描證據僅保留忽略檔。主DB容器ID前後保持。原型唯讀伺服器及測試分頁結束，未啟動主機讀屏。

可交付物：[30列矩陣](../DELIVERY-ACCEPTANCE-MATRIX.md)、[後台操作手冊](../ADMIN-OPERATING-GUIDE.md)、[既有server交接](../PRODUCTION-HANDOFF.md)、[工具與檔案指南](../modules/delivery-validation.md)。正式網域／SMTP／Google／分享與索引平台／內容確認及server安全需依U-18／U-21補實際結果；因此不能勾選「P2／全案正式交付完成」。


## 使用者要求再次驗證：本輪最新結論

2026-11-01。固定13套件全部exit0，來源為本輪啟動時4be53a2，完整report：artifacts/phase15/20261101-152617-37684/report.json。本輪只更新驗收文件，沒有修改應用／DDL／環境設定，也沒有重新執行先前17／32／9項部署還原或同映像掃描；那些保留為有效基線。

- 116 PHP／381斷言、PHPStan／PHPat、252 JS、4維運測試通過。66個匿名拒絕、P1 181契約、搜尋113／28契約、營運73／27契約、政策5項、SEO368／46graph／70HTTP、10頁HTTP資產及最後92路由通過。
- 真MariaDB34表338欄、20份migration逐檔SHA256對journal一致；原始69檔不變、113HTTP／68頁、字型資產與env忽略通過。1455個文件連結無缺漏；Composer正式lock audit空公告。
- 本輪主站5頁×三尺寸15筆、隔離後台3頁×三尺寸9筆，共24筆量測無整頁溢出；搜尋Enter到正確館舍／手機選單Escape返回、會員摘要正反向Tab／Escape／返回觸發按鈕通過。主站城市卡片同列有／無摘要的footer bottom一致。
- 用自有隔離景點移除摘要／介紹，實際加入行程：看介紹link top=273.171875、metadata bottom=265.171875，間隔8px且14px獨立成行。按明確儲存後重新進頁，名稱與備註載回、focus空且scrollY=0。自有資料與帳號已清理，正常audit保留。

### 不通過：U-22字型視覺穩定

**重新開啟此項，不能再以普通尺寸採樣稱字型問題已解決。** 本輪另用內建Browser開啟只綁127.0.0.1的靜態fixture，字型回應真延遲5秒；不提供API、不代理主站。以全新origin避免字型cache，另在fixture關閉進場動畫、固定純色背景，源CSS／font檔SHA256與frontend相同。這是排版隔離測試，不能拿其中缺API的帳號錯誤當網站功能缺陷。

初始截圖時間1793518552690ms，早於兩份字型回應完成；前後標題font-size=78.43px、font-weight=500、寬620／高324.703125及位置完全相同，**但字形／粗細仍在字型回應後改變**。標題區域像素差（差值>4）42257／202446＝20.87%，純色背景排除了圖片／進場動畫干擾。證據在artifacts/phase15/recheck/font-solid-{first,after}.png、font-solid.json、font-probe-solid-requests.jsonl、font-solid-comparison.json。前面使用clip的嘗試未用於結論，因截圖座標結果不符預期；最終採完整截圖後依DOM座標只做像素統計。

本輪確認的是可重現的晚變字形／視覺跳動，沒有把它誤寫成font-size或標題外框真的縮小，也沒有完整首幀CLS數值。因使用者本輪要求驗證，僅記錄明確失敗與證據，未更換正式字型策略或隱藏網站文字。先前階段十四合併是當時通過範圍，不能用來覆蓋本次重現。

三個暫時字型server與測試分頁均已結束，viewport已重設。主站只做唯讀檢查；業務寫入只在yinghua_test具名自有帳號，已清理。安全映像與正式服務仍按既有交接，未宣稱本輪重掃或正式驗證通過。**總結判定：本輪功能回歸通過，UI字型項失敗；階段十五仍不合併main、不簽核全案。**

## 最終本機收尾：補位與字型修正後

2026-11-01，U-23明確授權補位並收尾階段十五。**本機功能、介面增量及交付工程範圍通過，可依備份／tree核對規則合併main並保留階段分支。** 前節字型失敗已按同條件修正與補驗關閉；正式server安全／平台與全案正式交付仍不是本機簽核範圍。

| 範圍 | 本輪實際結果／證據 |
| --- | --- |
| 固定整合 | artifacts/phase15/20261101-194803-34412/report.json，14套件全部exit0。report記錄啟動HEAD為9c4aea5，當時含本輪未提交的補位／字型程式；不是把9c4aea5本身當新功能來源。最終功能來源已分別提交，下方列補驗界線 |
| PHP與分層 | 122測試／409斷言、PHPStan最高層與PHPat通過；最後發布競爭保護後再跑型別／單元亦通過。新增PHP scoped PER-CS dry-run零差異；Rector僅dry-run，唯一建議是移除new Row呼叫括號，為可讀性保留，未自動套用 |
| 前端 | 整套當時258項通過；之後補上預覽不失焦與輸入競爭修正，再完整跑260項全通過，且已重新建置68頁。不是把258的舊log改成260 |
| 補位HTTP／真DB | 整套當時45檢查／14契約；最後新增CSRF與外站Origin拒絕後再跑47檢查／14契約零錯誤。8項真MariaDB檢查含並行拒絕、地點限速、原值不回寫及請求中下架丟棄回應；證據fallback-final.log／fallback-db-probe.json |
| 既有功能 | 匿名67操作及兩種登入登出；P1 181契約零錯誤、搜尋113／28契約、營運73／27契約、政策5項通過。SEO本輪366檢查／46個graph／70 HTTP，因自有測試景點留白而少了文字存在性檢查，不複製舊368數量 |
| HTTP／檔案 | 68固定頁＋25直接引用資產＝93路由符合預期；10頁HTTP資產通過。原始69檔逐位元不變、115 HTTP契約、20 migration及字型SHA256／env忽略通過；34表338欄、無新DDL／依賴／env |
| 真Google | 自有yinghua_test發布景點以既有Place ID請求，HTTP200／available、原文48字、no-store、自填兩欄仍空。僅保存狀態及字數等元資料，無Google原文檔案；fallback-google-live.json |
| 新UI | 後台預覽及Google詳情各1440／390／320，共6筆有效尺寸量測無整頁水平溢出，原文完整換行；fallback-ui.json。先前跨tab未套用viewport的讀取不計。新增預覽Tab→Enter→Tab→Enter、Shift+Tab與可見焦點通過，送出後焦點留BUTTON，反向回SUMMARY，原值未改；fallback-keyboard.json及fallback-preview-keyboard.png |
| 字型失敗關閉 | 使用同樣真5秒延遲、純色背景、關閉動畫fixture；首張早於兩份font回應完成，標題前後changedPixels=0。快取正常頁確認fonts-ready與原Noto。font-fixed-comparison.json／font-fixed-{first,after}.png／請求時間log。不是完整連續首幀CLS或任意網路零CLS承諾 |

全站196筆Browser、18筆原型及後續24筆巡查、P0工程AA與階段十四有效證據沿前節及各階段文件引用；沒有宣稱這輪重新逐頁點完所有按鈕或取得第三方AA標章。新增UI依web-design-guidelines與現行原型殼審查，沒有啟動主機讀屏／NVDA。

原17項空庫／32項還原／9項回退與映像掃描屬523235a來源的有效工程基線，未在補位修改後重新封裝／重演；沒有DDL／環境／備份格式改變，不把它冒充新應用映像驗證。已有已知app／DB HIGH與CRITICAL差項，Composer lock本輪未改，安全與正式Google配額／政策、HTTPS、SMTP、索引及分享仍依[安全審查](PHASE-15-SECURITY-REVIEW.md)與[server交接](../PRODUCTION-HANDOFF.md)處理。U-18指定網站與部署分開，U-21正式網域未定先完成本機，因此這些不再冒充未完成的網站程式功能，也不稱正式全部通過。

Git按15A工具、15B擴充復原、字型修正、完整補位、最終驗收文件五筆保留功能界線；自身焦點／競爭修正已在補位提交內，沒有fixup進度。改寫前已verify before-fallback-closeout.bundle，保留已封裝523235a祖先與原始第一提交；合併前再備份並核對完整tree與69檔，--no-ff回main、保留階段分支、不推送。清理僅自有yinghua_test帳號／內容／設定，撤銷session、保留audit；暫時字型server與分頁亦關閉。

最終唯讀核對：真MariaDB為34表338欄、20份migration的SHA256與journal全部一致，無DDL變更；1495個文件內部路徑無缺檔（不是外部URL／所有anchor的可用性檢查），自有fixture已cleaned。證據final-integrity.json；提交後inventory另於20261101-200532-29232完成，原始69檔／115操作／68頁／20migration／字型與env忽略通過。

## 2026-11-02 三優先級重驗

使用者再次要求P0／P1／P2，已對Excel16／11／3列分別重驗，本機範圍通過。發現收藏快速離頁中斷儲存與後台UTC未轉時區，已修復並實測。122 PHP／409斷言、最終267 JS；固定14套件、補完整P0舊契約／公開內容／會員／SEO／health；168筆有效Browser尺寸與主要互動。既有原始69檔／115操作／68頁／34表338欄／20migration界線保持。所有先前失敗及接續原因、逐項數字、正式／AA基線與未重演項目見[獨立完整報告](P0-P1-P2-REVERIFICATION-20261102.md)，不以本段取代證據。修正與測試折回本階段整合驗收，維持五筆功能界線；無新階段、無新PHP業務API／DDL／env或依賴。
