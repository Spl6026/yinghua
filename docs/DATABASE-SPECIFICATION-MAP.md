# 資料表與原始 Excel 對照

更新日期：2026-10-19。基準：[原始規格](../映畫竹塹_後台工程串接功能規格.xlsx)「資料表建議」A2、C5–D25，以及功能規格／串接總覽的安全與驗收要求。A2 明示這是建模草案；以下區分對應、工程補充與後續功能，不因表名存在就宣告業務已完成。

目前 **20 張業務表＋1 張 migration 工具表**。五份校正 migration 不新增表；隔離 MariaDB 12.3.3 實際檢查為 21 表、181 欄、10 份已完成 migration。主庫也已套用並核對 20 張業務表筆數保留；完整驗收另見 [校正交付](SPECIFICATION-ALIGNMENT.md)，不以隔離結果代替主庫結果。

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
| 資料表建議 15 | merchants | 階段三僅 attraction_id FK＋created_at，作美食／店家標記；名稱、行政區、地址與官網由關聯 attractions 取得，不建立兩份會失去同步的資料。此表尚非完整優惠合作店家管理，phone／店家生命週期等留對應業務階段，不冒稱已完成整列建議 |
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
| content_publications | E-18：公開 HTML 與 API 的 generation、成功／失敗及重試狀態，避免保存失敗卻顯示已發布 |
| schema_migrations | 工具表，不是業務功能：紀錄 migration checksum、已套用 statement 與完成時間，支援中斷後續跑 |

007_approved_authentication_policy.sql 依 U-13 清除舊旗標、改預設 0；不修改原始已套用 migration。

## Excel 建議但尚未開始的業務表

films／film_screenings（列 12／13）、news_posts（14）、offers（16）、itineraries／itinerary_items（19／20）、favorites（21）、site_settings／content_seo（24／25）尚未實作。依 [實作計畫](IMPLEMENTATION-PLAN.md) 在相應 P0／P1／P2 階段建立，不因本次資料庫核對提前建立空業務或宣告串接完成。

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

[009_attraction_phone.sql](../backend/database/migrations/009_attraction_phone.sql) 僅增加 attractions.phone VARCHAR(80) NOT NULL DEFAULT ''；電話選填，原有內容預設空字串，不猜電話。phoneUrl由程式衍生，不是重複儲存欄。001–008保持原內容。樣本查詢使用legacy_id唯一欄位、狀態／時間與soft delete條件，沒有新增索引或業務表。

兩庫已先備份至被忽略的before-owner-phone-*-20261019.sql再升級；還原主庫舊備份後逐筆比較六筆景點全部原有欄位，完全一致。空庫十份migration、重跑、seed、固定管理員初始化與六筆範例亦通過；臨時庫及專用授權已移除。MariaDB12.3.3實際EXPLAIN選用attraction_admin索引、估計六筆；小型固定範例的filesort不構成新增索引理由。performance_schema仍OFF，沒有為此重啟資料庫或宣稱效能壓測通過。
