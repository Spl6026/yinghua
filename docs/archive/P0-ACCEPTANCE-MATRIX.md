# P0：Excel來源與實作／驗證矩陣

2026-10-29 AA 最終判定：依 U-20 不以 NVDA 為必要工具；內建 Browser／隔離 Orca 補驗及有效基線逐項覆核後，WCAG 2.1 A／AA 為39通過、10不適用、1已廢止，沒有未處理失敗準則。到期／錯誤摘要焦點、正反向Tab、草稿保留及174項前端回歸通過；[完整50項矩陣與證據界線](P0-AA-FINAL-20261029.md)。不宣稱本輪重跑所有真縮放或取得Orca完整錯誤字句的自動語音；已聚焦的文字錯誤依W3C 3.3.1／4.1.2判定，沒有改寫工具結果。主機讀屏未啟動，測試資料與臨時環境清理；API／DDL不變。這是目前P0工程AA驗收，不是正式server或第三方標章認證；未合併main／未開始P1。下方日期段落保留歷史。

2026-10-29 內建 Browser 補驗完成：40 個原型頁面桌手機成對對照、10 個核心頁面載入後 320px 重排，以及登入、權限、景點／圖片、電影、收藏／行程、SEO、到期草稿與鍵盤操作已實測。修正電影週期沿用範本日期的缺陷，171 項前端回歸通過；[逐項結果、檔案用途與 AA 證據界線](P0-BROWSER-VERIFICATION-20261029.md)。已清理隔離資料並恢復 SEO／TTL；API／資料表不變。新提示的 Windows NVDA 即時完整播報未列通過，完整 AA 及正式 server 簽核不冒稱完成；未合併 main。下方保留歷史結果。

2026-10-29：本輪 P0 背景驗收已完成，PHP 91／前端 171／session 51 項及完整 API／DB 套件通過；原始 69 檔無差異。瀏覽器專用連線不可用，原生控制後又因無法確認 URL 被工具安全機制停止，因此本輪介面與完整 AA 不簽核，不合併 main。具體執行、跨日續驗、工具診斷及清理見 [本輪驗收紀錄](P0-ACCEPTANCE-20261029.md)。沒有應用或資料表變更，仍為 72 API／68 固定頁／33 表 316 欄／18 migration。

2026-10-26 最新 G34／B15 狀態：[完整 AA 準則表](P0-AA-VERIFICATION-20261026.md) 已完成逐項審查，37 通過、2 不通過、10 不適用、1 已廢止。真實 Windows NVDA、模態雙向循環、會員／後台的實際 200%／400%、文字間距與直橫向補驗已執行；159 前端測試通過。登入到期提示／延長及已發布非當週內容的完整尋找途徑仍不通過，因此 P0 界線沒有通過。其餘業務來源與正式環境界線保持，下方歷史紀錄不改寫為本輪結果。

2026-10-26 登入後補驗：G34／串接總覽 B15 增會員／後台實際操作與表單語意證據，共用確認框依 WAI 補警示角色、modal 及說明關聯。Node 152、67 頁／81 HTTP 最終通過；[逐項範圍](P0-COMPLETE-VERIFICATION-20261025.md#2026-10-26-登入後與確認框補驗) 沒有把手動可讀當自動播報、初始 DOM 當 API 載入完成，或 P1 消息／優惠後台當 P0 缺陷。反向 Tab／模態末端、完整自動播報、Windows NVDA 與完整 AA 仍保留，階段八未合併。

2026-10-26：G34／串接總覽 B15 已取得有限的真實 Orca／Firefox 語音：首頁結構、會員登入必填警示、註冊確認密碼與同意框未勾選狀態。依 U-19 使用背景隔離桌面，Windows NVDA 不開啟；完整後台讀屏與 Windows NVDA／完整人工 AA 仍未列通過。結果及採證在 [隔離讀屏補驗](P0-COMPLETE-VERIFICATION-20261025.md#2026-10-26-隔離讀屏補驗)，下方歷史狀態保留，不重解 Excel 限制。

2026-10-26 01:20：G34／B15 已補相同 31 個公開／認證頁的實際 400%，與 200% 合計 64 次主要觀察；手機選單最後項與 Escape 焦點返回亦有實際位置。後台／會員的真縮放與實際讀屏不在這組通過範圍，完整人工 AA 仍不宣告通過，詳見 [400% 補驗](P0-COMPLETE-VERIFICATION-20261025.md#2026-10-26-0120-實際-400-縮放補驗)。無新應用修改，部署事項依 U-18。

2026-10-25 22:02：G34／B15 補上 31 個公開／認證頁的實際 200% 重排、表單必填與 Tab 捲動；400% 及讀屏輸出仍未驗完，NVDA 視窗存取授權逾時不等於網站缺陷或通過，helper 已停。範圍與原始觀察見 [實際縮放補驗](P0-COMPLETE-VERIFICATION-20261025.md#2026-10-25-2202-實際-200-縮放補驗)；無應用變更，正式環境另按 U-18 交接。

2026-10-25 21:40：U-18 將 Docker 映像／正式主機事項移至部署交接，網站驗收繼續。G34／B15 補驗 50 頁重排與語意、選單／必填／Google 錯誤／確認框鍵盤，以及 12 類圖片文字對比界限；已修封面 CTA 重疊與館舍圖片遮罩覆蓋。範圍、例外及實際縮放／讀屏狀態見 [介面補驗](P0-COMPLETE-VERIFICATION-20261025.md#2026-10-25-2140-網站介面補驗)，不能將既有「正式／映像未過」直接等同網站功能未完成，也不能把部分無障礙檢查當完整 AA 通過。

2026-10-25 20:15：V-01 對比、V-02 四頁返回、V-03 地圖錯誤關聯已修正並完成本機實測，見 [修正追記](P0-COMPLETE-VERIFICATION-20261025.md#2026-10-25-2015-修正後結果)。Node 152／81 HTTP、50 固定頁桌手機與四表單 320／390 驗證完成；複雜背景與完整人工 AA 不算通過。正式／安全門檻保留，P0 界線未宣告通過；下方為修正前歷史。

2026-10-25 15:21 最新完整輪：來源 `343bd7e`，16 列 E／G／L 的本輪證據與 80 組桌面／手機原型對照見 [完整驗證](P0-COMPLETE-VERIFICATION-20261025.md#16-列-p0-逐項結果)。API／DB、無 JS head、部署還原與回退首次執行通過；真 Google、選檔與會員／場次／唯讀／SEO 交互亦已操作。**P0 仍未通過：V-01 對比、V-02 內容表單上方返回動線、V-03 Google 網址欄位錯誤關聯，以及完整人工 AA／正式與安全門檻。** 下方帶日期矩陣保留先前結果；本輪範圍與未驗項以新報告為準，不抹去歷史 503。

2026-10-25 最新修正：G34 三類小字、館舍時間同源、E30／E32 列表 SEO 已修正且有真實畫面驗收；發布 alias 順序誤判已修，新增真 DB 7／診斷 8 項回歸。見 [修正紀錄](P0-IMPROVEMENTS-20261025.md)。**P0 仍未通過**：前輪優惠首次 503 未有足夠資料追認同一根因，完整 AA／正式環境及映像風險待處理。[16 列複驗](P0-REVERIFICATION-20261025.md#16-列本輪驗證) 與下方表格保留各輪原始結果，不覆蓋首次失敗。Google 真查詢一次、Semgrep／Gitleaks 已執行的是前一複驗輪；此次修正未重跑這些服務／掃描。

更新：2026-10-24；依原始Excel「功能規格」J欄16筆P0；F欄不是必填判定。S＝明文，D＝建議／草案，U＝使用者指定，E＝工程補充。以下為**本機功能證據**，不等於正式交付／完整AA通過。詳見 [階段八紀錄](PHASE-8-ACCEPTANCE.md)。

API皆以 `/api/v1` 為前綴；具體schema見 [OpenAPI](../../backend/resources/openapi.json)。表名／精確路由／分層為工程實作，不聲稱Excel強制指定同名。所有列亦適用共用安全／錯誤與無障礙要求。

| S：功能規格來源 | 實作頁面、API及主要資料表 | 本輪成功／拒絕證據與差項 |
| --- | --- | --- |
| E5／G5／L5 | index；GET /home；attractions／films／film_screenings／news_posts／site_settings／content_publications | home_public_integration：公開同步、空態、資料不混；public_content_security／發布故障拒絕舊快照；圖片替代。正式精選素材待業主。 |
| E6／G6／L6 | museum；GET /museum、/attractions/{slug}；attractions／media_assets | owner_requirements／map_fields／公開內容：電話網址格式、更新日與資料刷新；既有UI較早驗收沿用，本輪全頁HTTP及head重驗，未重新操作全部館舍互動。 |
| E7／G7／L7 | cinema、films/*；GET /films、/films/{slug}；films／film_screenings／media_assets | films_public／screenings／pagination、真兩場次列表→詳情／原型桌手機對照；未發布排除／週界；原型缺年份場次未猜。 |
| E9／G9／L9 | events／activities／city-news、news/*；GET /news、/news/{slug}；news_posts | news_public_integration：分頁全篇、分類、草稿／未到期、空態；拒絕非法分類。前後篇留P1。 |
| E11／G11／L11 | offers、offers/*；GET /offers、/offers/{slug}；offers／merchants／attractions | offers_public_integration：日期邊界、店家／優惠狀態、過期草稿排除；消息不混入。管理CRUD留P1。 |
| E13／G13／L13；U-08／U-11 | explore／food／places/*、favorites／planner；GET /attractions及本人 /me/favorites、/me/itineraries/current 系列；attractions／favorites／itineraries／itinerary_items | attraction與會員3組真DB回歸：分類／座標／slug、本人隔離、版本／重送／下架／重登；真UI收藏／加入／載回／移除。摘要介紹依U-11選填；文字自動補位延後。 |
| E16／G16／L16；串接總覽B10；U-13／U-15 | register／terms／privacy；POST /auth/register、GET /auth/captcha及email challenge；members／captcha_challenges | api_integration／specification_alignment：重複Email、必填確認一致、過期／錯碼／重用／限速／同意版本拒絕；100輪觀察及12CSRF邊界。真讀屏／正式條款待驗。 |
| E17／G17／L17；串接總覽B9 | 兩身分login／forgot／reset／change；/auth及/admin/auth 系列；members／admin_users／sessions／password_reset_tokens／auth_events | 登入、輪替、登出／停權／逾時／限速、reset重用與SMTP失敗、兩命名空間；真UI登入登出、HTTPSCookie演練。正式寄信與可信憑證待驗。 |
| E19／G19／L19 | admin/places；/admin/attractions GET／POST／PUT／DELETE及publish-retry；attractions／attraction_aliases／audit_logs | attractions_integration／corrections／review_regressions：搜索分頁、版本、軟刪／引用保護、授權／audit；UI刪除取消／Escape。路線完整管理未開放。 |
| E20／G20／L20；串接總覽B11/B12；U-14 | admin/place-add／place-edit；/admin/places/resolve-map-url、/admin/media及景點寫入；attractions／media_assets | owner_requirements／opening_hours／map_fields／google_link：既有試填、人工編輯、營業時間、SSRF／redirect／失敗不存；真選檔／空摘要保存。Google簡介僅參考，實連0次；病毒掃描未加入。 |
| E21／G21／L21；U-16 | admin/films／film-add／film-edit；/admin/films CRUD、/admin/media；films／film_screenings／film_aliases／media_assets | films_admin／screenings／persistence：交易／版本／海報alt／同日期開始時間衝突；真UI拒絕重複→修正保存2場→前台。結束時間／場地延後。 |
| E26／G26／L26；串接總覽B14 | admin/admins／admin-add／admin-edit；/admin/admins CRUD；admin_users／admin_role_permissions／audit_logs | api_integration／specification_alignment：固定root及最後管理權保護，越權403，U-13一般管理員自我操作；沒有刪除專頁／假成功。新帳號表單本輪未逐狀態人工重測。 |
| E27／G27／L27 | admin/permissions與左下唯讀模擬；/admin/permissions/catalog、/roles、/admins/{id}/permissions及/auth/preview；permissions／admin_roles／admin_role_permissions | admin_preview／review_regressions：每請求真授權、下個請求生效、範本／防越級與唯讀拒寫；UIroot40格全停用。未用瀏覽器改真帳號權限。 |
| E30／G30／L30；SEO規格E15:E17 | 所有公開HTML／動態詳情／分頁及私人shell；GET /site-settings/public；site_settings／content_seo及各內容表 | seo_head_inventory74HTML、seo_publication：無JShead／自指canonical／OG／noindex／敏感query，失敗不同步回503；正式網域／抓圖待驗，完整JSON-LD／sitemap留P1。 |
| E32／G32／L32 | admin/settings；GET/PUT /admin/settings/seo、GET /site-settings/public；site_settings／content_seo／audit_logs | seo_settings74／seo_publication60：HTTPS／robots／權限／同版／409／清除繼承；真UI422／409保留、reload取消／確認及保存；一般設定不提前開放。 |
| E34:H34／G34／L34；串接總覽B15；D API草案C43:H43 | 共用所有頁／API；新增GET /health；安全應用日誌及audit_logs（無新增system_logs表） | health18項／6真契約；PHP／JS／安全／真MariaDB越權與XSS；手機inert／Escape修正。**AA人工讀屏／完整對比縮放待驗**；映像仍有上游CVE，見安全報告。 |

資料庫：無新增表／欄／migration，既有18份未改，詳見 [逐表規格對照](../DATABASE-SPECIFICATION-MAP.md)。健康只讀migration journal；還原撤銷既有三類授權資料，不新增運維業務表。正式部署以logical dump＋媒體＋manifest組成，見 [操作指南](../P0-OPERATIONS.md)。

P0界線保持未通過：人工AA與正式環境項未完成；歷史未捕捉的註冊事件只保留事實，不能編造已定位。後續可按固定缺口補驗，不再用反覆跑到全綠代替診斷。P1／P2功能不因建立本矩陣而獲授權。
