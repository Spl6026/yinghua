# 資料表與原始 Excel 對照

2026-11-01 最新：階段十三依使用者授權完成全站搜尋，一個公開GET、同版MariaDB搜尋投影、五類分組／篩選／分頁、原型搜尋頁、錯誤／重試／返回。105 PHP API＋3爬蟲GET＝108 HTTP契約，68固定頁／34表338欄／20 migration；無新env／依賴／服務。230 JS／112 PHP（370斷言）、113真整合／28契約、P1 255／181契約與15筆Browser量測通過，測試資料已清理。詳見[計畫](PHASE-13-PLAN.md)、[驗收](PHASE-13-ACCEPTANCE.md)、[逐檔架構指南](modules/site-search.md)。正式差項沿U-21交接，階段十四／十五未開始；下方日期段落保留歷史。

階段十二的indexModifiedAt是成功快照內部中繼資料，PublishedRecord.updatedAt是DTO屬性，均非新DB欄位。復用既有updated_at與SEO文件時間；沒有新資料表、索引或migration。

2026-10-31 最新：階段十二依授權完成JSON-LD、同版robots／sitemap／分片及P1本機整合。104個PHP JSON API保持，另3個Nginx爬蟲GET，共107個HTTP契約；68固定HTML／33表327欄／19 migration，無新DDL／依賴／env。224 JS／107 PHP、P1 255項與181契約回應、索引369項、官方Validator七型別0錯誤／警告、Browser21筆及40個body保留通過。U-21指定網域未定先完成本機，正式Search Console／Bing／分享仍交接；P2階段十三未開始。詳見[計畫](PHASE-12-PLAN.md)、[驗收](PHASE-12-ACCEPTANCE.md)及[架構與檔案指南](modules/search-index.md)。下方帶日期段落保留歷史。

2026-10-31 最新：階段十一已依使用者授權完成本機範圍，包含本人多份行程、整份編輯保存、時間／備註／排序、推薦路線確認套用及P0資料相容。104 API／68固定頁／33表327欄／19 migration，沒有新DDL／環境。81項真實整合、66筆契約回應零錯誤、107 PHP／208 JS、P0收藏69與目前行程100通過；兩頁各1440／390／320實測並與原型對照。詳見[階段十一驗收](PHASE-11-ACCEPTANCE.md)、[計畫](PHASE-11-PLAN.md)及[檔案閱讀指南](modules/member-itineraries.md)。下方帶日期段落保留歷史；階段十二未開始，P1整體尚未簽核。

2026-10-30 最終：階段十已補完內建 Browser 本機驗收；四頁各1440／390／320共12筆實測，另12筆原型比對。公開導覽選中及刪除後焦點兩項已修、實測與回歸通過。PHP99／342、前端200、路線58／45契約回應零錯誤；96 API／68固定頁／33表327欄／19migration。全站導讀與檔案用途見[推薦路線指南](modules/recommended-routes.md)，實際範圍與共用AA基線界線見[階段十驗收](PHASE-10-ACCEPTANCE.md)。本機範圍通過，依備份／完整tree核對程序合併並保留階段分支；完整會員套用仍留階段十一。下方帶日期段落保留歷史。


2026-10-30：階段十依使用者授權於 `phase/10-recommended-routes` 實作推薦路線管理與公開列表；96 API／68固定頁／33表327欄／19 migration。自動化結果見 [階段十驗收](PHASE-10-ACCEPTANCE.md)，程式閱讀見 [推薦路線指南](modules/recommended-routes.md)。內建 Browser 連線不可用，實際介面待驗，尚未合併 main；完整會員套用留階段十一。下方帶日期段落保留歷史。


2026-10-30：階段九未新增或修改DDL，真MariaDB12.3.3盤點33表316欄／18 migration。沿用資料表建議14–16列的news_posts／merchants／offers及alias／媒體，新增管理SQL／軟刪除，不把草案欄位自動當必填。EXPLAIN沿既有news_public／offer_public／merchant主鍵；少量店家掃描不盲加索引。具體邊界與驗收見[階段九](PHASE-9-ACCEPTANCE.md)。

2026-10-25 15:21 完整驗證：重讀「資料表建議」A2／C5:D26 及 16 列 P0，從主／測試 MariaDB 查核均 **33 表／316 欄／18 migration**。本輪無 DDL 或 migration 變更；schema 空庫／升級／rollback、公開狀態、唯一／外鍵、本人授權與還原保留資料重跑通過。草案與 U-16／U-09／U-11 的差異維持，不新增推定必填；見 [本輪報告](P0-COMPLETE-VERIFICATION-20261025.md#資料表與架構)。具名內容／媒體／收藏行程清理，SEO 兩表與隔離 root 原摘要復原，正常 audit 保留。

2026-10-24 階段八核對：33張表／316欄／18份migration維持，舊migration未改、沒有新增運維業務表。health只讀schema_migrations並驗名稱／checksum／applied_at；system_logs仍以既有安全應用日誌處理，未從建議表名推定必須新建同名表。recovery只撤銷既有sessions／password_reset_tokens／captcha_challenges，帳號、權限、內容、收藏、行程、正常audit保留；隔離還原逐列hash證據見 [階段八紀錄](PHASE-8-ACCEPTANCE.md)。

階段七現況：**32 張業務表＋migration 工具表，共33表／316欄／18 migration**，主／測試庫均已套017。C24–D25 的 site_settings、content_seo 已建立並接三個 SEO API；草案沒有升格為所有欄位必填。site_settings 採 key PK、JSON 預設、published／version／updated_by FK／updated_at；content_seo 採 UUID、資源 enum、resource_id 或 path_pattern 擇一、可空標題／摘要／關鍵字／canonical／robots／分享圖、操作者時間及兩個目標唯一鍵。具體 DDL、160／500 等技術上限、路徑 XOR／enum／FK 為 E-28；og_image_media_id 預留 typed storage，本階段 HTTP 使用 URL 並驗證本站媒體原已公開，不新增上傳權限。原內容 SEO 欄位、member_consents 歷史及舊 migration 保留。見 [017](../backend/database/migrations/017_site_seo.sql)、[指南](modules/seo-settings.md)／[驗收](PHASE-7-ACCEPTANCE.md)。

更新日期：2026-10-24。基準：[原始規格](../映畫竹塹_後台工程串接功能規格.xlsx)「資料表建議」A2、C5–D25，以及功能規格／串接總覽的安全與驗收要求。A2 明示這是建模草案；以下區分對應、工程補充與後續功能，不因表名存在就宣告業務已完成。

階段六當時狀態：**30 張業務表＋1 張 migration 工具表，共 31 表、297 欄、17 份 migration**。015／016 建立 favorites、itineraries、itinerary_items，主／測試庫均已升級；七個本人 API 已接入，完整階段驗收仍進行中。三表依 C19–D21 建議設計，本人唯一約束、current_slot、版本及具體型別為 E-27；不將名稱／日期／時間／備註當成本階段使用者必填欄位。下方較早日期的數量保留當時結果。

階段五當時 **27 張業務表＋1 張 migration 工具表，共 28 表、276 欄、15 份 migration**。2026-10-22 階段五 5A 新增 012–014：擴充 merchants、新增 news_posts／offers 及兩張 alias 表；公開 API／CLI／畫面已接入並完成本機驗收。主庫／測試庫已套用，原 migration checksum 不改，空庫與保留會員／景點／店家的升級已重跑驗證；細節見[公開內容指南](modules/public-content.md)。階段四當時為 24 表／211 欄／12 份 migration，結果見 [驗收](PHASE-4-ACCEPTANCE.md)。

## 原始建議已有對應的資料表

| Excel 工作表／列 | 現有表 | 對應及差異 |
| --- | --- | --- |
| 資料表建議 5 | members | UUID、email 唯一、display_name、password_hash、status、email_verified_at、建立／修改／軟刪除皆已有；last_login_at 為工程補充。驗證碼不等於完成信箱驗證，不偽填 email_verified_at |
| 資料表建議 6 | admin_users | account／email 唯一、顯示名稱、安全密碼摘要、狀態、is_root、last_login_at 與時間欄位均有。role_id、version、permissions_version、deleted_at 是授權／併發／稽核相依。005 改 account 為 utf8mb4_unicode_ci，保留唯一索引；一般帳號可改名，固定 root CHECK／trigger 保留。must_change_password 舊欄暫留工具相容；U-13 明確核准取消首登，007 清零並改預設 0；應用程式不讀取，回應固定 false |
| 資料表建議 7 | roles | id、name、description、is_system、created_at 均對應；固定 root 角色及系統角色有保護 |
| 資料表建議 8 | permissions | module_key＋action_key 唯一，動作為 view／create／edit／delete |
| 資料表建議 9 | admin_role_permissions | admin_id、permission_id、allowed、updated_by、updated_at 與個人唯一鍵對應；實際授權使用個人矩陣，不只依範本 |
| 資料表建議 10 | attractions | 內容、唯一 slug、分類、行政區、介紹、地址、座標、週表、Google 關聯、發布狀態、版本與時間欄位對應。006 補 created_by／updated_by，皆為 admin_users 外鍵；HTTP 不能傳入冒名。既有歷史記錄無回填，允許 NULL；新管理寫入由驗證身分填入 |
| 資料表建議 11 | media_assets | storage_key 唯一；mime_type、尺寸及 created_at 同名。byte_size 對應 size_bytes、alt 對應 alt_text、source 對應 credit、uploaded_by 對應 created_by。public_url 不另存：由受控 storage_key 組成 /media 路徑，避免 URL 與儲存鍵不同步。source／license 可空白；圖片 alt 仍必填 |
| 資料表建議 A12–D12 | films | id、slug、title、genre、synopsis、poster_media_id、status、操作者、version 及時間對應。publish_at 表示預定公開時間，不冒稱首次發布時間；SEO、網址海報、用途說明、電影預設票務、prototype_id（原型編號，由 021 從 legacy_id 改名）／deleted_at 為 E-25。欄位清單不等於必填；片名依原型 required，有圖有效 alt 依 G7／G21 |
| 資料表建議 A13–D13 | film_screenings | id、film_id、starts_at、ticket_url、時間欄對應；starts_at 存 UTC。U-16 只比開始日期與時間，所以本階段沒有 ends_at／venue；場次公開性依父電影狀態，不自創獨立取消狀態。零場次可保存／發布 |
| 資料表建議 A14–D14 | news_posts | C14 的 slug、event／city、標題／摘要／正文、cover_media_id、可空活動起迄、狀態、publish_at、操作者、版本、時間及軟刪除均對應。D14 的 enum／排程查詢與優惠分表已採用；prototype_id（原型編號）、外連封面／用途說明、活動標籤／地點、SEO 為原型與 E-26。正文由 Service 淨化，不在 SQL 偽稱能檢查所有 HTML |
| 資料表建議 15 | merchants | 012 升級為獨立 profile，id PK、可空唯一 attraction_id FK、name／district／address／phone／website／status／version／timestamps／deleted_at。原連結／建立時間保留，一次回填後明確區分優惠聯絡資料與景點；完整管理介面仍在 P1 |
| 資料表建議 A16–D16 | offers | C16 的 slug、merchant_id、title、discount_text／description／terms、cover_media_id、可空 valid_from／valid_to、狀態及軟刪除均對應；G23 條款／店家與 D16 有雙端日期時的順序檢查已落實。published_at 採共用排程欄 publish_at；prototype_id（原型編號）、外連封面／用途、SEO、操作者／版本為 E-26。D16 日期索引是建議；目前投影保留已發布歷史詳情，實際查詢採公開狀態／發布索引，沒有建立未使用的日期索引 |
| 資料表建議 17 | routes | 僅 id／title／status／version，為刪除引用保護所需的最小標頭；完整 slug、theme、summary、duration 等於 P1 路線階段擴充 |
| 資料表建議 18 | route_attractions | route_id、attraction_id、sort_order 與順序／重複引用唯一性對應；suggested_time、note 尚未實作，不因此提前建立完整路線功能 |
| 資料表建議 22 | sessions | member_id／admin_id 擇一、token_hash、期限、撤銷、建立時間與雜湊化來源對應。idle_expires_at、last_seen_at 為期限實作；preview_admin_id 來自 U-06 唯讀模擬 |
| 資料表建議 23 | captcha_challenges | token_hash 對應挑戰識別摘要，answer_hmac 才是答案摘要，兩者不混用；purpose、期限、次數、使用／撤銷時間對應。session_binding_hash、recipient_hash 為綁定實作，IP 速率限制使用共用桶，不保存原始 IP |

## 目前實作採用的名稱與語意

| Excel 建議欄位 | 本案欄位／實作 | 理由 |
| --- | --- | --- |
| attractions.type | category＋type_label | category 是景點／美食／在地店家，type_label 是原型分類補充；不把展示文案誤作另一組授權或發布狀態 |
| attractions.lat／lng | latitude／longitude | 明確欄名；DTO／DB 保留緯度 ±90、經度 ±180 與成對完整性。兩者都可未知 |
| attractions.opening_hours | opening_hours JSON＋hours | U-09 人工／Google 共用七天常態週表；hours 保留舊摘要相容。省略週表不阻擋保存，提供時仍驗證格式 |
| attractions.rating_label | recommendation_label | 保存原型推薦文案，不將文案誤報成評分 |
| attractions.google_maps_url | map_url | 同一用途；手填與匯入均限制為允許的 HTTPS Google 地圖入口 |
| attractions.published_at | publish_at | 現行是預定公開時間，不宣稱是首次實際發布時間；若後續需要記錄實際首次發布，另按功能增加欄位，不虛構歷史時間 |
| attractions.timestamps | created_at／updated_at | MariaDB 保存 UTC，網站依台北時間展示 |
| 景點建立／修改者 | created_by／updated_by | 006 新增 nullable 外鍵；新管理操作由服務層填入，與 audit_logs 完整異動紀錄互補。受控原型匯入無人員身分時保持未知 |
| 欄位選填 | 非空型別與空字串／NULL 各有用途 | 地址、摘要、介紹及圖片來源說明採空字串；NOT NULL 不等於使用者必填。未知行政區保存空字串並受 CHECK 限制，不推測北區；不存在的媒體／操作者 FK 則為 NULL |

保留實際用途清楚且已有 API／外鍵相依的名稱，不為追求草案字面相同而做大量更名。現有景點 q 搜尋仍是參數化 LIKE；Excel D10 的全文索引是草案建議，未直接新增未使用的 FULLTEXT。005 改字元集後，實測登入 account 查詢仍使用 account 唯一索引。

## 工程補充資料表

| 表 | 必要用途／來源 |
| --- | --- |
| role_permissions | E-10：角色起始範本與個人 admin_role_permissions 分開，對應功能規格 E27 的「範本可複製」 |
| auth_rate_limits | E-04：實現串接總覽 B9／B10 限速、限次與失敗鎖定，不存明碼帳號／IP |
| auth_events | 功能規格 I17 提到登入事件；記錄成功／拒絕原因，不包含密碼、Cookie 或驗證碼答案 |
| audit_logs | 功能規格 I19／I20／I26／I27 要審計；append-only 紀錄真正操作者與資源異動 |
| password_reset_tokens | E-03：串接總覽 B9 要密碼重設；只保存 token 摘要、用途、期限與使用狀態 |
| member_consents | E-05／API草案 G17：保存會員同意的條款版本及時間 |
| attraction_aliases | E-18：唯一 slug 的歷史路徑對應，保留舊網址導向及碰撞保護 |
| content_publications | E-18／E-25／E-26：景點／電影／消息／優惠與首頁共同公開 HTML／API 的 generation、成功／失敗及重試狀態，避免保存失敗卻顯示已發布 |
| film_aliases | E-25：電影歷史 slug 路徑對應 UUID；保留舊網址及碰撞保護 |
| news_aliases／offer_aliases | E-26：兩主題各自歷史網址與 owner FK，包含單一 coffee-walk 相容入口；不藉此將優惠寫入消息表 |
| schema_migrations | 工具表，不是業務功能：紀錄 migration checksum、已套用 statement 與完成時間，支援中斷後續跑 |

007_approved_authentication_policy.sql 依 U-13 清除舊旗標、改預設 0；不修改原始已套用 migration。

## Excel 建議表的分階段狀態

階段六 6A–6C 實作更新：favorites／itineraries／itinerary_items 已依 015／016 建立，主／測試均 31 表／17 份 migration；收藏／目前行程均已接入。確切欄位／約束及型別用途見 [會員閱讀指南](modules/member-resources.md)，規格來源為功能規格 G15／資料表建議 C19–D21、工程設計 E-27。以下帶日期的規劃紀錄不代表現在仍未建表；site_settings／content_seo 已於階段七建立，最新狀態見本頁開頭。

2026-10-23 已依 C19–D21 完成 [階段六三表細設](PHASE-6-PLAN.md#4-預計資料表與-migration)：favorites、itineraries、itinerary_items 均仍未建立；current_slot、確切索引／FK、版本／重送及名稱快照為 E-27。預計新增 015／016 兩份 migration，不修改既有檔；實際執行數仍是 15，並未升級資料庫。

news_posts（14）、offers（16）已於階段五建立並接入，欄位來源見 [PHASE-5-PLAN.md](PHASE-5-PLAN.md#4-預計資料表及升級)。itineraries／itinerary_items（19／20）、favorites（21）已於階段六建立並接入基本保存；完整旅程留階段十一。site_settings／content_seo（24／25）已於階段七建立，僅接 SEO，一般設定依階段十四處理。

## Migration 與操作

原有 001、兩支 002、003、004 的內容與 checksum 不變；新增：

- [005_admin_account_specification.sql](../backend/database/migrations/005_admin_account_specification.sql)：帳號字元集及僅固定 root 不可改名的 trigger；005 本身不變更認證政策；U-13 政策另由 007 升級。
- [006_attraction_specification.sql](../backend/database/migrations/006_attraction_specification.sql)：未知行政區、地址空字串預設、兩個操作者欄位、行政區 CHECK 及 FK。

```powershell
docker compose exec -T app-test php bin/console.php db:migrate
# 隔離驗證完成且已備份後才對主庫執行
docker compose exec -T app php bin/console.php db:migrate
```

DDL 參照 [MariaDB ALTER TABLE 官方語法](https://mariadb.com/docs/server/reference/sql-statements/data-definition/alter/alter-table)，並在本案 12.3.3 實測；沒有修改 skill 預設版本來假裝相容。migration 不 DROP／TRUNCATE 業務表，也不將所有選填欄一律改成 NULL。

本機忽略的升級前備份為 `artifacts/before-specification-alignment-yinghua-20261018.sql` 與 `artifacts/before-specification-alignment-yinghua_test-20261018.sql`，含敏感資料，不提交 Git。實際欄位／外鍵清單在 `artifacts/specification-schema-inventory.json`，該清單只有 schema，沒有帳密或資料列。

## 2026-10-19 固定身分再次驗證

[008_root_identity_bytes.sql](../backend/database/migrations/008_root_identity_bytes.sql) 以 BINARY 比較固定身分的文字欄位（E-23），防止 CI collation 忽略大小寫或尾端空白。依 [MariaDB 官方語意](https://mariadb.com/docs/server/reference/sql-functions/string-functions/binary-operator) 實作。001–007 保留原內容；普通管理員仍可改名，密碼及正常登入時間可更新。

16 項隔離 DB 探測全部回滾並通過；主庫先備份再套用，20 張業務表筆數未變；臨時空庫九份 migration／重跑／seed／初始化亦通過。沒有新增表、欄位或索引。實際結果與限制見 [再次檢查報告](FULL-RECHECK-20261019.md)。

## 2026-10-19 G6 電話與 E20 範例

[009_attraction_phone.sql](../backend/database/migrations/009_attraction_phone.sql) 僅增加 attractions.phone VARCHAR(80) NOT NULL DEFAULT ''；電話選填，原有內容預設空字串，不猜電話。phoneUrl由程式衍生，不是重複儲存欄。001–008保持原內容。樣本查詢使用legacy_id（現名prototype_id）唯一欄位、狀態／時間與soft delete條件，沒有新增索引或業務表。

兩庫已先備份至被忽略的before-owner-phone-*-20261019.sql再升級；還原主庫舊備份後逐筆比較六筆景點全部原有欄位，完全一致。空庫十份migration、重跑、seed、固定管理員初始化與六筆範例亦通過；臨時庫及專用授權已移除。MariaDB12.3.3實際EXPLAIN選用attraction_admin索引、估計六筆；小型固定範例的filesort不構成新增索引理由。performance_schema仍OFF，沒有為此重啟資料庫或宣稱效能壓測通過。

## 2026-10-19 階段四：電影與場次（U-16／E-25）

010 建立 films（22 欄）與 film_screenings（6 欄），011 建立 film_aliases（2 欄）；合計新增 30 欄。原始 Excel 仍是建模草案，欄位省略／空值與 API 規則見 [電影計畫](PHASE-4-PLAN.md) 的逐欄來源。

- films：title 必須非空由 DTO 驗證；synopsis／genre／poster_url／ticket_url／SEO 可為空字串，媒體 FK 與用途覆寫以 NULL 表示未選／繼承。NOT NULL 不自動等於業務必填。poster_media_id 與非空 poster_url 不能並用；媒體存在由 FK，所屬 films 模組與有效 alt 由服務政策驗證。
- film_screenings：film_id FK，UNIQUE(film_id,starts_at) 防同片重複。跨電影比較依 U-16 在共享寫入鎖及交易內執行；草稿占用、下架釋放，故不把 starts_at 單獨做全域唯一鍵。這個狀態策略及分鐘精度是 E-25，不冒稱 Excel 明文。
- 既有場次 ID 只能屬於同電影；集合更新保留 ID／created_at，updated_at 依本次寫入更新。父電影 version 防止不同人互相覆蓋整組場次，場次及稽核故障皆回滾。
- film_aliases：path 主鍵、film_id FK；新舊 slug 都保留，只有目前已發布電影可解析為站內 canonical。prototype_id（原型編號）使用原型 0-based 對照，不用陣列位置替代 UUID。
- 仍沒有 ends_at、venue 或電影／場次另套權限表。每場票務空白沿用電影預設，只做外連；D13 的結束大於開始與白名單仍是草案，未擅自增加發布必填或營運網域白名單。

MariaDB 12.3.3 實際 EXPLAIN：管理排序選 film_admin；公開查詢選 film_admin 並有 filesort；場次批次選 film_start 的 range；當次開始時間碰撞查詢以 film_admin＋film_start 查找。樣本小，不把估計 1–2 筆當成大資料效能證明，未另增索引或壓測宣稱；screening_start 保留開始時間索引用途。performance_schema 為 OFF，沒有為此重啟共用資料庫。

升級前主／測試庫備份：被 Git 忽略的 artifacts/phase4-before-migrations-20261019.sql。`film_schema_upgrade.py` 新建隨機 fresh／upgrade 測試庫，驗證 24 表／12 migration、重跑、最少電影、FK／同片唯一及舊會員／景點保留；最後清除自有測試庫與暫時授權，沒有 DROP 使用者主庫／固定測試庫。正常升級沿用 db:migrate，完整命令見驗收與全站導讀。

## 階段十三搜尋投影（E-35）

功能規格E18/G18/L18要求搜尋；資料表建議未指定搜尋表。新增019_public_search.sql／search_documents共11欄，公開generation／type／resource_id主鍵及文字／URL白名單，無業務外鍵或新增編輯限制。舊19份migration不變，總34表338欄／20份。EXPLAIN實測後移除未使用額外索引，只留PRIMARY；內容／媒體／設定仍是來源，還原後content:publish重建。來源與逐欄用途見[搜尋指南](modules/site-search.md)。
