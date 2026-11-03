# 消息／優惠管理與詳情前後篇閱讀指南

2026-10-30，階段九已完成本機交付範圍，結果及界線見 [驗收紀錄](../archive/PHASE-9-ACCEPTANCE.md)。原始 website 與 Excel 不修改。

## 來源與範圍

S：功能規格 E22/G22/L22 為消息管理，E23/G23/L23 為優惠管理，E8/G8/L8、E10/G10/L10、E12/G12/L12、E29/G29/L29 為完整詳情及同分類前後篇。D：資料表建議14–16列沿用 news_posts／merchants／offers。E-31：分頁搜尋、版本衝突、共享店家必要編輯、HTTP DTO及同版快照投影是工程設計。消息摘要／正文、活動時間、優惠起訖依原型選填；優惠條款依G23必填，不能把所有列出的欄位當必填。

## 從畫面到資料庫

`frontend/pages/admin/news*.html`、`offer*.html`、`offers.html` 是六個前端View；不產生PHP View。`admin.js` 驗證身分後分派頁面，`core/session.js` 根據實際權限顯示入口。`api/content-admin-api.js` 共用已存在的CSRF/session HTTP層。

`pages/content-list.js` 處理搜尋、分類、狀態、分頁與刪除確認；`pages/content-form.js` 共用消息／優惠保存、台北時間、上傳、409保留輸入、重新載入及成功後避免重複新增。`components/content-body-editor.js` 提供格式工具與安全預覽，伺服器仍是HTML清理的邊界。`components/offer-merchants.js` 以真實店家ID選取／建立／修正，修正共享資料前明示影響所有引用優惠；不擴張成完整店家後台。

`backend/config/routes.php` 的消息與優惠區段註冊13個管理操作，靜態 merchants 路徑在優惠 `{id}` 之前。三個Controller只解析HTTP與回應；`AdminNewsManagement`、`AdminOfferManagement`、`OfferMerchants` 負責授權、交易、版本、稽核及發布協調。既有 `NewsManagement`／`OfferManagement`／`MerchantManagement` 透過 `prepareValues`／`saveChange` 共用驗證，不在HTTP偽造CLI匯入物件；CLI原介面保留。

`PreparedContentChange` 是與傳輸無關的已驗證變更。`AdminContentQuery` 驗證查詢；`ContentPage`、`ContentRecordView`、`ContentWriteResult` 將已授權資料明確轉成API結果。三個Repository負責參數化SQL與軟刪除；沒有新增業務表。更新／刪除必須帶正整數version；錯誤409不覆蓋舊資料。管理資料保存與公開頁面同步分開，202代表已保存、同步待處理，不能重送新增。

`Policies/NeighborSelection.php` 只讀取已發布快照，景點按分類、消息按event/city、電影與優惠各自排序；發布時間降冪、同時ID升冪。`ContentPublication` 將detailNeighbors納入同版generation。`PublicNeighbors`／`PublicNeighborController` 提供四個匿名查詢；沒有混用即時DB與舊HTML。`frontend/scripts/publish.mjs` 使用 `core/content-neighbors.js` 產生原型卡片，空側不生成連結，博物館使用真實路徑，縮圖由文字連結提供名稱。

## API／資料庫／環境

管理端新增消息5、優惠5、店家輔助3操作；公開前後篇4，共新增17，總89操作。完整輸入、必填、回應與錯誤見 `backend/resources/openapi.json`。固定頁仍68；新串接六頁取代保留頁，並非增加六條固定路由。

沿用news_posts／news_aliases、offers／offer_aliases、merchants與media_assets；本階段0 migration、0新表，總33表316欄／18 migration。MariaDB12.3.3實測EXPLAIN：news使用news_public、offers使用offer_public及merchant主鍵，店家少量資料掃描／排序；未因小量管理分頁盲增索引，現有證據不代表大資料負載測試。Docker、.env及部署服務不變。

## 驗證命令

PHP及Composer工具只在Docker：

```powershell
docker compose exec -T app-test /var/www/tools/vendor/bin/phpunit -c phpunit.xml
docker compose exec -T app-test /var/www/tools/vendor/bin/phpstan analyse -c phpstan.neon --no-progress
docker compose run --rm --no-deps frontend
docker compose run --rm --no-deps frontend node --test tests/*.test.mjs
python -X utf8 backend/tests/phase9_contracts.py --dependency-path .cache/validation/python
```

`phase9_integration.py` 只使用yinghua_test／localhost8081與隔離fixture，覆蓋真實SQL、拒絕／衝突／撤權／並行寫入。`phase9_contracts.py` 獨立依OpenAPI驗證真實請求／回應，避免測試自己定義契約。`NeighborSelectionTest.php` 與 `content-neighbors.test.mjs` 覆蓋分類、順序、路徑及安全編碼。測試資料與採證位於被忽略的artifacts，不提交假密碼。

2026-10-30：正文工具列使用共用button樣式，檔案選擇器以標準::file-selector-button呈現一致外觀；日期時間允許秒數，避免讀回後被原生驗證擋送。


最終前端測試190項；共用表單8項行為涵蓋消息／優惠的202鎖定、讀取失敗與重試、409保留、唯讀模擬及取消重載；`content-form-time.test.mjs`獨立驗時間往返與原生秒數步進。僅新增者取消時返回可用頁，避免回到自己的新增頁。

操作入口：[消息管理](http://localhost:8080/admin/news.html)、[優惠管理](http://localhost:8080/admin/offers.html)。先選發布狀態；「發布／排程」搭配未來發布時間即排程，時間空白則立即發布；草稿與下架不公開。優惠有效期和發布狀態分開判定，日期以台北為準。新增店家按「建立新店家並選取」後，仍須另外保存優惠；共享profile修正另有確認。完整編輯請參考明確的必填星號，選填不自行補入Google文字。

店家分頁的停用狀態由offer-merchants.refresh負責；共用表單controls在重設輸入狀態後再次委派，防止第一頁上一頁／最後頁下一頁被重新啟用。offer-merchants.test.mjs驗首末頁、空／單頁與鎖定狀態。

本機bind mount更新PHP後，content:watch已載入的class不會自動替換；依[運維指南](operations.md)重啟既有publisher並確認它自行同步，再驗公開頁。僅重建前端不能代替重載長駐PHP；正式發布需同版app／publisher。
