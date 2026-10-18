# 階段二、三再次完整檢查

後續2026-10-19業主逐條核對仍發現本報告未覆蓋 E20 範例試填及 G6 電話；這兩項現已補齊，最新來源與結果見 [業主要求對照](PHASE-2-3-OWNER-ALIGNMENT.md)。本報告的435／445項與九份migration保留為當次事實，不作最新全面符合規格的宣告。

更新：2026-10-19。本次依使用者「全部再檢查一次」要求，重新核對已實作的認證、管理員、權限、唯讀模擬、景點、媒體、地圖候選與公開發布。不代表 P0 全部完成；階段四以後仍未開始。

## 基準與方法

以原始 Excel、原始 website 及最新使用者指示為準。主要來源為「功能規格」G13／L13（景點驗證與發布）、G20／L20（地圖）、G26／L26（固定管理員）、G27／L27（權限），「串接總覽」B9／B10／B12／B14（認證、驗證碼、媒體、授權）。逐欄來源見 [限制回溯](SPECIFICATION-BASELINE-REVIEW-20261018.md) 及 [資料表對照](DATABASE-SPECIFICATION-MAP.md)。

U-13 保留八字密碼、取消首登強制及具權限的一般管理員自我操作；最新 U-15 要求確認密碼必填且逐字一致。新增管理員原型只有初始密碼，不另加確認欄。U-14 掃描範圍為圖片內容檢查。摘要與介紹依 U-11 選填，自動補位仍延後。工程邊界不冒稱 Excel 業務必填。

這次除了重跑測試，也檢查原始來源、實際 DOM、MariaDB 約束與真實 HTTP 的 JSON Schema。測試通過不能反過來當作規格依據。

## 發現與處理

| 項目 | 修正前的可重現問題 | 現在的處理與驗證 |
| --- | --- | --- |
| 固定管理員編輯頁 | 以保護訊息替換表單後，finally 重新查詢已移除按鈕，出現 `Cannot set properties of null` | 先保留按鈕參照；Node 測試模擬節點確實移除，修正前失敗、修正後通過；實際頁面只顯示保護訊息 |
| 新增管理員說明 | 仍寫著首登須改密碼，與 U-13 衝突 | 改為直接依指派權限使用，與 API 現況一致 |
| 固定身分資料庫保護 | CI collation 將大小寫與尾端空白視為相等，直接 SQL 可變更 root 的等價文字 | 新增 008 migration，以 BINARY 比較不可變欄位；16 項真實 DB 探測全部回滾。原 HTTP 修改／刪除仍回 403，未觀察到 HTTP 越權 |
| JSON 非預期欄位 | `"0"` 等鍵被 PHP 轉為整數，再遭 Request 忽略，未交給白名單拒絕 | HTTP 入口直接回 422；保留巢狀列表用途。單元測試及 HTTP 驗證拒絕且不新增資料 |
| Unicode 帳號長度 | HTML maxlength 以 UTF-16 計算，與 API／DB 的 60 個 Unicode 字元不一致 | 移除三個帳號輸入框的原生長度截斷，仍由 API 驗證 60 字；60 字保留並可登入、61 字拒絕 |
| requestId 契約 | OpenAPI 寫 36 字元，實際產生 32 位十六進位 | 使用共用 RequestId schema；同時檢查 JSON／HTTP header 一致，沒有改動識別碼演算法 |
| 地址回應契約 | 輸入已選填，但兩個回應 schema 仍要求至少一字 | 空地址允許回傳空字串；回應仍包含 address，不額外強迫使用者填寫 |

DB 的 BINARY 比較採 [MariaDB 官方語意](https://mariadb.com/docs/server/reference/sql-functions/string-functions/binary-operator)，比較大小寫及尾端空白。這是 S-026 的工程實作 E-23；不是增加一般帳號的改名限制，也無法防止具 DDL 權限者移除 trigger。

舊檢查沒有捕捉全部問題的原因包含：DOM mock 未模擬節點被移除、回應只驗部分欄位而未執行獨立 Schema、缺少非 BMP 帳號與 collation 等價文字案例。這次補上對應回歸與可重複執行的獨立驗證，不以增加測試數量保證永遠無錯。另清除 PROJECT-GUIDE 將景點寫為尚無正式資料表的過時段落，並補齊九份 migration 與新增檔案索引。

## 實際驗證

| 範圍 | 本輪結果 |
| --- | --- |
| 真實 HTTP／MariaDB／Mailpit，13 組整合套件 | 435 項通過；其中包含 root 16 項回滾探測的彙總，未重複加總 |
| SMTP 故障／恢復 | 4 項通過，Mailpit 已恢復 |
| 隔離 app-test／web-test 重啟保存 | 6 項通過，不刪除 volume；新增測試內容於最後刪除 |
| PHPStan level 10／PHPat | 通過 |
| PHPUnit | 41 項、189 斷言通過 |
| Node | 68 項通過 |
| PER-CS | 125 檔，0 項修改建議 |
| Rector | 本次三個 PHP 變更檔 dry-run 通過，無修改建議；未自動套用 |
| 前端建置／靜態檢查 | 64 頁建置；無遺失原始路由、破損本機資產引用、無效 JSON-LD 或主要導覽差異 |
| 原始交付 | 第一提交的 69 檔逐位元組一致；主樣式、SEO 樣式及 favicon 保留 |
| 獨立 OpenAPI 驗證 | python-jsonschema 4.26.0；519 份實際 JSON 回應、165 份成功 JSON 請求及 1 份真實 Mapper 的合成候選，0 個格式差異 |

整合共 445 項（435＋4＋6）。獨立 schema 驗證覆蓋 39 種實際操作；路由／契約清單另核對 44 種操作一致，不宣稱每種操作的每種狀態都做過 schema 驗證。Google 候選使用合成輸入，這輪沒有呼叫 Google 付費 API。

64 個來源網址實際 HTTP 探測中，63 個回 200；`place-detail.html` 未帶識別碼回 404，符合改用正式內容解析的路由。帶原型 `?id=2` 實際轉至 `/places/dongmen-market.html` 並回 200。不能把缺識別碼的詳情頁當成建置遺漏，也不能將 64 個靜態檔都宣稱直接回 200。

## 資料庫與環境

現在 9 份 migration、20 張業務表＋1 張工具表，共 180 欄。008 只更新保護 trigger，不新增表、欄位、API 或服務；001–007 已套用檔案內容不變。

主庫升級前已備份至被忽略的 `artifacts/before-full-recheck-root-guard-yinghua-20261018.sql`；升級前後 20 張業務表筆數一致。主庫及隔離庫皆已套用 008 並重跑確認。另以臨時空庫驗證九份 migration、重跑、seed 及初始化，完成後移除該臨時庫與專用授權。沒有更換 `.env` 或正式管理員密碼。

MariaDB 12.3.3 的外鍵／CHECK 檢查開啟，景點操作者無孤兒資料，帳號查詢使用既有 account 索引。本次沒有新增索引、調整效能觀測設定或重啟主庫。

## 重現與閱讀入口

新增檔案各有單一用途：

- [008 migration](../backend/database/migrations/008_root_identity_bytes.sql)：固定身分逐位元組不可變。
- [RequestBoundaryTest](../backend/tests/Unit/RequestBoundaryTest.php)：數字 JSON 鍵及巢狀列表邊界。
- [root_identity_constraints.php](../backend/tests/root_identity_constraints.php)：僅隔離 DB 可執行的 16 項回滾探測。
- [review_regressions.py](../backend/tests/review_regressions.py)：HTTP 非預期欄位、Unicode 帳號與 DB 探測彙總；最後清除本次 fixture。
- [openapi_contracts.py](../backend/tests/openapi_contracts.py)：整合套件的真實請求／回應獨立 Schema 驗證。
- [requirements.txt](../backend/tests/requirements.txt)：僅上述 Python 測試的鎖版依賴，不是網站執行依賴。

先依 [導讀](PROJECT-GUIDE.md#啟動驗證與排查) 啟動隔離環境，再執行：

```powershell
python -m pip install --target artifacts/audit-jsonschema -r backend/tests/requirements.txt
python backend/tests/openapi_contracts.py --dependency-path artifacts/audit-jsonschema
```

此命令會修改 yinghua_test 的合成測試資料並寄信至本機 Mailpit。清理或初始化不應改成主庫 yinghua。測試結果保存在被 Git 忽略的 artifacts；Schema 錯誤只記錄路由、狀態及欄位路徑，不輸出密碼、token 或請求內容。SMTP 故障與容器重啟測試需另外執行，避免與其他測試並行。

## 驗收界線

這輪畫面以實際 DOM／互動與原型來源、樣式、導覽對照為依據；不是全部頁面的逐像素比對。瀏覽器手機設定為 390 × 844，工具實際回報內容寬 355，報告採實測值，不聲稱精確 390。已操作 root／一般帳號登入、60 字 Unicode 帳號登入、root 與一般帳號編輯、新增管理員文案、手填景點、固定 root 40 格鎖定矩陣、唯讀模擬／退出，以及更改密碼返回後台。上述手機頁面實測 scrollWidth 341，未超出 innerWidth 355；桌面手填景點頁為 innerWidth 1745／scrollWidth 1732。確認密碼欄 required，返回連結為後台首頁；未修改正式密碼。前台搜尋可顯示零結果、清空後恢復六筆，分類可切換為兩筆；手機導覽可展開。瀏覽器測試的兩個普通帳號按確切 ID 軟刪除，未清除其他測試或主庫帳號。

正式 SMTP／TLS 部署、正式壓力測試與外部弱點掃描器不在本輪通過範圍。地圖即時服務、真實圖片選擇器不以這次合成 API 驗證替代。其餘業務及自動補位維持既定計畫，不因本次再次檢查提前列為完成。

## Git 交付

修正歸回原有功能提交，新增獨立契約驗證工具與這份報告按跨模組驗證交付。五個改動後的功能快照（HTTP 前置、認證、管理員、唯讀模擬與景點選填）已在 Docker 通過 PHPStan、適用單元測試、前端建置／測試與路由契約檢查。

事前及候選歷史備份在被忽略的 artifacts/history-before-full-recheck-20261018.bundle、artifacts/history-before-full-recheck-fold-20261019.bundle；合併前核對完整 tree、原始 69 檔及保留的階段分支。原始交付提交維持 56733e5c566009b02fb8d23c8949eec0c61f80c6；不推送或改寫遠端。

