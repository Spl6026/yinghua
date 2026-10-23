# 會員收藏與基本行程閱讀指南

更新：2026-10-23。來源：功能規格 E13／G15／L15、資料表建議 C19–D21、U-08、E-27。階段六正在實作；本節先記錄 6A 已建立的共用資料層，不把未接入的 API／前端列為可用。

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

build.mjs 與 publish.mjs 統一加入 phase6.css 的必要操作狀態樣式；公共 HTML 沒有會員資料，愛心只由瀏覽器讀本人 API。此時收藏可用，加入／排序／完整行程仍依後續子步驟處理。

6B 驗證：69 個整合檢查（包含逐回應有契約判定）、29 個 JSON 回應契約通過；新增 8 個收藏狀態／競態行為案例，前端共 112 項通過；PHP 共 69 項／270 斷言、PHPStan10／PHPat 與 216 檔格式 dry-run 通過。完整瀏覽器／階段驗收仍待 6D。主／測試庫皆已套用 17 份 migration、31 表。

## 6C 目前行程與基本保存

三個端點為 GET `/api/v1/me/itineraries/current`、PUT／DELETE `/api/v1/me/itineraries/current/items/{attractionId}`。寫入只接受 `version`；owner 來自會員 session，不能由 body 指定。詳細欄位與錯誤回應見 `backend/resources/openapi.json`。總計 68 個 API 操作。

`Controllers/CurrentItineraryController.php` 驗證 HTTP 輸入並轉 DTO，`Services/CurrentItinerary.php` 組織交易及版本規則，`Repositories/ItineraryRepository.php` 保留 SQL 與映射。GET 不會建立行程；第一次加入以 version=0 在同一交易新增父表及站點，失敗整組回滾。寫入依會員、目前行程、景點順序加鎖；並行修改以 409 要求重新載入，已完成的相同加入／移除可安全重送，不增加版本。移除最後一站仍保留父表與版本。

`DTO/CurrentItineraryPage.php`／`ItineraryWriteResult.php` 僅輸出需要的欄位。列表游標綁目前行程 ID、版本及排序位置；跨頁版本變更不拼接舊資料。公開景點投影仍由 MemberAttractions 共用；下架後只顯示先前公開名稱快照與不可瀏覽狀態，不讀出草稿介紹。

`core/current-trip-state.js` 管理會員、列表、保存版本、忙碌狀態與過期回應；保存前須讀取成功，遇到身分變更／409／未知網路結果須重新讀取。`pages/planner.js` 處理真實列表、分頁、移除、重讀及登入後待辦確認；query 不會自動寫入。`components/member-itinerary.js` 安全輸出站點文字，保留原型編號／資訊／移除結構。加入控制由 `core/member-actions.js` 共用，成功才前往 planner。名稱／日期保留原版位置且唯讀，排序及套用推薦路線待完整旅程階段開放，沒有示意行程混入帳號。

```powershell
python backend/tests/member_resources_integration.py --dependency-path artifacts/audit-jsonschema-linux
python backend/tests/member_itinerary_integration.py --dependency-path artifacts/audit-jsonschema-linux
docker compose run --rm --no-deps frontend node --test
docker compose run --rm --no-deps frontend
docker compose exec -T -u www-data app php bin/console.php content:publish
```

上述 dependency-path 為本機忽略的 Linux 測試依賴目錄，於 WSL 執行；Windows 使用 artifacts/audit-jsonschema，其他環境可依 tests/requirements.txt 安裝後省略參數。整合測試只建立 yinghua_test 自有會員／景點，完成後移除收藏／行程與景點、停用測試會員。行程整合 100 項檢查／55 個回應契約通過，包含父子回滾、多輪並行、管理員 session 不能讀會員資料；PHP 69 項／270 斷言及前端 120 項通過。跨模組回歸與實際瀏覽器驗收尚未完成，不能以這些數字代替整階段驗收。

共用入口補充（E-27）：auth.js 登入／註冊互切使用既有 returnTo 白名單保留待辦；attractions.js 支援美食無數字 span 的分類模板，food.html 店家分類使用 API 的在地店家值。content-cards.js／publish.mjs 含愛心的圖片容器使用 group，避免隱藏互動子元素；空營業時間顯示依店家公告，收藏與待辦長文字換行。member-card-accessibility.test.mjs、attraction-food-list.test.mjs 與 auth-navigation.test.mjs 覆蓋這些實際重現情境。

行程排版補充（E-27）：phase6.css 的 grid 子項 min-width:0 及 overflow-wrap:anywhere 保留完整長名稱且避免手機橫向溢出；版面仍沿用原型雙欄／手機單欄。
