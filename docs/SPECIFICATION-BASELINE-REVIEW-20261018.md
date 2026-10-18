# 階段二、三規格限制回溯驗證

後續狀態：本報告是修正前的歷史觀察。R-01–R-07 及未定義政策現已依 Excel／原型與 U-13／U-14 校正；目前行為、355 項驗證與主庫升級見 [校正交付](SPECIFICATION-ALIGNMENT.md)，資料表見 [逐表對照](DATABASE-SPECIFICATION-MAP.md)。

日期：2026-10-18。使用者指示：「往回驗證」。檢查版本：main `1aacb08`，即 U-12 規格基準確立後的應用程式。本輪是來源比對與行為驗證，**當時的限制尚未修正；本報告保留當時觀察，現行狀態以上述校正交付為準。**

## 結果與範圍

以 [原始 Excel](../映畫竹塹_後台工程串接功能規格.xlsx) 為基準，逐項核對原始 website 表單／JavaScript、現行 frontend 表單／JavaScript、PHP DTO／Service／Policy、OpenAPI 及 SQL migrations。使用者另行指定的 U-01 至 U-11 保留獨立來源，不混寫成 Excel 要求。

發現 **6 類額外業務門檻，以及 1 項 API 契約與實作不一致**，詳見 R-01 至 R-07。另列 7 組文件未定義或需釐清範圍的政策；沒有把「文件未定義」直接當成全部選填，也沒有把必要安全防護一併移除。

在現有 Docker 隔離環境 `http://localhost:8081`／`yinghua_test` 完成 **33 項行為觀察斷言**。這些斷言重現目前的接受／拒絕行為，包含不應冒稱規格要求的限制；**不是 33 項規格驗收通過**。PHP 執行在 Docker，使用真實 MariaDB；主站資料庫、`.env`、正式管理員帳密及 Google API 均未變更，外部 Google 請求為零。

本輪未修改應用程式、schema、API 或原始交付；不新增階段。先前功能測試結果仍是歷史事實，但不能證明附加限制獲得規格授權。

## 需校正的差異

| ID | 文件／原型基準 | 現行行為與證據 | 判定與修正方向（尚未實作） |
| --- | --- | --- | --- |
| R-01 地址被設成必填 | 功能規格 F13／G13、F20／G20 列出 address，但未逐欄指定必填；原始 `website/admin/place-add.html`／`place-edit.html` 沒有 required，`website/js/app.js` 的 `readPlaceForm()` 可保存空地址 | [AttractionInput.php](../backend/src/DTO/AttractionInput.php) 的 address 最少 1 字；新增／編輯 HTML 有 required 與星號。空白或省略各得到 HTTP 422／address | 新增的業務門檻，不能由「主要資料欄位」推導。校正前端、DTO、OpenAPI；空字串可存入現有 NOT NULL 欄位，不應為此直接改成 nullable |
| R-02 沒有圖片不能發布 | 串接總覽 B12 規定圖片處理及 alt；功能規格 F13／F20 列出 image，但沒有「每筆發布必有圖片」條件。原型沒有此發布阻擋；功能規格 L5 另要求圖片失敗替代圖 | [AttractionManagement.php](../backend/src/Services/AttractionManagement.php) 在 published 且沒有 media／legacyImage 時拒絕。實測 422／mediaId；同筆內容可無圖建立草稿 | 把「有圖片時需符合規則」擴張成「必須有圖片」。應允許無圖內容依規格發布，沿用既有替代圖；圖片存在時的驗證及 alt 規則保留 |
| R-03 上傳來源與授權文字必填 | 串接總覽 B12 提到保存授權資訊，資料表建議 C11 有 credit／license；明文必填是 alt，沒有要求這兩個文字欄位非空 | [MediaController.php](../backend/src/Controllers/MediaController.php) 對 source／license 各要求最少 1 字；[attraction-form.js](../frontend/assets/js/pages/attraction-form.js) 在上傳前也阻擋；MediaUpload schema 列為 required。兩者分別留空實測各為 422 | 保存欄位不等於必填。應保留欄位並放寬文字非空門檻，前後端與契約一致；現有 DB source／license 已可存空字串。此判定不代表可任意使用沒有使用權的素材 |
| R-04 一般管理員帳號不可改名 | 功能規格 E26／G26／L26 保護的是固定 admin／root；原始 `website/admin/admin-edit.html` 帳號可輸入，`website/js/app.js` 可更新一般帳號 | [admin-form.js](../frontend/assets/js/pages/admin-form.js) 將 account 設 readOnly；[AdminManagement.php](../backend/src/Services/AdminManagement.php) 拒絕改名；[AccountRepository.php](../backend/src/Repositories/AccountRepository.php) 的 updateAdmin 也不更新 account。實測非 root 改名為 422／account | 保護範圍被擴張。應恢復一般帳號可修改，保留 admin 保留字、唯一性、固定 root 保護、稽核與 session 身分一致性；不能只解除前端 readOnly |
| R-05 新管理員首登強制改密碼 | 串接總覽 B9、功能規格 17 要安全登入、密碼政策與重設，沒有強制首次更改的流程；E-07 已承認這是額外方案 | 新管理員預設 must_change_password=1；[Kernel.php](../backend/src/Http/Kernel.php) 拒絕業務讀取。實測登入 200，原本授權的景點列表回 403／PASSWORD_CHANGE_REQUIRED；隔離 fixture 清除旗標後同 session 可讀 200 | 屬增加的使用門檻，不能當作原規格必需。應移除未指定的強制流程或由使用者另行明確指定；一般改密碼／忘記密碼與安全防護不因此移除。DB 預設、既有旗標、前端 guard 與唯讀模擬須一併處理 |
| R-06 管理員帳號字首、字元與最短長度 | 功能規格 G26 只明確限制 admin 保留字／root；原型一般帳號沒有 pattern／minlength | [AdminWriteInput.php](../backend/src/DTO/AdminWriteInput.php) 要求至少 3 字、英文字母開頭及限定字元，前端同樣限制。兩字帳號與中文帳號各為 422。`001_identity.sql` 另以 ASCII 保存 account | 這是額外的帳號業務政策，不能只稱為通用安全驗證。需依原型及明確政策校正；若恢復非 ASCII 帳號，必須處理 DB 字元集、唯一性與正規化，不能只放寬 regex |
| R-07 新增營業時間的契約不一致 | U-09 指定人工／Google 共用營業週表；並非 Excel 規定必須逐日填完才可新增。API 契約應準確描述實際輸入 | [OpenAPI](../backend/resources/openapi.json) `components.schemas.AttractionCreate.required` 包含 openingHours；[AttractionInput.php](../backend/src/DTO/AttractionInput.php) 只在提供時處理。實測省略 openingHours 的草稿 POST 為 201 並保存成功 | 是可重現的契約缺陷。先釐清「預設未知週表／相容舊文字」的既定語意，再讓契約與實作一致；不能為迎合錯誤契約而擅加使用者必填門檻 |

R-01 至 R-06 不表示這些政策在任何專案都不合理；本次判定是它們沒有在本案基準中成為硬性要求。既有 E 標記、計畫中出現或測試曾通過，也不等於使用者已選定其全部限制。

## 文件未定義或需釐清的政策

| 項目 | 來源與現況 | 邊界／後續處理 |
| --- | --- | --- |
| 密碼最少 15 字、最多 128 字 | 功能規格 G16 要「密碼政策」，沒有數值；原型 register.html 最少 8 字。目前 RegisterInput／PasswordInput／AdminWriteInput 最少 15 字；實測 8 字會員與管理員密碼均被拒絕。E-08 已標工程設定 | 不應宣稱「Excel 規定 15 字」。這是文件要求政策後選定的具體值，須明確定案；本輪不自行移除密碼政策或直接修改為另一組數字 |
| 註冊確認密碼必填 | 原型只有一個密碼欄；功能規格 F16 沒有 confirmation。現行前端與 RegisterInput 新增此必填；實測省略得到 422／passwordConfirmation | 屬新增流程，應標明工程補充，按原型與使用者決策校正，不冒稱文件欄位 |
| slug 由管理員手填且限小寫英數連字號 | G13／G20 要唯一 slug；資料表建議 C10、SEO規格 E16 也使用 slug。原型建檔未要求手填 slug；現行表單 required，API 省略／空白拒絕 | 唯一識別需求有依據，「必須人工輸入」及確切 regex 沒有明文。可評估自動產生且允許修改；仍需保護路徑與唯一性，不把 slug 全部刪除 |
| 行政區必須主動選取 | 原型有三區 select，預設北區；目前三區保留，但新增空白選項及 required；空白 API 422。U-10 要改善 Google 行政區帶入，並非明文逐欄必填政策 | 三區範圍有原型依據；避免誤填的空白初值與強制選取是另一項決定，需分開說明，不能從 U-10 推導必填。也不能擅自把未知地點歸北區 |
| 一般管理員一律不能自刪／停用 | G26 寫「不可自我移除最後管理權」。PermissionPolicy 現在對所有 self remove 拒絕；前端自編輯停用也鎖住。實測另有固定 root、本人具 admins.delete，仍不能自刪 403 | 保護最後管理權是規格；所有情況均禁止自移除是更強的 E-09 政策。需校正條件或另行明確選定，不削弱固定 root 保護 |
| 手填地圖網址的 Google 網域限制 | G20／L20 要 HTTPS、允許 Google 網域、非法網域拒絕；上下文是短網址匯入。resolver 的 HTTP／外域實測均 422；但手填 mapUrl=`https://example.com/maps` 的 PUT 為 200 | 已確認兩條路徑不同。G20 是否也約束純顯示用 mapUrl 需釐清；不能直接把這個不會觸發後端抓取的欄位當成 SSRF。其前台連結標籤仍寫「在 Google 地圖開啟」，與實際可存外域不一致 |
| 檔案掃描的驗收範圍 | 串接總覽 B12 寫「檔案掃描」。LocalImageStorage 目前做 MIME、實際解碼、大小／像素限制及重新編碼；程式與容器沒有獨立病毒掃描器 | 文件沒有指定掃描器或檢測範圍，不能將「沒有某品牌工具」直接判成違規；也不能把內容驗證寫成已通過獨立惡意程式掃描。需將掃描定義列為媒體驗收待釐清項 |

## 已核對的規則對照

「保留」表示有相應來源，並不代表本輪重新跑過這項所有失敗／並行／UI 案例。實際重跑範圍見下一節。

| 模組／規則 | 基準 | 現況及判定 |
| --- | --- | --- |
| 會員 Email、名稱、密碼、驗證碼 | 功能規格 E16／F16；原型 register.html | 欄位用途及必填有依據；確認密碼與具體長度另見前節 |
| Email 格式與唯一性 | 功能規格 G16；原型 admin 表單亦有 email 與重複檢查 | Input email 驗證、帳號表 UNIQUE、衝突轉欄位錯誤；保留 |
| 同意條款／版本 | 功能規格 G16；API草案 G17 提同意版本 | acceptTerms／termsVersion 與同意記錄；同意為 S，具體版本欄及表為 D／E；保留 |
| 安全雜湊、登入／登出、Cookie、CSRF、session rotation | 串接總覽 B9；功能規格 G17／L17 | 保留防護；演算法、namespace、期限是 E。正式 TLS／Secure 仍須依部署驗收，不能把本機 HTTP 當成正式通過 |
| 驗證碼限時、一次性、限次、綁定與速率限制 | 串接總覽 B10；功能規格 G16／L16 | Captcha／RateLimiter 有相應邊界；五分鐘／三次等數值為 E-08，Email 替代入口為 E-06 |
| 後台每請求檢查身分與 module/action | 串接總覽 B14；權限矩陣 A2 | Kernel／Authorization／Policy 保留；匿名景點寫入本輪 401 |
| 固定 admin 保留字、root 不可修改／刪除／降權 | 功能規格 E26／G26／L26、G27 | 服務與 SQL 共同保護；本輪 root DELETE 403。不能由此推導一般帳號也不能改名 |
| 個人權限與角色起始範本 | 功能規格 E27／L27 | 個人完整矩陣、版本與稽核存在；完整 JSON 及 version 是契約／衝突處理 E，不能與畫面必填混為一談 |
| 禁止授出自己沒有的權限 | 功能規格 G27 明寫「可選策略」 | E-10 選擇啟用；文件允許的策略，須持續標示可選，不能改寫成所有實作必須 |
| 唯讀模擬身分 | U-06 | 保留 owner／effective、寫入拒絕與退出；不是 Excel 新增要求，且不支持 E-07 首登門檻必須存在的推論 |
| 景點名稱 | 原型 place-add／edit 的 required 與新增 JS 空值檢查；功能規格 F13／F20 | 名稱非空有原型依據；120 字上限為技術設定 E |
| 分類與行政區值域 | 原型 select；資料表建議 D10 提 category enum | 三類／三區值域有來源。必須主動選取與值域是不同規則，見前節 |
| 列表摘要與景點介紹 | Excel 未逐欄指定必填；U-11 明確要求選填並延後自動補位 | HTML 無 required，DTO 可省略／空白；本輪 POST 201、MariaDB 兩欄為空字串。回應 schema required 表示固定回傳欄位，不代表使用者必填；NOT NULL 也不禁止空字串 |
| 人工／Google 共用營業週表 | U-09 | 原型文字欄改為七天結構由使用者指定；四段、最長 24 小時、重疊檢查與備註上限為 E-20。每天可未知，不是要求管理員知道全部營業時間；create 契約問題見 R-07 |
| 座標有效範圍 | 功能規格 G13；資料表建議 D10 指出緯度 ±90／經度 ±180 | DTO 與 003 CHECK；本輪緯度 91 被拒。成對提供／留空是 E 的資料完整性實作，不冒稱文件逐字指定 |
| 公開狀態、草稿／下架不公開 | 功能規格 G13／L13；API草案 G6／G7 | enum、公開投影與 404；本輪草稿公開 GET 404。排程、發布鎖與 generation 是實作 E |
| 地圖網址服務端解析、候選確認、不自動儲存 | 功能規格 E20／G20／L20 | resolver HTTPS／Google 域檢查及 SSRF 防護保留；候選失敗不寫入。確切允許清單、次數、逾時與配額為 E-19，本輪不呼叫 Google |
| Google 簡介與關聯確認 | U-10 | 簡介只在後台參考、不存入網站文案；關聯介面與確認流程已有使用者改善要求，保留獨立 U／E 來源 |
| 圖片 alt、格式與上傳資源限制 | 串接總覽 B12；資料表建議 D11 的用途條件 | alt 必填有來源，本輪留空 422；JPEG／PNG／WebP、8 MiB、6000 邊長／1200 萬像素是實現防護的 E 數值，不是 Excel 指定值。source／license 與必有圖片另見 R-02／03 |
| 版本、slug 唯一、軟刪除、引用檢查與稽核 | 功能規格 G19／G20；API草案 G27；資料表建議 D10 | duplicate slug／stale version 本輪均 409；版本為 D／E，軟刪除與引用保護有 S 來源；歷史 slug 保留策略為 E |
| 博物館資料、更新日期與公開 head | 功能規格 E6／G6；SEO規格 E17／F17；U-02 API-only | publish.mjs 使用公開資料產生 HTML／head 與更新日期，PHP 只交付資料；全站 SEO 設定及 robots／sitemap 仍按後續階段，不因此宣告 P0 完成 |

名稱／Email／文字欄位的型別、UTF-8、NUL 拒絕、長度上限、JSON 欄位白名單、分頁上限、未知 enum 拒絕及 DB 外鍵屬資料／技術邊界。需有 E 說明並前後端一致；它們的存在不能拿來合理化「來源、授權、地址都要填」等額外業務政策。

## 本輪實際驗證與限制

- Excel：唯讀解析七張工作表，重讀相關列及規則；沒有修改原始檔。原型 HTML 以解析器擷取 required／readonly／pattern／長度，再核對 JavaScript 的實際儲存流程，未以「沒有星號」單獨推論可選。
- 靜態交叉核對：RegisterInput、LoginInput、PasswordInput、AdminWriteInput、AttractionInput、營業時間 DTO、相關 Controller／Service／Policy、前端表單／模組、OpenAPI、001 至 004 migrations。沒有執行 SQL migration 或改寫 schema。
- HTTP／MariaDB：33 項觀察全部得到所記錄的現況。包含六類額外門檻、空內容保存、草稿不可公開、唯一 slug、舊版衝突、越權與固定 root 保護，以及手填／解析網址差異；營業時間省略的 201 與 schema.required 交叉核對得到 R-07。
- 初次探針把 mapUrl 誤寫成 url，得到未知欄位 422；修正探針後重跑成功。這是測試請求錯誤，不列應用程式缺陷；前次建立的測試景點也已清理。
- 測試只重設隔離庫 root，建立本輪隨機管理員與景點；完成後經 API 軟刪除並登出。圖片請求全部在文字欄位驗證時拒絕，沒有新增成功的媒體；未測試外部 Google、寄信或修改主站資料。
- 沒有修改 frontend，本輪以表單／JS 語意及真實 HTTP 驗證限制，沒有宣稱重新完成手機、逐像素或系統檔案選擇器驗收。介面規則依 [Web Interface Guidelines](https://raw.githubusercontent.com/vercel-labs/web-interface-guidelines/main/command.md) 的表單標示與錯誤處理檢查；R-03 的必填規則藏在 JS，也應在校正時同步處理標示。
- 沒有重跑先前 396 項整合測試、PHPStan／PHPUnit 或外部安全 scanner；本輪沒有程式異動，舊測試結果不灌入新驗收數字。安全來源比對沒有替代全面弱點掃描。

忽略的本機證據：`artifacts/verify-spec-baseline-20261018.py`、`artifacts/spec-baseline-results-20261018.json`。JSON 不含帳密、Cookie 或金鑰；不把測試觀察命名為規格通過。

## 當時的校正計畫（現已由校正交付取代）

1. 階段三：R-01／02／03 同步調整 HTML、JS、DTO／Service、OpenAPI 與閱讀指南；無圖內容使用既有替代圖。有圖片的 alt／格式限制維持。確認空內容、空地址、缺圖與缺少來源說明能按基準保存／發布；補相應真實 MariaDB／HTTP 失敗與越權驗證。
2. 階段二：R-04 恢復一般帳號改名並驗證 DB 唯一性、固定 admin 保護、舊／新帳號登入、既有 session 及稽核；R-05 校正首登強制政策，涵蓋既有旗標及唯讀模擬。R-06 帳號格式連同 DB 字元集及正規化處理，不只改 UI。
3. R-07 修正契約差異；若允許省略週表，契約必須能描述相容行為，仍保留已提供週表的格式檢查。補真正的請求 schema 與伺服器接受行為對照，不能只檢查 API 路徑有無出現。
4. 密碼數值、確認密碼、手填 slug、行政區初值／必選、自我停用／刪除、手填地圖網址範圍與檔案掃描的未定義部分，按原型及使用者決策定案；不在本次審查中擅自選定新政策。
5. 依 AGENTS／GIT-WORKFLOW 將實作修正歸回相應功能，更新 PROJECT-GUIDE、來源表、契約及實際驗收結果。此次文件提交只交付審查成果，不算修正已完成。
