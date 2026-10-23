# 階段七：全站 SEO 設定與 HTML head 計畫

設計日期：2026-10-24。使用者已授權實作，分支 `phase/07-seo-settings-head` 從最新 main 建立。**7A 資料層、7B 管理設定 API／表單已建立；公開同版發布與整階段驗收繼續。** 目前為 70 個 API 操作／65 固定頁；主／測試庫均 33 表／18 migration。檔案與實測見 [閱讀指南](modules/seo-settings.md)，以下保留設計／驗收基準。

基準：[原始規格](../映畫竹塹_後台工程串接功能規格.xlsx)、原始 `website/admin/settings.html`／`website/js/app.js` 的 SEO 表單，以及 [總計畫](IMPLEMENTATION-PLAN.md)。S＝Excel 明文，D＝Excel 建議／草案，U＝使用者指定，E＝工程提案。本階段新增的具體設計集中標為 E-28；欄位被列出不等於必填。

## 1. 完成範圍與優先級界線

完成後，具設定權限的管理員可以在原本「網站設定」頁保存全站 SEO 與逐頁／逐內容覆寫；另一個未登入瀏覽器直接取得 HTML，就能讀到更新的 title、description、canonical 及分享標籤。PHP 仍只提供 API，HTML 由 frontend 的建置／發布工具產生，不靠瀏覽器修改 head 充當爬蟲輸出。

| 來源 | 本階段責任 | 界線／解讀 |
| --- | --- | --- |
| S：功能規格 E30–L30；SEO規格 B5–F9、B15–F17 | 獨立 HTML 的標題、摘要、keywords、robots、canonical、OG、Twitter、favicon 與逐頁覆寫 | 30 字標題、70–100 字摘要及 1200×630 圖片是建議，不新增硬性業務門檻 |
| S：功能規格 D32–L32 | 全站設定、正式網址、分享圖、驗證碼、權限、稽核、快取與重新登入後保存 | 只實作 SEO 區塊，不提前開一般網站設定 CRUD |
| D：API草案 C38–H39 | 公開設定 GET、管理 SEO GET／PUT | 三個 HTTP 操作；精確 JSON／錯誤與版本回應是 E-28 |
| D：資料表建議 C24–E25 | site_settings、content_seo；白名單及內容優先於路徑 | 精確 DDL、索引、全文件版本及路徑範本白名單為 E-28 |
| S：SEO規格 C7／E7；功能規格 G30／G32 | 管理／會員／登入等頁強制 noindex，私人 API 仍需授權 | 不能讓全站 robots 或頁面覆寫解除保護 |
| S：功能規格 E28／J28、E31／J31、E33／J33 | 一般設定 P2；JSON-LD、sitemap／索引管理 P1 | 一般聯絡資料／條款編輯留 14；完整結構化資料與索引驗收留 12 |
| S：功能規格 E16／G16；總計畫 7.4 | 註冊可讀條款與隱私說明、版本清楚 | 正式文案仍待業主提供，不生成正式法律內容或改寫歷史同意紀錄 |
| U-02／U-05／U-12 | Controller／Service／Repository、保留原型介面、Excel 為基準 | 不引入 PHP View、SPA 或另外一套設定框架 |

優先級交疊處：SEO規格 G10／G11／G12 分別列 Twitter／驗證碼／keywords 為 P1／P1／P2，但功能規格 E30、E32／J30、J32 與 SEO規格 B15／G15 已把必要設定與輸出列為 P0。本計畫沿既有階段七界線完成這些基本欄位保存與 head 輸出；平台實際驗證、進階索引與完整 JSON-LD 仍留後續，不將整個 P1／P2 提前完成。

## 2. website 與頁面對照

原型的設定 UI 實際由 `website/js/app.js` 的 `adminSEOSettingsMarkup()` 產生，不能只看 settings.html 空容器判斷原型沒有表單。

| 原始／目前頁面 | 預計處理 | 保留與差異 |
| --- | --- | --- |
| `admin/settings.html` | 將 frontend 同名頁的「尚未開放」換成 SEO 表單，接真實 API | 保留側欄、頂列、admin-panel、form-grid、底部保存及逐頁 details 版型；不另建 SEO 首頁 |
| 原型「基本網站資訊」「搜尋引擎與社群分享設定」 | 網站名稱、預設摘要、後綴、robots、keywords、正式 origin、分享圖、Twitter、Google／Bing token | 聯絡信箱、一般條款／政策編輯仍顯示未開放；不保留可點但不保存的假控制 |
| 原型「各頁 SEO 覆寫」 | 固定頁／四種詳情範本可展開編輯；增加必要的單筆內容選擇 | 名稱、摘要、關鍵字、圖片 URL、robots 沿原型；明示留空繼承、有效值來源及清除覆寫 |
| `index.html`、`explore.html`、`food.html`、`museum.html`、`cinema.html` | 原有已發布 HTML 套共用 head 規則 | 正文、卡片、導覽與會員操作維持原有資料流 |
| `events.html`、`activities.html`、`city-news.html`、`offers.html` | 列表與後續分頁都用同一組設定／generation | 每頁分頁的標題、摘要及 canonical 與實際結果一致，不讓所有 page 指向第 1 頁 |
| `places/*.html`、`films/*.html`、`news/*.html`、`offers/*.html` | 自身內容 SEO → 範本／全站設定；舊網址繼續由 resolver 正規化 | 不因 SEO 另建一份內容或修改 slug；下架仍為既有真實 404／發布保護 |
| `routes.html` | 套通用 head 及未完成頁保護 | 原型示意路線仍非真實路線功能；E-28 建議正式內容交付前 noindex，不冒稱階段十完成 |
| `search.html`、登入／註冊／忘記／重設／更改密碼、`planner.html`、`favorites.html` | 固定通用 metadata＋強制 noindex，排除敏感 query | 不把姓名、收藏、行程、Email 或一次性 token 寫入 HTML head |
| `admin/*.html` | 建置階段保留固定 noindex,nofollow，所有設定覆寫皆不適用 | 管理登入與設定修復頁不能依賴 SEO 發布成功才能開啟 |
| 擬新增 `terms.html`、`privacy.html` | 可閱讀且可從註冊頁進入的靜態說明 | 先呈現現有本機測試說明與真實版本；正式業主文案列外部待辦，不冒充已交付 |

原型共有 19 個 SEO 頁面／範本項目，包含 `place-detail.html` 等 legacy 入口。管理清單保留使用者熟悉的主題名稱，但 legacy 詳情的有效 SEO 目標是 resolver 找到的正式內容，不保存一個會蓋過所有內容的假單筆頁。新 favorites／驗證頁的保護另外加入，不從原型照搬不安全的 index 設定。

前台可見品牌、聯絡信箱及頁尾一般資訊不在此階段全面改寫；此處網站名稱用於 title／og:site_name 等 SEO。一般品牌／聯絡設定留階段十四，避免把 SEO 儲存變成重新設計頁面。

## 3. 預計 API 與資料文件

| 方法與路徑 | 授權／輸入 | 結果與用途 |
| --- | --- | --- |
| GET `/api/v1/site-settings/public` | 匿名，不帶管理端條件 | 白名單的已發布 siteName、seoDefaults、公開驗證 token 及成功 generation；不回 draft、操作者、一般敏感設定或整份未發布覆寫 |
| GET `/api/v1/admin/settings/seo` | `settings.view` | SEO 文件、全文件 version、已設定覆寫／有效值來源、可選頁面與內容識別、發布狀態；不需要另加第四個 selector API |
| PUT `/api/v1/admin/settings/seo` | `settings.edit`、有效管理 session／CSRF，body 帶 version | 在同一交易保存全站＋覆寫＋audit；回新 version 與 publicationReady。唯讀模擬禁止寫入 |

預計文件形狀為 `{version, defaults, overrides}`。defaults 欄位：siteName、titleSuffix、description、keywords、canonicalOrigin、robots、ogImageUrl、twitterCard、twitterSite、googleVerification、bingVerification。overrides 每筆用明確 target 與 title／description／keywords／canonicalPath／robots／ogImageUrl。型別、欄位與語意以實作前補齊的 OpenAPI 為準，不接受任意 key-value 或原始 HTML。

E-28：PUT 是完整 SEO 文件的版本更新，前端沿 GET 的快照編輯；未提供完整文件／version 不被當成清空全部覆寫。null 表示移除單欄覆寫並繼承，空的「關鍵字／Twitter 帳號／驗證碼」可清除；title／description 的空白覆寫回繼承。刪除某筆覆寫以明確移除該 target 表達。一般設定、密碼、API key 不在白名單內。

錯誤沿現有契約：401 未登入、403 無權／唯讀模擬、422 欄位／target 不合法、409 舊 version、503 讀取發布未就緒。**DB 已保存但產頁失敗**回保存結果＋publicationReady=false，UI 清楚表示已保存／待發布並提供重新讀取；不可假稱失敗後要求再次 PUT 相同舊版本。重試沿既有 `content:publish`／publisher，不新增可由匿名觸發的 purge API。

公開 GET 讀同一個成功發布快照，不讀取尚未生成的 DB 新設定；與公開 HTML 無法對版時回既有發布待更新狀態。管理 GET 可讀保存真值及發布狀態，讓有權管理員仍能修正。

完成後預計為 **71 個操作**（68＋3），不把 HTML 輸出計成新增 PHP 網頁 API。OpenAPI、固定清單及各 API 契約回歸須隨功能提交更新。

## 4. 預計資料表與既有內容相容

預計新增 `017_site_seo.sql`，共兩表；完成後預計 33 表／18 份 migration。原有 17 份檔案與 checksum 不改，確切欄數以實際 DDL 驗證後記錄，不先冒充資料庫已升級。

| 表 | 預計欄位／約束 | 來源與用途 |
| --- | --- | --- |
| `site_settings` | `key` PK、`value_json` 合法 JSON、`published`、`version`、`updated_by` nullable FK admin、`updated_at` | D：C24。此階段只開白名單 `seo` 文件；version 作整個 SEO aggregate 的樂觀鎖。未初始化 GET 以版本 0 的預設輸出，不因 GET 寫資料 |
| `content_seo` | `id` UUID PK、`resource_type`、`resource_id` nullable、`path_pattern` nullable、`seo_title`、`seo_description`、`seo_keywords`、`canonical_path`、`robots`、`og_image_media_id` nullable FK、`og_image_url`、`updated_by` FK、`updated_at` | D：C25–D25。空值代表繼承；`og_image_url` 為原型 URL 欄的 E-28 延伸，不強迫新增上傳功能 |

target 採已知固定頁／四種詳情範本，或 attraction／film／news／offer 的真 UUID。resource_id 與 path_pattern 至少一者見 D25；E-28 擬採兩者擇一，避免同筆規則指向兩個目標。個別內容 target 唯一、path_pattern 唯一；範本只允許 `/places/*.html` 等明確白名單，不接受任意 regex、檔案路徑或 SQL LIKE 條件。索引依 PK／這兩項唯一查詢設計，無列表效能證據前不加額外索引。

多型 resource_id 不能用一個 FK 指向四張表；Service 用對應 Repository 檢查存在／未刪，不把「UUID 合法」當成存在。允許為具讀取權限的草稿內容編輯 SEO，公開投影仍只帶已發布內容；頁面範本不可轉而發布草稿。內容軟刪保留稽核，成功公開快照排除該覆寫；改 slug 後仍以穩定 UUID 套用。

管理端選擇器只回必要識別資訊；未發布內容另需相應模組的 view 權限，不能因 settings.view 就列出所有草稿正文。單筆草稿 target 的讀取也走相同規則。SEO 設定權限不等於把內容狀態改成 published 的權限。

既有 attractions／films／news_posts／offers 的 seo_title、seo_description **保留且作內容欄位的 fallback**，不搬移、不刪欄、不建立雙向同步。content_seo 的明確單筆覆寫優先；沒有覆寫時既有內容編輯仍有效。兩個入口有優先關係時，畫面顯示來源與「清除 SEO 覆寫後沿用內容值」，不能讓管理員修改完卻無法理解何以沒變。

本階段分享圖沿原型以 HTTPS URL 輸入，預留的 media FK 沒有選擇器時保持 NULL，不把預留欄位說成已交付上傳功能。引用本站 `/media` 的 HTTPS URL 時，公開匿名可讀性必須檢查現有 MediaAccess 的發布引用規則，避免只對管理員可讀。擬將有效 SEO 圖引用納入成功快照的允許集合，不公開整個媒體目錄、不讓草稿媒體被任意列出。外部 URL 不由 PHP 抓取，避免把保存 SEO 變成 SSRF 入口。

E-28：沒有媒體選擇／發布功能時，本站媒體 URL 只接受當下已公開可讀的圖片，不能藉輸入一個私有 storage 路徑把草稿媒體公開。內容下架後的 SEO 引用須重新驗可用性，失效時改用已公開預設圖並報告狀態，不解除原模組存取保護。

## 5. 欄位限制、繼承與正規化

| 項目 | 明確規則與來源 | 儲存／驗收處理 |
| --- | --- | --- |
| title／description | S：G30、SEO E5／C6 是長度建議；原型未 required | 留空繼承，字數提示不阻擋業務；技術長度擬沿既有 160／500，標 E-28，不冒稱 Excel 必填或 30／100 上限 |
| keywords／Twitter site／驗證碼 | 原型 Twitter site 明列選填；其餘沒有必填依據 | 可空；token 純文字，拒絕貼整段 script／meta，無 token 就不產生該標籤 |
| robots | S：G32 白名單；原型三個選項 | `index,follow`、`noindex,follow`、`noindex,nofollow`；不接受任意指令字串，保護頁規則優先 |
| 正式 origin | S：G32／SEO C8–F8 | 非空只接受 HTTPS 根來源，拒 userinfo、path、query、fragment、外加腳本。可先留空完成本機設計，不虛構正式域；正式上線未設定即列未通過 |
| canonicalPath | D：D25，不接受任意外域；S：SEO E8 | 只接受合法站內目標、禁止 `//`／外站／穿越；詳情強制回正式 slug 自身，不能把不同內容都指往首頁 |
| 圖片 URL | S：SEO E9 | 非空為絕對 HTTPS；1200×630 僅提示。無個別圖沿用內容公開圖片或全站預設；匿名前台讀取列驗收 |
| 未定義欄位／技術大小 | Excel 沒有定義每欄上限或整份覆寫數量 | 實作前按資料型別／既有 Input 與實際 65 頁規模固定具體上限並寫 OpenAPI／E-28，不把建議字數當硬限制、不無限收任意 JSON |

逐欄解析順序：**content_seo 單筆內容覆寫 → 既有內容 SEO 欄位 → 明確頁面／詳情範本覆寫 → 全站預設 → 既有安全模板 fallback**。所有層皆逐欄繼承，不因一個 robots 覆寫把摘要清空。這是 D25／SEO D5–D6／D16 的本案具體化 E-28，實作以表格測試固定。

title 的完整覆寫原樣作標題；未覆寫時以當頁真實主題＋目前後綴生成，後綴只加一次。原型及現有內容的既定標題／摘要會整理為明確頁面預設，初始資料不把所有頁灌成同一份全站摘要。全站摘要是最後共用 fallback；有效頁仍須符合 S：L30／SEO F5–F6 的主題相符、非空與差異化。若管理員刻意輸入重複，提出可見提示及驗收差異，不自行增加禁止保存的規則。空介紹不從 Google 或 AI 另寫行銷文案。

canonical 與 og:url 使用同一個決定結果。`/` 與 `/index.html` 統一；page=1 去除，合法 page>1 保留；追蹤參數去除，分類／分頁依現有可生成網址白名單處理；legacy id 先經既有 resolver 轉 slug，私人 place／route／returnTo／token 等不進 head。

SEO canonicalOrigin 與運行中的 `APP_URL`／Nginx canonical authority **分開**。後台 SEO 設定不改 .env、不切換 Cookie 來源或登入網域。本機 canonicalOrigin 空時可用本機 origin 驗證流程，明列 E-28 開發 fallback；測試中另以保留的 HTTPS 測試域驗輸出。正式網址、TLS、匿名分享圖與平台結果仍需正式部署驗證。

## 6. 分層、共用程式與檔案

沿 [ARCHITECTURE](ARCHITECTURE.md) 的 Controller → Service → Repository，資料交易與發布協調放 Service。新增型別使用 strict_types／readonly DTO／enum 與建構子依賴，不需要 BaseSettingsService、ORM 或另一套 MVC 框架。

| 預計檔案／位置 | 責任 |
| --- | --- |
| `Controllers/SeoSettingsController.php`、`PublicSiteSettingsController.php` | 白名單輸入 → DTO → Service → JSON；不產生 HTML，不寫 SQL |
| `Services/SeoSettings.php` | 授權再次確認、版本鎖、全站＋覆寫＋audit 同交易、提交後呼叫既有發布；不在 Repository commit |
| `Services/PublicSiteSettings.php` | 從成功發布快照取公開白名單，不讀一般設定或私有欄位 |
| `Repositories/SiteSettingsRepository.php`、`ContentSeoRepository.php` | 明確 PDO 查詢、鎖設定列／唯一目標、批次讀取與型別映射，不逐頁 N+1 |
| `DTO/SeoSettingsInput.php`、`SeoDefaults.php`、`SeoOverride.php`、`SeoSettingsDocument.php` 等 | 有型別的輸入、目標、欄位與輸出；具體名稱以實作核對為準 |
| `Policies/SeoTargetPolicy.php`、`SeoUrlPolicy.php` | 共用頁面／範本白名單、來源／canonical 安全與有限 robots 值 |
| `ContentPublication.php`、`NodeContentRenderer.php` | projection／generation 納入 SEO 文件，rendererVersion 納入共用 head 模組與頁面清單 |
| `frontend/scripts/seo-head.mjs`、既有 build.mjs／publish.mjs | 共用純函式解析優先級與安全標籤；固定／動態頁不各寫一套。Node 留在建置／發布容器，不改為網站後端 |
| `frontend/assets/js/api/seo-settings-api.js`、`pages/seo-settings.js`、必要共用欄位元件 | 沿 api-client／表單錯誤／CSRF、版本、讀取／保存／發布提示，不把整套設定塞進 admin.js |
| `frontend/pages/admin/settings.html`、必要範圍 CSS | 保留原型排版、details、欄位標籤與手機操作；共用 admin.js 權限及唯讀模擬 |
| `backend/tests/seo_settings_*.py/php`、`frontend/tests/seo-*.test.mjs` | MariaDB／HTTP／契約／並行／失敗回滾、真實產物 head、表單行為；不只 grep source 宣稱功能通過 |

共享規則選用一個擁有者：PHP 管輸入／業務限制，Node 管實際 HTML head 與繼承；可跨語言的 route catalog／enum 值用明確資料文件共用並驗契約，避免兩邊複製不同清單。不為消除一兩行重複引入設定 DSL。

## 7. 發布一致性、快取與復原

目前只有景點／電影／消息／優惠／首頁受 content publication 管理；其他固定頁直接來自 dist，而 build.mjs 亦有硬編碼 head。實作須處理這個現況，不只改 publish.mjs 就宣稱全站完成。

1. 以既有發布鎖固定順序取得設定文件；會員收藏／行程不加入這把鎖。保存交易檢查目前管理員權限／version，鎖定或首次建立 `seo` 設定，寫入覆寫與安全 audit 摘要，commit 後再產頁。
2. 一次 projection 包含內容與 SEO revision，generation 同時計算；只在完整目標產物與 head 檢查成功後原子切換 current。公開 API 與 HTML 使用同一快照，不先讓 GET 顯示新值而 HTML 還是舊值。
3. 將已交付公開固定頁也接入相同發布結果。Nginx 查找順序與 publication gate 要明確核對，不能優先命中舊 dist。失敗時沿既有 503／不服務舊公開版本規則，不靜默退回可索引的舊 head。
4. 管理／認證及私人頁保留安全固定 shell 與 noindex，不因 SEO 發布故障阻止管理員登入及修復。這些頁的 head 不接任意逐頁 index 覆寫；不以 PHP 動態 View 解決固定頁同步。
5. 既有 no-store 先維持，這是 E-28 的低複雜度快取策略；實際有 ETag／cache 時必須按 generation 失效，禁止只清瀏覽器 localStorage。外部 CDN 未部署不宣稱完成 purge，公開設定不快取草稿。圖片外部供應者的快取不在本機可控範圍。
6. publisher 對 SEO revision／新模板版本變動能重試，CLI `content:publish` 可受控復原；已保存的 version 不回退。進行 renderer 失敗／DB 故障測試時只用 yinghua_test，復原後核對公開與管理版本重新一致。

不把 SEO origin 寫回 APP_URL；不新增容器、套件、對外 port、Google API 呼叫、Redis 或訊息佇列。修改 Nginx／renderer 後依既有 Docker 流程建置、重啟所需服務及重新發布，主／測試來源與 volume 維持隔離。

## 8. 功能提交與執行順序

本規劃文件在 main 保存。只有後續取得實作授權，才從當時最新 main 建立 `phase/07-seo-settings-head`；不提前建立含假成果的階段分支。

| 子步驟／預計提交 | 可獨立說明與驗證的功能 |
| --- | --- |
| 7A `build(seo): prepare typed settings and override persistence` | 兩表／一 migration、DTO／enum、Repository／target 規則、空庫／保留資料升級、版本與唯一約束；明列共用前置，尚無可用 UI |
| 7B `feat(seo-settings): manage SEO defaults and page overrides` | 管理 GET／PUT、原型設定表單、權限／唯讀模擬／CSRF、同交易 audit、衝突／清除／繼承及 API 契約；保存與待發布狀態誠實區分 |
| 7C `feat(seo-publication): publish consistent SEO across public pages` | 公開 GET、共用 head、固定與動態發布、Nginx、canonical／robots／社群／驗證碼及匿名圖片；三個操作與全站同版整合可完整驗證 |
| 7D `feat(policy-pages): expose readable terms and privacy notices` | 原型既有開發說明的獨立頁與連結、版本／歷史同意不變，正式文案缺少明列待業主提供；不新增一般政策管理 |
| 7E `test(phase-07): verify SEO publication and update project guide` | 固定驗收清單、跨模組與產物／實際介面、容器重建、全站導讀、資料表對照與本機／正式差項 |

代理自身修正併回對應功能提交，前後端／契約／相關測試／中文閱讀指南隨功能交付。合併前備份歷史、確認原始 69 檔／既有 migration／完整 tree；本機驗收通過後 --no-ff 合併並保留分支，不自行推送。防毒隔離的既有 skill 檔案差異保持原狀。

## 9. 實作前固定驗收清單

| ID | 必須驗證的結果 |
| --- | --- |
| 7-T01 | 空庫／保留既有 31 表升級、重跑安全，原會員／內容／收藏／行程及既有 migration checksum 保留；預計 33 表／18 migration |
| 7-T02 | 三個操作逐請求／回應驗 OpenAPI；設定只允許 SEO 白名單，匿名／會員／無權管理員拒絕，撤權下個請求生效 |
| 7-T03 | 有 view 無 edit 能看不能存，edit 授權與當前頁需 view 的 UI 規則清楚；唯讀模擬及 CSRF 缺漏／偽造寫入拒絕；固定 root 不被改權 |
| 7-T04 | 初次版本 0、兩個管理員相同版本並行只有一份成功；舊版本 409，不覆蓋別人的設定；資料／覆寫／audit 失敗整組 rollback |
| 7-T05 | 全站、固定頁、範本、單筆內容及既有 seo_title／description 的逐欄優先級；空值清除恢復繼承；重複摘要只提示不擅加阻擋儲存規則 |
| 7-T06 | 關閉 JS／直接 HTTP 檢查全部已交付獨立頁及所有生成分頁：title／摘要符合內容且差異化，標籤無重複，後綴只加一次，favicon 存在 |
| 7-T07 | 正式 HTTPS 模式 canonical／og:url 同值且絕對、詳情自指、首頁正規化、分頁保留、追蹤參數去除；legacy 及舊 slug 轉正式內容，非法路徑不穿越／外跳 |
| 7-T08 | 全站／單頁要求 index 都不能解除 admin／登入／註冊／搜尋／私人頁 noindex；head 不含會員名稱、私人資料、returnTo／重設 token |
| 7-T09 | OG／Twitter 圖文與配置一致、可匿名讀圖、不放大草稿媒體授權；token 作純文字輸出、空值不產生、惡意 HTML／屬性／URL 不執行 |
| 7-T10 | 發布失敗顯示已保存／待發布，公開設定／HTML 不混版，重試恢復；固定頁不能繞 gate 命中舊 dist；管理登入／設定修復仍可用 |
| 7-T11 | 設定變動、內容改 slug／下架、範本更新、publisher 重新啟動皆讓 generation 正確更新；no-store／既有快取失效真 HTTP 驗證，私人保存不觸發 SEO 發布 |
| 7-T12 | 原型設定頁桌面 1440／手機 390、details／Tab／焦點、欄位錯誤、讀取／保存中／409／發布待更新；重新登入及第二瀏覽器讀回真值 |
| 7-T13 | app／web 容器重建後設定與內容保留；真 MariaDB 的 PK／唯一查詢 EXPLAIN，無 N+1、未為觀測而重啟共享 DB |
| 7-T14 | 階段二至六認證／權限／媒體／發布／電影／消息優惠／首頁／本人收藏行程契約與必要介面回歸；不呼叫 Google 或碰主庫測試資料 |
| 7-T15 | PHPStan10／PHPat／格式、PHP／JS 行為及真產物測試，Rector 只 dry-run 並審閱；建置頁數按實際新增更新完整性工具，不以未執行當通過 |
| 7-T16 | 條款／隱私連結、當前開發版本與既有 member_consents 一致；正式網址／文案／TLS／平台分享及驗證分列外部待驗，不擅自同意條款或新增帳號 |
| 7-T17 | 隔離 fixture 清理及正常安全紀錄保留；更新 PHASE-7-ACCEPTANCE、modules/seo-settings、PROJECT-GUIDE、OpenAPI／資料表／來源對照，確認 8／12／14 未提前冒充完成 |

實作 PHP 時套 php-modernization；管理 API 與來源／URL 邊界完成後用 security-audit＋真實 MariaDB 失敗／越權測試；索引使用 mariadb-query-optimization，以本案 12.3 實查；表單／畫面使用 web-design-guidelines 與可用 browser。正式文案及網域缺少不阻擋無關程式開發，但不把正式上線驗收勾成完成。

## 10. 成熟設計參考與待提供項目

已於 2026-10-24 核對下列官方資料，作為 E-28 設計依據；客戶驗收限制仍以 Excel 為準。

- [Google：canonical 的指定](https://developers.google.com/search/docs/crawling-indexing/consolidate-duplicate-urls)：統一 canonical 訊號及絕對 URL，避免不同輸出來源互相矛盾。
- [Google：robots meta](https://developers.google.com/search/docs/crawling-indexing/robots-meta-tag)：head 的索引指令與 robots.txt／真正存取控制分開；本案私人 API 仍依 session 授權。
- [Google：搜尋摘要](https://developers.google.com/search/docs/appearance/snippet)：有內容意義的頁面摘要，不將建議字數當搜尋引擎保證或資料必填上限。
- [RFC 9111：HTTP Caching](https://www.rfc-editor.org/rfc/rfc9111.html)：沿既有快取策略處理版本與失效；不只在管理員當前瀏覽器看見新值就認定跨端同步。
- 分層／Repository／DTO／DI 依 [既有架構來源與取捨](ARCHITECTURE.md#3-採用哪些成熟做法解決什麼問題)，不另加框架或可有可無的 pattern。

外部待提供：正式 HTTPS 網域、可公開使用的分享圖／favicon 品牌素材、Google／Bing 驗證 token（有申請才提供）、業主正式條款與隱私文案／版本。token 只放本功能的公開驗證欄位；API key、帳密仍不放 SEO。這些不需要現在貼密碼或金鑰，也不構成本次已實作成果。
