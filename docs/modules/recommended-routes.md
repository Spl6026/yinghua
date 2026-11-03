# 推薦路線程式閱讀指南

2026-10-31：階段十一已完成完整多份行程及確認後套用推薦路線，沿用同一itineraries／itinerary_items資料；新8操作、版本與草稿、逐檔用途見[完整會員行程指南](member-itineraries.md)。本文件較早「留階段十一」段落為階段六／十當時的交付界線。

2026-10-30。階段十已完成本機功能與實際 Browser 範圍驗收，依Git流程交付；見 [計畫](../archive/PHASE-10-PLAN.md)／[結果](../archive/PHASE-10-ACCEPTANCE.md)。來源 S 為功能規格 E14:G14/L14/E24:G24；資料表草案 D C17:D18；具體工程決策 E-32。

先看 `RecommendedRouteInput::from()`：JSON 白名單、名稱、選填資料、發布狀態與至少一站；`RouteStopInput` 驗證每站 UUID、HH:mm及備註。HTTP 不接受 sortOrder 站點欄位；陣列順序就是站點次序。

再看 `RecommendedRoutes::save()`：重新驗證 routes 權限 → exclusive 發布鎖 → transaction → 鎖主檔與版本 → 鎖有效景點 → Repository保存主檔及站點 → 稽核 → commit → 同步公開 HTML。`RouteWriteResult` 明確區分保存與發布，202不可當成未保存重送。刪除軟刪主檔並解除站點引用；不刪會員行程。

公開資料經 `RecommendedRouteSelection::project()`，用同快照的已發布景點名稱及網址，跳過下架站，零有效站不公開。`PublicRecommendedRoutes` 只讀成功快照，與其他內容一樣在代數不符時503；不讓 API 與 HTML 各讀不同版本。

## 檔案責任

| 檔案 | 用途 |
| --- | --- |
| backend/database/migrations/018_recommended_routes.sql | 擴充兩張既有表、回填穩定 slug、排序唯一約束及公開索引；舊 migration 不變。 |
| backend/src/DTO/RecommendedRouteInput.php、RouteStopInput.php | 型別化寫入資料與規格／容量邊界。 |
| backend/src/DTO/RecommendedRoute.php、RouteWriteResult.php | 管理輸出與保存／發布結果。 |
| backend/src/Repositories/RecommendedRouteRepository.php | 參數化查詢、版本鎖、候選景點、有序關聯保存及軟刪除。 |
| backend/src/Services/RecommendedRoutes.php | 管理權限、交易、版本、稽核與發布協調。 |
| backend/src/Services/PublicRecommendedRoutes.php | 公開主題篩選與分頁。 |
| backend/src/Policies/RecommendedRouteSelection.php | 純函式公開站點過濾，不查DB。 |
| backend/src/Controllers/RecommendedRouteController.php、backend/config/routes.php | 七個HTTP操作、輸入／回應與路由；不處理SQL。 |
| backend/src/Composition.php、Services/ContentPublication.php、Adapters/NodeContentRenderer.php | 依賴組裝、推薦路線進同版快照、渲染來源版本。 |
| frontend/assets/js/api/recommended-routes-api.js | 共用 api-client 的路線管理介面。 |
| frontend/assets/js/pages/route-list.js | 搜尋／狀態／分頁、刪除確認與權限按鈕。 |
| frontend/assets/js/pages/route-form.js | 新增／編輯共用；景點搜尋分頁、上移下移、時間備註、未保存提示及衝突保留。 |
| frontend/assets/js/core/route-view.js | 原型卡片、安全轉義、站點與選填時間說明。 |
| frontend/pages/admin/routes.html、route-add.html、route-edit.html | 原型後台殼與真實表單；共用模板建置。 |
| frontend/pages/routes.html、assets/css/phase10.css | 原型公開殼、站點排序控制及長文字重排。 |
| frontend/scripts/publish.mjs、docker/nginx/default.conf | 同快照公開 HTML 與版本檢查路由。 |
| backend/resources/openapi.json | 96個操作；新增路線輸入、輸出、錯誤與參數契約。 |
| backend/tests/phase10_integration.py、phase10_contracts.py、tests/Unit/RecommendedRouteTest.php | 真MariaDB／HTTP、獨立契約、純規則測試。 |
| frontend/tests/route-form.test.mjs、route-list.test.mjs、route-view.test.mjs | 排序保留時間、分頁、202、409、重載／唯讀與文字安全。 |

## 資料庫與環境

兩張表仍是 routes 與 route_attractions。routes 增加 slug、theme、summary、duration_minutes、sort_order、published_at、created_at、updated_at、deleted_at；關聯增加 suggested_time、note及路線內排序唯一索引。沒有新服務、環境變數或外部套件，.env保持忽略。

先備份資料庫，`docker compose exec -T app php bin/console.php db:migrate`；隔離驗收改用 app-test。`docker compose run --rm --no-deps frontend` 建置。PHP程式變更後依既有操作指南重新啟動 publisher；Nginx模板變更後重新啟動 web（測試為web-test）。不以重啟取代資料備份。正式server依同一 migration 與靜態／API路由配置，不能只上傳舊 dist。

維持時間以分鐘保存，Excel未定義日期式路線詳情或可選結束時間，本輪不新增。完整會員一鍵套用、管理多份行程屬階段十一；來源路線資料可從公開 API取得，但本輪不寫本人行程。
