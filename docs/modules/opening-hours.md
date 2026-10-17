# 共用營業時間閱讀指南

2026-10-18。來源 U-09：人工及 Google 匯入以相同方式填每週時間；E-20：具體資料結構與限制。原規格景點／店家及地圖匯入功能沿用 S-013／19／20，不能把本次工程格式宣稱為 Excel 原訂欄位。

## 資料怎麼流動

手動填寫 → opening-hours-editor.js → attraction-form.js 的 openingHours → AttractionInput → OpeningHours／OpeningDay／OpeningPeriod → AttractionManagement 的既有授權、版本與交易 → AttractionRepository → attractions.opening_hours → 公開 projection → publish.mjs／opening-hours.js → 無 JS 也可讀的詳情。

Google URL → 原有安全 HTTP／網域檢查 → MapResolver → GoogleOpeningHours Adapter → 相同 openingHours 候選 → 確認帶入相同編輯器 → 人工核對與儲存。解析 API 不存景點，也不新增外部端點或金鑰。由建構子注入 Adapter，DTO 不依賴 Google、HTTP 或資料庫。

## 每個新檔案

| 檔案 | 責任 |
| --- | --- |
| [OpeningDayStatus.php](../../backend/src/Models/OpeningDayStatus.php) | unknown／closed／all_day／periods enum，未知不視為公休 |
| [OpeningPeriod.php](../../backend/src/DTO/OpeningPeriod.php) | HH:mm、次日、正長度且最多24小時；給定分鐘區間與文字 |
| [OpeningDay.php](../../backend/src/DTO/OpeningDay.php) | 星期、有限狀態與最多四段；驗證狀態／時段一致，排序時段 |
| [OpeningHours.php](../../backend/src/DTO/OpeningHours.php) | 七個不重複星期、台北時區、備註；跨日／跨週重疊檢查，產生短摘要 |
| [GoogleOpeningHours.php](../../backend/src/Adapters/GoogleOpeningHours.php) | 外部 periods 轉成相同 DTO；24/7、跨日／連續多日、缺資料安全處理 |
| [004_opening_hours.sql](../../backend/database/migrations/004_opening_hours.sql) | 只新增 nullable JSON；不改舊 migration、不推測或覆寫舊文字 |
| [opening-hours.js](../../frontend/assets/js/core/opening-hours.js) | 空週表與可讀每日文字，供後台和 Node 公開產生器重用 |
| [opening-hours-editor.js](../../frontend/assets/js/components/opening-hours-editor.js) | 原生欄位、七天、多段、次日、備註；載入／讀取／唯讀鎖定 |
| [OpeningHoursTest.php](../../backend/tests/Unit/OpeningHoursTest.php) | DTO 與提供者邊界、未知／公休、跨日／週、格式與重疊 |
| [opening_hours_integration.py](../../backend/tests/opening_hours_integration.py) | 隔離 MariaDB／HTTP／SSR、越權／唯讀、版本與失敗不改資料 |
| [opening-hours.test.mjs](../../frontend/tests/opening-hours.test.mjs) | 每日文案／星期映射及舊內容語意 |
| [opening-hours-editor.test.mjs](../../frontend/tests/opening-hours-editor.test.mjs) | 真正元件的人工／匯入讀回、隱藏必填、唯讀、上限及移除焦點 |

## 契約與例子

API 仍是原有 attractions POST／PUT／GET，新增 openingHours。timezone 固定 Asia/Taipei；days 必須包含星期 0–6 各一次，0 是週日，顯示從週一開始。note 最多500字。每段 opens／closes 採24小時 HH:mm，nextDay 為布林。

```json
{"day":1,"status":"periods","periods":[
  {"opens":"09:00","closes":"12:00","nextDay":false},
  {"opens":"22:00","closes":"02:00","nextDay":true}
]}
```

表示週一上午營業，午間休息，週一晚間再營業到週二02:00。跨日時段歸開始日；相鄰日／跨週若另有重疊時間會被拒絕，接續不重疊的邊界可通過。選全天或公休時 periods 必須空，未知同樣空。隱藏的時段輸入停用，避免瀏覽器 required 阻擋保存。

舊資料 openingHours 回 null，原 hours 照舊顯示。管理員打開時舊字串放入備註，七天預設未知，保存後才產生週表。不推測「09:00–17:00」是每天。新前端一律送週表；舊 API 客戶端省略它時保留既有 JSON 與結構化摘要，未轉換的舊內容仍可沿用舊文字。提供週表時伺服器負責產生 hours 摘要，不能由另一份字串改掉。

API 的一日最多四段及格式限制是工程補充，Google 回應超過限制／不完整／重疊會退回未知，保留 weekdayDescriptions 文字供人工核對（至多500字）。不解析語系文字、也不假定 periods 陣列順序。缺 periods 與明確空 periods 有不同語意；近期 currentOpeningHours 不當作常態排程。

卡片與後台清單維持短摘要；每天相同時顯示每日時間，不同時提示詳情，未知時顯示備註或依店家公告。詳情包含七天與備註，全部 HTML 編碼。沒有實作「現在營業中」、特殊節日資料表或自動同步。

## 環境與驗證

先備份開發資料庫，`docker compose exec -T app php bin/console.php db:migrate`；建置 frontend，`docker compose restart publisher`，以 www-data 執行 content:publish。測試庫也執行 migration／content:publish。NodeContentRenderer 的版本包含共用 opening-hours.js，調整顯示規則會令舊 generation 失效，避免重用舊 HTML。

HTTP 測試會初始化同一個隔離管理員密碼，**依序執行，不能並行**；不清空正式帳號或所有測試資料。`--keep-for-browser` 僅保留本次自有測試筆供畫面驗收，驗收後透過既有軟刪除清理。密碼只在忽略的 artifacts，不能列印或提交。

其他匯入欄位的已確認問題見 [OPENING-HOURS-PLAN.md](../OPENING-HOURS-PLAN.md)，本次沒有擴增成地址、分類或 Google 關聯管理功能。實際測試與 UI 結果見階段三驗收。
