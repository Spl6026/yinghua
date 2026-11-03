# 階段十一：完整會員行程驗收

日期：2026-10-31；分支 `phase/11-member-itineraries`，由已完成階段十的main分出。計畫與來源見[PHASE-11-PLAN.md](PHASE-11-PLAN.md)，逐檔說明見[會員行程指南](../modules/member-itineraries.md)。以下只記本輪實際結果。

## 功能與規格對照

| 來源 | 完成範圍／證據 |
| --- | --- |
| 功能規格E15/F15/L15；API草案C21:G24 | 本人集合、新建、開啟、完整保存、刪除；名稱／日期／時間／備註、上下排序及移除，重新登入及另一HTTP session載回一致。原型名稱日期未required，空名稱／日期／站點可保存。 |
| 功能規格G15 | 會員所有權；A不能讀改刪／切換／套用B行程。admin Cookie不能當會員，無CSRF拒絕。重複站點／新加入不存在或下架景點拒絕；舊下架站保留私人snapshot，不洩漏草稿。 |
| API草案E23/F23；資料表建議C19:D20（D） | 父子同交易，強制子表失敗實測完整回滾；同版並行一成功一409。整份保存與原P0目前行程共用資料。 |
| 功能規格E14/G14/L14/E24/G24/L24 | routes恢復套用入口；GET僅預覽，確認後替換名稱／站點並保留日期。修改已發布路線後新順序、時間備註可套用；下架站跳過，草稿路線不公開／不套用。 |
| E-33／U-05 | 會員鎖、同會員單調版本、UUID建立去重及草稿隔離；保護切換行程後的P0舊分頁。保留原型布局，增加真正必要的編輯與安全確認。 |

新增8個操作，實際Composition路由與OpenAPI逐一比對為104／104，差集皆空；舊96操作契約不變。68固定頁／33表327欄／19migration。沒有新表、欄位、migration、依賴、環境變數或容器。

## 自動化及真實資料庫

| 檢查 | 實際結果 |
| --- | --- |
| phase11_integration／contracts，yinghua_test HTTP＋MariaDB | **81通過；66筆回應／17個實際操作，JSON Schema零錯誤**，涵蓋全部新8操作及既有認證／P0／管理路線。204沒有JSON內容；成功請求亦驗契約。 |
| P0 member_resources_integration | **69通過／29契約回應**，收藏、所有權、停權與CSRF既有回歸。 |
| P0 member_itinerary_integration | **100通過／55契約回應**，目前行程持久化、分頁、失敗交易與並行增減。 |
| PHPUnit（Docker PHP 8.5.12） | **107 tests／356 assertions，通過**。 |
| 全部前端Node測試（Docker） | **208通過**；新增8項草稿狀態測試，原planner VM測試依完整編輯器調整，保留回應身分隔離及無GET自動寫入。 |
| PHPStan level10／PHPat | 通過，無錯誤。 |
| PER-CS scoped dry-run | 新增／調整16個PHP檔最終零差異。最初格式差異已檢視並限定修正，不算新功能進度。 |
| Rector | 僅dry-run；兩檔可選語法提案未套用。保留顯式null判斷避免與PHPStan型別結論衝突。 |
| 前端build | 成功產生68固定HTML；全站HTTP完整性結果見下方交付核對。 |

`phase11_queries.php`在真MariaDB **12.3.3**建立160份自有行程並全部回滾；集合EXPLAIN為ALL／filesort（測試中幾乎所有列屬同會員），ANALYZE約3ms；MAX(version)約0.03ms，目前行程用既有member_current唯一索引const。未因小資料計畫任意補索引或重啟DB。performance_schema=OFF如實保留，沒有聲稱取得長期工作負載或大規模壓測；若實際會員行程量增長，再依工作負載評估D19建議的member_id／updated_at索引。

## 實際內建Browser與原型

本輪背景IAB操作，不開主機讀屏或控制主機視窗。舊tab handle讀取失效後，以同一Browser新分頁及公開Playwright介面恢復；登入只用自有yinghua_test帳號。

- 會員登入安全返回planner、新建、名稱／日期保存、空名稱／空日期／空行程保存；兩份行程切換後原站點、時間、備註、順序載回。
- 地點query只顯示待加入，按「加入並儲存至此行程」才保存；兩站上移、手機鍵盤下移、邊界禁用、儲存。
- 重新載入／新建前的未保存確認，取消與Escape保留；反向Tab由取消循環至確認，Escape返回原觸發控制。
- routes的套用連結與planner預覽不改帳號；取消保持原名稱，確認後帶入名稱／有序站點／時間／備註，旅行日期保持。
- 兩個實際分頁同版修改：第一頁成功，第二頁409，輸入文字保留、保存按鈕禁用、錯誤取得焦點；確認重新載入才捨棄草稿。
- 另一分頁登入會員B，A頁再提交會清私人草稿／站點，不把舊資料套給B。重新登入A載回已保存內容。
- 刪除取消返回刪除按鈕；確認後焦點至結果，刪current選另一份，刪最後一份回空狀態，保存／刪除禁用。
- planner、routes各**1440／390／320共6筆**；原始website相同兩頁及三尺寸另**6筆**對照。實測innerWidth符合設定，沒有整頁橫向溢出；planner所有input/select/textarea都有label。

保留原型hero、字體配色、300px桌面左欄、旅行卡片與編號／上移下移移除。真會員資料取代示範localStorage；增加清單、時間備註編輯及確認，卡片高度隨內容增加，並非逐像素相同。44px操作按鈕維持桌面橫排、窄螢幕移到內容下；分頁控制調整同列。原型自動套用改為確認後寫入（E-33），不把匿名GET當修改。

增量AA檢查：標籤、原生輸入／鍵盤操作、可見焦點、確認框正反循環／Escape／返回、錯誤焦點、status、320重排及未保存保護通過；依U-20沒有啟動NVDA。共用頁殼沿用[P0有效AA基線](P0-AA-FINAL-20261029.md)，不宣稱本輪重跑全站50準則／真200%400%／完整輔具或取得正式標章。

## 交付核對

測試產物放忽略的`artifacts/phase11/`：integration／contracts／queries／inventory／browser-matrix、PHP與Node日誌及成對截圖。兩個本機入口8080／8081均為**68固定頁、19資產、87 HTTP、零issues**。獨立比對確認舊96操作及原Schema不變、原始第一提交69檔逐byte無差異，新文件連結無缺檔。

隔離fixture清理完成：自有兩會員行程／收藏、路線、景點、電影、失敗測試trigger均為0；自有管理員及兩會員已停用軟刪、所有session撤銷，正常稽核保留。臨時原型HTTP server已停止，新建Browser驗收分頁關閉並reset viewport；舊失效登入分頁受handle限制無法再綁定，留給Browser的回合自動清理，沒有操作其他使用者分頁。正式server與先前上游映像交接範圍維持，不用Docker環境事項代替應用驗收。

Git按11A共用資料、11B完整會員編輯與套用交易、11C公開路線直接預覽／套用入口、11D整合驗收分功能提交。建立提交前備份`before-functional-commits.bundle`，合併前另建`before-merge.bundle`並核對最終tree；原始交付及階段分支保留、不推送遠端。階段十二未開始。
