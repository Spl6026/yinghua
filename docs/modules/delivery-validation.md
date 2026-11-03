# 交付驗證工具閱讀指南

2026-11-02追加：完整原始Excel E／G／L逐列結果見[功能核實](../EXCEL-FUNCTIONAL-VERIFICATION-20261102.md)，非敏感150案例與Browser結果見[機讀證據](../evidence/EXCEL-FUNCTIONAL-20261102.json)。`frontend/tests/admin-delete-focus.test.mjs`實際執行五類列表成功／取消handler共十案；`home-error-view.test.mjs`驗證四區塊錯誤／重試、noindex及去舊內容。真503與恢復另由HTTP／Browser驗收，不能只用產頁測試當服務通過。內部錯誤資產及路由用途見PROJECT-GUIDE與正式交接。

2026-11-01，E-38。此模組只提供工程CLI，不增加網站API或業務資料表。

`scripts/validation/delivery.py` 是入口：先核對原始交付69檔、OpenAPI 115操作、48固定頁、21 migration、字型SHA256與`.env`忽略；integration再依固定順序執行PHP／分層、JS、維運邊界、匿名拒絕、P1契約、搜尋、內容補位、一般設定／政策、SEO／索引與HTTP資產／HTML。每次建立獨立忽略目錄，保存退出碼、時間、log SHA256及來源commit；第一個失敗即停止，不把舊綠色紀錄當本輪結果。

```text
python scripts/validation/delivery.py inventory
python backend/tests/phase11_fixture.py
python scripts/validation/delivery.py integration --dependency-path .cache/validation/python --schema-vocabulary .cache/validation/schemaorg-current-https.jsonld
python backend/tests/phase14_cleanup.py
```

依賴路徑需預先安裝測試用jsonschema；Schema.org字彙檔需從官方來源取得，沿用已驗證的快照時標明時間。integration需要Docker原有開發服務及隔離庫，**不要使用主站帳號**。fixture程式只建立自有具名前綴，不重設既有root。套件假設兩位自有會員沒有手動Browser留下的行程；先完成／清理UI資料再執行。失敗時先看該輪report及log，必要時先還原自有設定；不能直接重跑直到綠色。最後cleanup停用帳號、撤銷session及清理自有內容，保留正常audit。

`backend/tests/phase15_access.py` 依當前OpenAPI逐個驗證管理／本人API匿名拒絕，另驗兩種自有帳號登入／登出。媒體`auth_request`探測器的403只有requestId，依它自己的契約檢查，不強套一般錯誤欄位。此測試不能代替已登入越權、CSRF或所有權測試；後者沿用既有真DB整合並由本輪P1／P2回歸增量驗證。

`docs/DELIVERY-ACCEPTANCE-MATRIX.md` 是Excel30列人工判讀入口；有效基線、實跑及正式差項分開。工具的passed僅表示所列套件，不表示Browser、完整AA、正式SMTP／Google／分享平台或實際server通過。

復原工具延用`backend/tests/recovery_rehearsal.py --extended-delivery`；只操作明確自有spl-p0-* project的新資料卷。除原有媒體／收藏／權限外，加入真一般政策、排序路線及第二份行程。搜尋generation預期重建，逐欄比較公開投影而非舊generation；其餘業務表仍逐列hash核對。命令與結果見階段十五驗收及[P0維運手冊](../P0-OPERATIONS.md)。

2026-11-01補充：固定14套件新增content_fallback_validation.py，串接真DB並行／限速與HTTP契約；測試Google替身與真外部驗證分開。補位功能為U-23新增應用API，與本工具本身不新增API的責任分開。詳見[補位指南](attraction-content-fallback.md)。

2026-11-03補充：固定套件由14組增為19組，納入原本只能手動執行、已長期失敗而沒被發現的五支測試：`policy-pages`（條款頁真實連結與版本）、`attractions`（景點與媒體整合）、`member-persistence`（會員收藏／行程的資料庫約束）、`publication-alias-order`（舊網址查詢順序不影響 generation）、`publication-clock`（換日／換週重新發布）。失敗原因與處理：

- `publication_alias_order.php`、`public_content_clock.php`：`ContentPublication` 加入「同一請求內沿用已驗證快照」後，測試仍用同一個實例模擬多個請求，內容改變後讀到快取而失敗（反向排序的比對也因此變成必然成立）。改為每個請求建立新實例，與正式環境一致。
- `member_resources_persistence.php`：階段十一起會員可有多份行程，仍斷言「第二份行程會被資料庫拒絕」；改為驗證資料庫保證同時只有一份目前行程。版本號改為「只增不減」的判斷。
- `policy_pages_integration.py`：仍要求條款頁出現「待業主提供」等開發說明；U-25 已改為大眾文案，改為檢查不得出現開發／測試字樣。另外後台自訂條款版本名稱時，頁面顯示該名稱、同意紀錄存內部代碼（policy-雜湊），測試改從公開設定 API 取得應顯示的名稱。此套件須在 `restore-settings` 之後執行，因 `policy-consent` 會暫時改動條款版本。
- `attractions_integration.py`：假設隔離庫已有示範景點（博物館、原型編號）；改為開始時執行不覆寫的 `content:seed-demo`。

另：在殼層設有 `TERMS_VERSION` 時以 `docker compose run frontend` 建置，會把該值寫進固定頁；執行前請用 `env -u TERMS_VERSION`，與其他 compose 指令相同。

## 自動化 CI（2026-11-03）

`.github/workflows/checks.yml` 在每個 PR 與推送到 main 時執行不需要資料庫的檢查：

| 工作 | 內容 |
| --- | --- |
| PHP | 以 `docker/php/Dockerfile` 的 development 階段建置映像（與本機相同的 PHP 版本、擴充與工具），執行 PHPStan、PHPUnit、PHP-CS-Fixer（只檢查不修改）。 |
| 前端 | Node 24：`npm ci`、`npm run format:check`、建置後 `npm test`。 |
| 維運腳本 | Python 3.13 執行 `scripts/operations/tests` 單元測試。 |

需要 MariaDB、Mailpit、Node 發布程序與瀏覽器的 19 組整合套件仍以 `delivery.py integration` 在 Docker 開發環境執行；CI 綠燈不代表整合套件已通過。單元測試原本依賴開發環境的 `APP_KEY`，`backend/phpunit.xml` 改為提供測試專用的固定金鑰（已有環境值時沿用），讓乾淨環境也能執行。

## 瀏覽器冒煙測試（2026-11-03）

`backend/tests/browser_smoke.py` 在隔離的 localhost:8081／yinghua_test 建立本次專用的管理員與會員（帳號前綴 `smoke`），執行 `frontend/tests/browser/smoke.mjs`，結束後一律停用帳號、撤銷 session 並刪除其行程。也可用 `delivery.py integration … --browser` 併入套件執行，報告以 `browserSmoke` 標示；它不等於完整的人工 Browser 驗收，`browserVerified` 仍為 false。

檢查內容（目前 70 項）：14 個公開頁桌面無主控台錯誤、無重複載入的模組、手機 390px 無橫向捲動；所有後台頁無錯誤；景點、電影、消息編輯表單載入後可編輯、有修改時重新載入要先確認；會員頁無錯誤，以及行程總覽→新增→加入兩個地點→儲存（畫面不捲動、焦點不移到狀態訊息）→行程內容→手機寬度→刪除。結果逐項寫入 `artifacts/browser-smoke/results.json`。

需要 Node、`frontend` 的 devDependencies（含與瀏覽器版本一致的 `playwright` 1.63.0）及 Playwright 的 Chromium。無頭瀏覽器對程式聚焦不一定捲動畫面，所以「不捲動」另以焦點位置直接檢查；已實際把舊行為放回確認此檢查會失敗。
