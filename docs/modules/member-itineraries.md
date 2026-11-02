# 完整會員行程閱讀指南

更新：2026-10-31。階段十一；規劃及來源見[計畫](../PHASE-11-PLAN.md)，實際測試見[驗收](../PHASE-11-ACCEPTANCE.md)。原型 `website/planner.html` 保留；實作只放 `frontend/`、`backend/`。

## 先理解資料流

瀏覽器 planner → `itineraries-api.js` → API Controller → `MemberItineraries` Service → `ItineraryRepository` → MariaDB。Controller只處理HTTP及DTO；Service組織所有權、交易、版本與可用性；Repository只執行綁參數SQL。`Composition.php`建構子注入依賴。沿用[架構文件](../ARCHITECTURE.md)既有Service Layer／Repository／DTO慣例，沒有PHP HTML View或另一套會員身分系統。

畫面有兩份狀態：`trip`為伺服器已保存版本，`draft`為目前輸入。改名稱、日期、站點時間、備註、上移／下移／移除只改草稿；按「儲存行程」才整份交易保存。景點詳情的加入入口導向planner待確認，選定行程後按「加入並儲存至此行程」。推薦路線GET只預覽，按套用並確認才替換名稱與站點，保留日期。

## 檔案用途

| 檔案（相對專案根目錄） | 用途 |
| --- | --- |
| backend/config/routes.php | 七個新會員操作；在路由表中排在P0靜態current之後，避免泛用id攔走current。 |
| backend/src/Controllers/MemberItineraryController.php | 集合、單筆、新增、全量更新、刪除、切換、套用的HTTP入口。 |
| backend/src/Services/MemberItineraries.php | 重查本人、鎖會員及行程、版本比較、去重建立、全量保存、套用公開路線。 |
| backend/src/DTO/TripInput.php、TripStopInput.php | 輸入白名單、UUID／日期／時間／長度、重複景點。順序依items陣列位置。 |
| backend/src/DTO/TripView.php、TripCreated.php | 安全輸出整份行程；建立結果區分新建201與同ID重試200。 |
| backend/src/DTO/Input.php | 共用objects增加可傳容量上限；其他呼叫仍用原100。 |
| backend/src/Repositories/ItineraryRepository.php | 本人集合及站點、建立／保存／切換／刪除；同會員單調版本。 |
| backend/src/Services/MemberAttractions.php | 成功公開投影及即時景點驗證；批次固定ID次序鎖定，避免每站重讀投影。 |
| backend/src/Services/PublicRecommendedRoutes.php、Controllers/RecommendedRouteController.php、config/routes.php | 公開路線單筆預覽；既有列表不變。 |
| backend/src/Composition.php、backend/phpstan.neon | 組裝與路由品質分析範圍。 |
| frontend/pages/planner.html、assets/css/phase11.css | 保留原型hero、左右欄、編號卡片；補本人選單、頁碼、編輯／確認控制及手機重排。 |
| frontend/assets/js/pages/planner.js | DOM、確認框、欄位錯誤、未保存提醒、地點／路線意圖及登入恢復。 |
| frontend/assets/js/core/trip-editor-state.js | 伺服器／草稿分離、身分前後檢查、409與不確定結果保留、建立去重ID。 |
| frontend/assets/js/components/trip-stop-editor.js | 原型站點卡片、安全文字、時間／備註label及上下移除控制。 |
| frontend/assets/js/api/itineraries-api.js | 沿用共用Cookie／CSRF／API錯誤客戶端，沒有第二套HTTP工具。 |
| frontend/assets/js/core/member-actions.js | 詳情加入導向待確認planner；收藏原流程保持。 |
| frontend/assets/js/core/route-view.js | 公開路線恢復原型套用連結。 |
| frontend/assets/js/public.js | 完整planner由模組處理，舊示範toast／導航不再重複攔截。 |
| backend/resources/openapi.json | 104個操作；新8個API及安全輸出／輸入契約。 |
| backend/tests/phase11_fixture.py | 只建立／清理自有yinghua_test兩會員及既有隔離內容。 |
| backend/tests/phase11_integration.py、phase11_contracts.py、phase11_queries.php | 真HTTP／MariaDB越權並行與持久化、獨立契約、回滾式EXPLAIN。 |
| backend/tests/Unit/TripInputTest.php、frontend/tests/trip-editor-state.test.mjs | 邊界及非同步草稿狀態回歸；member-pages／route-view舊測試同步新行為。 |

## API與資料庫

新增8個操作列於[計畫API表](../PHASE-11-PLAN.md)。集合每頁預設20、最多100；選擇行程會更新本人current。DELETE帶JSON version，成功204沒有JSON；刪目前行程會選下一份，最後一份刪除則回空狀態。所有會員API強制member session；admin Cookie不能代替，memberId不接受客戶端指定。

沿用migration `016_member_itineraries.sql`的`itineraries`／`itinerary_items`，本階段無新表、欄位或migration。`tripDate→trip_date`、`startTime→planned_time`、`note→note`；`sort_order`依陣列順序重排；原有不可用站保留`id`及`snapshot_name`，新增不可用站拒絕。父子同交易，錯誤不留半份。保留軟刪父列及版本，避免刪除後重用舊版本。舊P0目前行程API讀寫同一資料，沒有複製／遷移來源。

E-33：先鎖會員列，再鎖行程／景點；每次修改版本大於同會員全部歷史版本。切換不碰另一會員；舊分頁只送version的P0請求不能誤改新current。POST可選UUID供重試去重，不是使用者要填的業務欄位；同本人已建立ID只載回舊結果，不覆寫。

來源基準：功能規格E15/F15/G15/L15、E14/G14/L14/E24/G24/L24，API草案C21:G24、資料表建議C19:D20；後兩者為D。名稱／日期原型未required，允許空行程。200字名稱、2000字備註、1000站點、64KiB JSON及確認／版本策略為E-33工程邊界，不新增業務必填或時間碰撞限制。

## 環境與操作

PHP 8.5／MariaDB 12.3／Docker沿用現有環境；33表327欄19 migration、68固定HTML、104 API。沒有新env、依賴、容器、公開DB port或正式設定。公開資料仍經成功generation，發布暫時失敗不從私人草稿取資料。查詢證據及索引取捨見驗收，不冒稱大規模壓測。

```powershell
docker compose run --rm --no-deps frontend
docker compose run --rm --no-deps frontend node --test tests/*.test.mjs
docker compose exec -T app-test /var/www/tools/vendor/bin/phpunit -c phpunit.xml
docker compose exec -T app-test /var/www/tools/vendor/bin/phpstan analyse -c phpstan.neon --no-progress
python -X utf8 backend/tests/phase11_fixture.py
python -X utf8 backend/tests/phase11_contracts.py --dependency-path .cache/validation/python
docker compose exec -T app-test php tests/phase11_queries.php
python -X utf8 backend/tests/phase11_fixture.py --cleanup
```

先準備fixture再執行整合；整合會清自有兩會員行程，勿與同fixture的Browser同時操作。測試密碼／manifest／截圖只放被忽略artifacts，測後撤銷session、停用自有帳號並清理內容，不改真實帳號。401保留同本人草稿、重新驗證後再由本人操作；更換會員會清私人草稿。409／結果不確定不自動重送，重新載入先確認捨棄。
