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
