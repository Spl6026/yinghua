# 全站搜尋閱讀指南

更新：2026-11-01。規格來源為Excel「功能規格」E18:G18/L18，原型為website/search.html；具體容量、查詢與發布方式標E-35，見[計畫](../archive/PHASE-13-PLAN.md)。PHP只提供API，HTML View與互動仍在frontend。

## 一次發布與搜尋如何運作

管理內容保存 → 原交易提交 → ContentPublication取得已發布快照 → SearchDocument依公開白名單產文字 → SearchRepository在交易內完整寫入generation → Node產HTML並切換current → 清除舊索引。索引寫入或HTML產生失敗時，不以新內容冒充公開成功；搜尋沿既有read()發布狀態檢查回503。寫入新索引時舊HTML仍可能存在，但新索引generation不會被舊頁查到。

搜尋時：SearchController解析SearchQuery → PublicSearch取得共用發布鎖 → 檢查同版快照 → SearchRepository對MariaDB查詢count與page → 白名單結果。讀取與發布／清理使用同一鎖，防止count與page跨版本；請求無會員帳號欄位或後台資料來源。這是現有Service Layer／Repository／DTO與公開投影的延伸，不新增框架、PHP View或搜尋服務容器。

## 檔案用途

| 檔案（相對專案根目錄） | 用途 |
| --- | --- |
| backend/src/Models/SearchType.php | 景點／美食／電影／消息／優惠的enum與固定分組順序。 |
| backend/src/DTO/SearchQuery.php | 類型、字數與分頁邊界。 |
| backend/src/DTO/SearchDocument.php | 公開快照白名單、HTML轉可搜尋文字、實際詳情URL與縮圖；不放Google關聯或帳號秘密。 |
| backend/src/Repositories/SearchRepository.php | 索引交易替換、成功後清舊版、參數化LOCATE／count／穩定排序分頁。 |
| backend/src/Services/PublicSearch.php | 發布版本與鎖的協調；空查詢回空集合。 |
| backend/src/Controllers/SearchController.php | HTTP參數解析及Response；沒有SQL或HTML。 |
| backend/src/Services/ContentPublication.php | 既有發布流程加入完整索引寫入與成功後清理。 |
| backend/src/Adapters/NodeContentRenderer.php | 指紋納入搜尋投影規則；部署後即重建索引與公開HTML。 |
| backend/src/Composition.php | 明確依賴組裝與搜尋路由。 |
| backend/database/migrations/019_public_search.sql | 新增一張可重建search_documents；未改舊migration。 |
| backend/resources/openapi.json | 一個匿名GET搜尋契約、錯誤、類型與字數。 |
| frontend/pages/search.html | 原型主視覺／搜尋表單，加入類型、狀態、重試、分頁與noscript網站導覽。 |
| frontend/assets/css/phase13.css | 增量樣式、類型控制項、卡片長文字及手機重排。 |
| frontend/assets/js/core/search-state.js | URL編碼／往返及最新請求序號；過時成功或錯誤不覆蓋新結果。 |
| frontend/assets/js/pages/search.js | 共用api-client、會員導覽、原生表單／類型／分頁／popstate、安全textContent、錯誤關聯與焦點。 |
| frontend/assets/js/components/search-card.js | 原型卡片的純元件，不載入prototype；安全文字、同類型詳情URL與可辨連結。 |
| frontend/assets/js/public.js | 移除舊原型陣列搜尋，不保留另一個搜尋擁有者；其他既有展示互動保持。 |
| backend/tests/Unit/SearchTest.php | 公開白名單、型別／URL、短中文、字面特殊字元及邊界。 |
| backend/tests/phase13_integration.py | 真MariaDB／HTTP／獨立OpenAPI；公開同步、分頁、特殊字元與失敗復原。 |
| backend/tests/phase13_cleanup.py | 僅清命名yinghua_test自有內容、重建索引；可保留基礎fixture供其他回歸。 |
| frontend/tests/search-state.test.mjs | URL往返、先後請求競爭、過時錯誤、清空與失敗恢復。 |
| frontend/tests/search-card.test.mjs | XSS文字、外部／javascript／錯誤主題URL拒絕及館舍實際路徑。 |
| backend/tests/phase13_profile.py | 實際WHERE／排序／分頁與count的EXPLAIN／ANALYZE、版本及觀測界線。 |
| backend/tests/phase13_verify_cleanup.py | 只讀核對自有內容殘留、索引版本／主鍵及全部migration checksum。 |

## API與資料表

`GET /api/v1/search?q=&type=&page=&pageSize=`匿名讀取。q最多255個Unicode字元，trim後空白回空集合；不是至少兩字。type留空或place／food／film／news／offer；美食對應景點主表的「美食」分類，其他景點／店家屬place。page從1起、上限100000；pageSize預設20、最多100，都是E-35工程容量。無新業務必填欄位。

items只含id、type、title、summary、slug、url、thumbnail、thumbnailAlt；另total、page、pageSize、generation與共用requestId。直接詳情URL對應實際主題；館舍仍使用museum.html。優惠僅列目前可用優惠，與公開優惠列表一致；電影可查所有已發布電影，不限本週場次。

搜尋欄位包括名稱／標題、摘要或介紹、消息安全正文、電影類型、景點分類／行政區／地址、店家名稱與優惠條款。正文先移除標籤、解碼字元與正規空白，不把HTML標籤當搜尋內容；原本是文字的標題仍按字面顯示。結果卡片沿原型採文字，不自動把thumbnail改成新圖卡樣式。

新增search_documents共11欄：generation、resource_type、resource_id、type_order、title、summary、search_text、slug、url、thumbnail、thumbnail_alt。僅保留generation／type／ID主鍵；實測未使用的額外排序索引已在本階段未提交migration定稿前移除。沒有新增編輯表、會員欄位、env、依賴或Docker服務。它是E-35工程投影，非Excel「資料表建議」新增硬性實體；業務來源仍是既有四類內容表。

## 查詢方式與限制

參考[MariaDB LOCATE](https://mariadb.com/docs/server/reference/sql-functions/string-functions/locate)進行字面包含，%／_不是萬用字；所有q、type、generation、LIMIT／OFFSET綁定參數。先依類型分組，再完全同名優先、UUID固定次序，跨頁穩定；發布變更後屬新generation，不保證不同版本仍在同一頁。

包含搜尋需要檢查候選文字，不能用一般title B-tree保證次線性；採此方式支援一字中文，不假裝已有中文分詞／拼音／模糊容錯。依MariaDB12.3真EXPLAIN／ANALYZE記錄索引與排序，不冒稱大資料效能或正式壓測；未來規模改變再依量測調整。

本輪MariaDB12.3.3、Performance Schema原為OFF，未重啟DB啟用或宣稱取得完整等待事件剖析。49筆測試投影的all與news查詢皆type=ref、使用PRIMARY（generation或generation+type），候選估計49／28筆並Using filesort；ANALYZE另記實際執行。這是本機小資料查詢計畫，不是大規模效能保證。

## 操作與復原

```powershell
docker compose exec -T app php bin/console.php db:migrate
docker compose exec -T app-test php bin/console.php db:migrate
docker compose run --rm --no-deps frontend
docker compose restart publisher
docker compose exec -T -u www-data app php bin/console.php content:publish
docker compose exec -T -u www-data app-test php bin/console.php content:publish
docker compose exec -T app-test /var/www/tools/vendor/bin/phpunit -c phpunit.xml
docker compose exec -T app-test /var/www/tools/vendor/bin/phpstan analyse -c phpstan.neon --no-progress
docker compose run --rm --no-deps frontend node --test tests/*.test.mjs
python -X utf8 backend/tests/phase11_fixture.py
python -X utf8 backend/tests/phase13_integration.py --dependency-path .cache/validation/python
python -X utf8 backend/tests/phase11_fixture.py --cleanup
```

整合測試預設finally清自有搜尋內容；只有`--keep-browser`且全部通過才保留，Browser結束後執行`phase13_cleanup.py`（同時清基礎會員fixture）。`--content-only`僅清本模組內容，供後續P1回歸繼續使用基礎帳號。依賴路徑是先前本機驗收的忽略目錄；其他機器以其已準備的jsonschema路徑取代，不將未安裝當通過。

備份仍包含DB／媒體／release。復原或部署新版本後content:publish會由業務內容重建索引；不要只上傳dist或手動複製一份舊搜尋結果。搜尋頁維持noindex且排除sitemap；查詢字詞不加入JSON-LD。正式網域及既有外部平台交接仍依U-21處理。
