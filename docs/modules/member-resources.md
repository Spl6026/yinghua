# 會員收藏與基本行程閱讀指南

2026-10-31：階段十一已完成完整多份行程及確認後套用推薦路線，沿用同一itineraries／itinerary_items資料；新8操作、版本與草稿、逐檔用途見[完整會員行程指南](member-itineraries.md)。本文件較早「留階段十一」段落為階段六／十當時的交付界線。

更新：2026-10-24。來源：功能規格 E13／G15／L15、資料表建議 C19–D21、U-08、E-27。階段六 6A–6C 已建立，七個本人 API 與前端已接入；6D 自動化及 Brave 桌面／手機實測完成，範圍與未驗環境分列。以下依資料層、收藏及目前行程說明，最新狀態見 [驗證紀錄](../PHASE-6-ACCEPTANCE.md)。

## 6A 資料層與本人身分

`015_member_favorites.sql` 建立 favorites，PK(member_id,attraction_id) 保護不重複收藏，會員＋時間索引支援本人游標列表。`016_member_itineraries.sql` 建立 itineraries 與 itinerary_items；同一會員最多一份 current_slot=1，其他 NULL 槽留給未來多份行程。行程項目以兩個唯一鍵分別保護景點不重複與順序不重複。

`Repositories/FavoriteRepository.php` 專管本人收藏的存在、批次狀態、列表與增減。`ItineraryRepository.php` 管目前行程／findOwned／有序項目，所有查詢都包含 member_id；知道別人的 itineraryId 也讀不到。這些 Repository 不自行 commit，MAX(sort_order)+1 必須由上層在父表鎖內使用。

`Services/MemberAccess.php` 用既有 AccountRepository 重查有效會員；寫入時配合外層交易鎖會員。它不使用只適用後台的 Authorization，也不把管理員登入轉成會員登入。

`MemberItinerary`／`ItineraryStop`／`FavoriteRecord` 是不可變資料；`MemberPageInput`／`FavoriteStatusInput`／`ItineraryItemInput` 驗證頁面資源、UUID 與非負版本，不允許 memberId 或完整行程編排欄位寫入。伺服器回應不直接序列化內部 memberId。

名稱快照取加入當時公開名稱；時間／備註儲存位置供後續完整旅程沿用。本階段預設名稱、NULL 日期／時間及具體型別是 E-27，不是新增客戶必填規則。

## 資料層驗證命令

PHP 及品質工具在 Docker 執行；WSL 引擎可用時，先切到 `/mnt/c/Users/spl/Desktop/project` 再執行相同命令。

```powershell
docker compose exec -T app-test php bin/console.php db:migrate
docker compose exec -T app-test php tests/member_resources_persistence.php
docker compose exec -T app php /var/www/tools/vendor/bin/phpunit --filter MemberInputTest
python backend/tests/member_resources_schema.py
```

資料層案例只在 yinghua_test，finally 回滾自有會員／景點。空庫／升級協調工具只建立隨機 `yinghua_p6_*` schema，確認重跑安全、保留舊會員／景點後刪除自有 schema，不清除主庫或測試庫。

2026-10-23 6A 實測：新空庫及保留舊資料升級均為 31 表／17 份 migration，重跑不破壞舊會員／景點、臨時 schema 已清理；持久層 25 項檢查及輸入單元 5 項／7 斷言通過，PHPStan level 10／PHPat 與 208 檔格式 dry-run 通過。這只涵蓋共用資料層，尚未開放收藏／行程 API 或頁面。

## 6B 收藏 API 與共用愛心

`config/member-routes.php` 由 Composition 明確載入，先註冊固定的 status 路徑，再註冊 attractionId。四個 API 均以會員 session 守門；FavoriteController → MemberFavorites → FavoriteRepository 完成本人收藏列表、批次狀態、明確 PUT 收藏與 DELETE 取消。唯一鍵與會員交易鎖使重送不反轉；已下架的本人收藏仍可取消。

`MemberAttractions` 把既有 ContentPublication 的成功投影加入私人記錄。生成中回 availability=unknown，已下架則 unavailable；兩者都不從草稿補任何公開資料。新增時還會鎖定重查景點狀態／公開版本；私人修改不觸發發布。

前端 `api/member-resources-api.js` 沿用既有 api-client；`core/member-resource-state.js` 協調身分／批次狀態、同項互斥、舊回應與網路結果未知，`core/member-actions.js` 綁定所有受管景點愛心及登入待辦。列表重畫後呼叫 mountMemberActions，登入待辦只顯示確認，不因 URL 自動寫入。`pages/favorites.js`／favorites.html 提供本人列表、取消、失效項與分頁；我的收藏入口位於會員選單。原卡片資料映射改為共用 attractionCardData，沒有改原型 style.css。

`auth.js` 的登入／註冊互切連結先用 session.returnTo 的既有白名單，再編碼為 query，保持原先待辦；不自動收藏或加入。`pages/attractions.js` 同時支援 explore 有數字與 food 無數字的分類按鈕，店家分類使用 API 的「在地店家」。`content-cards.js` 及 publish.mjs 將有愛心的圖片容器標為 group，讓按鈕保留可及性；沒有營業時間時顯示既有「依店家公告」。這些為 E-27 的共用入口整合，未改業務必填限制。

build.mjs 與 publish.mjs 統一加入 phase6.css 的必要操作狀態樣式；公共 HTML 沒有會員資料，愛心只由瀏覽器讀本人 API。此時收藏可用，加入／排序／完整行程仍依後續子步驟處理。

6B 驗證：69 個整合檢查（包含逐回應有契約判定）、29 個 JSON 回應契約通過；新增 8 個收藏狀態／競態行為案例，前端共 112 項通過；PHP 共 69 項／270 斷言、PHPStan10／PHPat 與 216 檔格式 dry-run 通過。完整瀏覽器／階段驗收仍待 6D。主／測試庫皆已套用 17 份 migration、31 表。

## 6C 目前行程與基本保存

三個端點為 GET `/api/v1/me/itineraries/current`、PUT／DELETE `/api/v1/me/itineraries/current/items/{attractionId}`。寫入只接受 `version`；owner 來自會員 session，不能由 body 指定。詳細欄位與錯誤回應見 `backend/resources/openapi.json`。總計 68 個 API 操作。

`Controllers/CurrentItineraryController.php` 驗證 HTTP 輸入並轉 DTO，`Services/CurrentItinerary.php` 組織交易及版本規則，`Repositories/ItineraryRepository.php` 保留 SQL 與映射。GET 不會建立行程；第一次加入以 version=0 在同一交易新增父表及站點，失敗整組回滾。寫入依會員、目前行程、景點順序加鎖；並行修改以 409 要求重新載入，已完成的相同加入／移除可安全重送，不增加版本。移除最後一站仍保留父表與版本。

`DTO/CurrentItineraryPage.php`／`ItineraryWriteResult.php` 僅輸出需要的欄位。列表游標綁目前行程 ID、版本及排序位置；跨頁版本變更不拼接舊資料。公開景點投影仍由 MemberAttractions 共用；下架後只顯示先前公開名稱快照與不可瀏覽狀態，不讀出草稿介紹。

`core/current-trip-state.js` 管理會員、列表、保存版本、忙碌狀態與過期回應；保存前須讀取成功，遇到身分變更／409／未知網路結果須重新讀取。`pages/planner.js` 處理真實列表、分頁、移除、重讀及登入後待辦確認；query 不會自動寫入。`components/member-itinerary.js` 安全輸出站點文字，保留原型編號／資訊／移除結構。加入控制由 `core/member-actions.js` 共用，成功才前往 planner。名稱／日期保留原版位置且唯讀，排序及套用推薦路線待完整旅程階段開放，沒有示意行程混入帳號。

`phase6.css` 的 grid 子項 min-width:0 與 overflow-wrap:anywhere 防止合法長名稱把手機頁面撐寬，不截斷或改寫保存名稱；收藏卡片及待辦提示共用相同換行規則。

```powershell
python backend/tests/member_resources_integration.py --dependency-path .cache/validation/python
python backend/tests/member_itinerary_integration.py --dependency-path .cache/validation/python
docker compose run --rm --no-deps frontend node --test
docker compose run --rm --no-deps frontend
docker compose exec -T -u www-data app php bin/console.php content:publish
```

上述 dependency-path 為本機忽略的驗證套件快取，依 tools/validation/requirements.txt 安裝。Windows／WSL及不同Python小版本需各自建立相容原生擴充，不能共用同一份二進位套件；其他環境也可安裝至自己的venv後省略參數。整合測試只建立 yinghua_test 自有會員／景點，完成後移除收藏／行程與景點、停用測試會員。6C 行程整合 100 項檢查／55 個回應契約通過，包含父子回滾、多輪並行與新增／移除競爭、管理員 session 不能讀會員資料；當次 PHP 69 項／270 斷言及前端 120 項通過。最新跨模組、前端與實際瀏覽器結果見下方 6D。

## 6D 自動化與本機瀏覽器驗收

最新前端共 129 項通過。`tests/member-pages.test.mjs` 執行真實頁面腳本、替換 DOM／API 邊界；`member-intent.test.mjs` 驗證重複掛載與載入中取消，不能代替瀏覽器排版。`backend/tests/member_resources_queries.php` 以 160 筆自有資料取得真 MariaDB EXPLAIN／ANALYZE 後回滾；`member_resources_restart.py` 只重建 app-test／web-test，驗證 session 與私人資料保留；`member_publication_integration.py` 做五輪發布前 unknown／發布後新資料及舊名稱快照驗證。`phase6_regression.py` 跑既有 21 組契約與階段五五組補充，正常請求採前端既有的一次 CSRF 更新重試；刻意錯誤 token／來源檢查不重試。

新增 [member-card-accessibility.test.mjs](../../frontend/tests/member-card-accessibility.test.mjs) 執行卡片元件，確認愛心不在 img 語意之下與空時間可讀；[attraction-food-list.test.mjs](../../frontend/tests/attraction-food-list.test.mjs) 執行列表模組並使用實際美食模板分類，覆蓋無數字 span 與店家值；[auth-navigation.test.mjs](../../frontend/tests/auth-navigation.test.mjs) 執行認證／session 模組確認互切待辦及不安全目的地拒絕。這些保護真實重現情境，不宣稱替身能驗 CSS。實際手機長名稱則以瀏覽器量測並截圖。

結果、命令、環境中斷、間歇事件與 6-T01–15 的未驗部分集中記在 [階段六紀錄](../PHASE-6-ACCEPTANCE.md)。2026-10-24 補完 Brave 桌面 1440×900／手機 390×844 與鍵盤驗收，並記錄工具逾時及應用程式結果的區別；未宣稱其他瀏覽器或正式部署通過。原始 69 檔、CSS、忽略的 .env 與所有本機文件連結另有檢查；沒有為本階段新增容器服務、PHP／JS 套件、port 或環境變數。
