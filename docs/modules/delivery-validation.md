# 交付驗證工具閱讀指南

2026-11-02追加：完整原始Excel E／G／L逐列結果見[功能核實](../EXCEL-FUNCTIONAL-VERIFICATION-20261102.md)，非敏感150案例與Browser結果見[機讀證據](../evidence/EXCEL-FUNCTIONAL-20261102.json)。`frontend/tests/admin-delete-focus.test.mjs`實際執行五類列表成功／取消handler共十案；`home-error-view.test.mjs`驗證四區塊錯誤／重試、noindex及去舊內容。真503與恢復另由HTTP／Browser驗收，不能只用產頁測試當服務通過。內部錯誤資產及路由用途見PROJECT-GUIDE與正式交接。

2026-11-01，E-38。此模組只提供工程CLI，不增加網站API或業務資料表。

`scripts/validation/delivery.py` 是入口：先核對原始交付69檔、OpenAPI 115操作、68固定頁、20 migration、字型SHA256與`.env`忽略；integration再依固定順序執行PHP／分層、JS、維運邊界、匿名拒絕、P1契約、搜尋、內容補位、一般設定／政策、SEO／索引與HTTP資產／HTML。每次建立獨立忽略目錄，保存退出碼、時間、log SHA256及來源commit；第一個失敗即停止，不把舊綠色紀錄當本輪結果。

```text
python scripts/validation/delivery.py inventory
python backend/tests/phase11_fixture.py
python scripts/validation/delivery.py integration --dependency-path artifacts/p0-full-20261028/deps --schema-vocabulary artifacts/phase12/schemaorg-current-https.jsonld
python backend/tests/phase14_cleanup.py
```

依賴路徑需預先安裝測試用jsonschema；Schema.org字彙檔需從官方來源取得，沿用已驗證的快照時標明時間。integration需要Docker原有開發服務及隔離庫，**不要使用主站帳號**。fixture程式只建立自有具名前綴，不重設既有root。套件假設兩位自有會員沒有手動Browser留下的行程；先完成／清理UI資料再執行。失敗時先看該輪report及log，必要時先還原自有設定；不能直接重跑直到綠色。最後cleanup停用帳號、撤銷session及清理自有內容，保留正常audit。

`backend/tests/phase15_access.py` 依當前OpenAPI逐個驗證管理／本人API匿名拒絕，另驗兩種自有帳號登入／登出。媒體`auth_request`探測器的403只有requestId，依它自己的契約檢查，不強套一般錯誤欄位。此測試不能代替已登入越權、CSRF或所有權測試；後者沿用既有真DB整合並由本輪P1／P2回歸增量驗證。

`docs/DELIVERY-ACCEPTANCE-MATRIX.md` 是Excel30列人工判讀入口；有效基線、實跑及正式差項分開。工具的passed僅表示所列套件，不表示Browser、完整AA、正式SMTP／Google／分享平台或實際server通過。

復原工具延用`backend/tests/recovery_rehearsal.py --extended-delivery`；只操作明確自有spl-p0-* project的新資料卷。除原有媒體／收藏／權限外，加入真一般政策、排序路線及第二份行程。搜尋generation預期重建，逐欄比較公開投影而非舊generation；其餘業務表仍逐列hash核對。命令與結果見階段十五驗收及[P0維運手冊](../P0-OPERATIONS.md)。

2026-11-01補充：固定14套件新增content_fallback_validation.py，串接真DB並行／限速與HTTP契約；測試Google替身與真外部驗證分開。補位功能為U-23新增應用API，與本工具本身不新增API的責任分開。詳見[補位指南](attraction-content-fallback.md)。
