# P0：Excel來源與實作／驗證矩陣

更新：2026-10-24；依原始Excel「功能規格」J欄16筆P0；F欄不是必填判定。S＝明文，D＝建議／草案，U＝使用者指定，E＝工程補充。以下為**本機功能證據**，不等於正式交付／完整AA通過。詳見 [階段八紀錄](PHASE-8-ACCEPTANCE.md)。

API皆以 `/api/v1` 為前綴；具體schema見 [OpenAPI](../backend/resources/openapi.json)。表名／精確路由／分層為工程實作，不聲稱Excel強制指定同名。所有列亦適用共用安全／錯誤與無障礙要求。

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

資料庫：無新增表／欄／migration，既有18份未改，詳見 [逐表規格對照](DATABASE-SPECIFICATION-MAP.md)。健康只讀migration journal；還原撤銷既有三類授權資料，不新增運維業務表。正式部署以logical dump＋媒體＋manifest組成，見 [操作指南](P0-OPERATIONS.md)。

P0界線保持未通過：人工AA與正式環境項未完成；歷史未捕捉的註冊事件只保留事實，不能編造已定位。後續可按固定缺口補驗，不再用反覆跑到全綠代替診斷。P1／P2功能不因建立本矩陣而獲授權。
