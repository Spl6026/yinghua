# 公開搜尋投影準備

2026-11-01，階段十三13A。Excel功能規格E18/G18要求全站搜尋，具體投影表與版本方式為E-35。此提交先建立共用發布基礎，API與搜尋頁在13B一同交付。

| 檔案 | 用途 |
| --- | --- |
| backend/database/migrations/019_public_search.sql | 可重建search_documents，11欄；generation／type／ID主鍵。 |
| backend/src/Models/SearchType.php | 五類分組順序。 |
| backend/src/DTO/SearchDocument.php | 公開快照轉文字與實際URL；不保存整個內容或私人資料。 |
| backend/src/DTO/SearchQuery.php | 後續共用字數／類型／分頁DTO，不代表API已加入。 |
| backend/src/Repositories/SearchRepository.php | 交易寫完整新generation、成功後清舊版；13A尚不提供查詢用例。 |
| backend/src/Services/ContentPublication.php | HTML成功之前先完成同版索引，失敗不開放新投影。 |
| backend/src/Adapters/NodeContentRenderer.php | 指紋包含投影規則，修改即重建。 |
| backend/src/Composition.php | CLI與HTTP共用發布組裝。 |
| backend/tests/Unit/SearchTest.php | 白名單、可見文字、優惠狀態、館舍路徑、短中文與邊界。 |

索引不是編輯主表。部署先db:migrate，再content:publish；還原沿業務DB、媒體、release，再以同一命令重建。無新env／依賴／容器。13A先依[計畫](../archive/PHASE-13-PLAN.md)驗投影，完整搜尋指南由13B一起交付。
