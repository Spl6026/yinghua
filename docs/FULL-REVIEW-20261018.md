# 階段二、三完整複查

2026-10-18 規格校正已交付：回溯發現的 R-01–R-07 已處理；安全政策經 U-13 明確同意，掃描範圍依 U-14 定案。355 項隔離整合驗證、主庫保留資料升級及目前行為見 [Excel 基準校正](SPECIFICATION-ALIGNMENT.md)，逐表對照見 [資料庫對照](DATABASE-SPECIFICATION-MAP.md)。以下較早日期的測試保留當時結果，不當作現行政策。

日期：2026-10-18。來源：使用者要求「全部重新檢查一次」。檢查基準為 main `e5f715e`，包含最近的 Google 關聯狀態列、摘要／介紹選填與原文參考區移位。**本輪未發現新的程式阻斷問題，沒有修改應用程式。** 不代表後續 P0／P1／P2 已實作，也不代表正式部署或全站逐像素相同。

## 本輪重跑結果

測試使用既有 Docker PHP 8.5.11／MariaDB 12.3.3，寫入測試固定在 yinghua_test／8081。主站8080做唯讀路由／來源／標頭檢查，沒有重設正式開發庫帳密或修改 .env。會重設測試 root 的套件依序執行，瀏覽器操作放在套件完成之後。

| 檢查 | 本輪結果 |
| --- | --- |
| PHPStan level10／PHPat | 無錯誤，分層限制通過 |
| PHPUnit | 37 tests／182 assertions 通過 |
| PER-CS | 123個PHP檔，dry-run零待修 |
| Rector | 僅dry-run，14檔既有風格建議，已檢視未套用；有差異的exit1不當作零差異 |
| 前端 | 64項Node測試通過；64頁Docker建置通過 |
| Composer／Nginx | 應用strict validate、platform requirements及nginx -t通過 |
| 依賴安全 | 應用與開發工具的composer audit --locked均無已知advisory／abandoned回報；不是全面無漏洞保證 |
| 認證／註冊／密碼／帳號權限 | api_integration.py：88項通過，含真實MariaDB／Mailpit |
| 唯讀模擬 | admin_preview.py：58項通過 |
| 認證回應契約 | response_contract.py：32項通過 |
| 郵件故障／復原 | mail_failure.py：4項通過；Mailpit已復原 |
| 景點／媒體／發布 | attractions_integration.py：65項通過；本套件未開live-map |
| Router／OpenAPI／內容欄位 | content_contract.py：9項通過、44操作一致 |
| 圖片說明／共用隔離／排序 | attraction_corrections.py：21項通過 |
| 每週營業時間 | opening_hours_integration.py：21項通過 |
| 行政區／官網／參考內容保存邊界 | map_fields_integration.py：10項通過 |
| app-test／web-test重啟持久化 | content_persistence.py：6項通過；未刪volume或重啟正式app／web |
| Google關聯確認政策 | google_link_integration.py：15項通過 |
| 摘要／介紹選填 | optional_content_integration.py：19項通過 |
| Origin／canonical／CSRF | 主站17項、隔離站17項通過 |
| MariaDB座標CHECK | attraction_constraints.php：8項通過，交易已回滾 |
| 帳號資料保存／例外轉譯／交易 | account_persistence.php：6項通過，交易已回滾 |

以上整合檢查合計396項，與PHP／JS單元測試分列，不把44個路由操作再次計為測試數。隔離站來源測試首次呼叫漏帶 `--origin http://localhost:8081`，修正驗收執行參數後17項通過；這不是應用程式修正，原失敗log保留。

## 頁面、檔案與資料庫

- 64份建置HTML沒有重複ID、缺少本機assets或未替換模板。主站路由63個回200；沒有識別參數的 `/place-detail.html` 回404，符合舊網址解析規則，非缺頁。帶識別的舊網址轉向另由景點整合測試驗證。
- 27個建置後JS模組的相對imports都存在。來源中的 `core/routes.js` 由build產生，已核對建置產物；不把刻意生成的模組誤列為缺檔。
- 新增／編輯來源與建置檔四份均確認：Google原文參考 → 列表摘要 → 景點介紹；兩欄required已移除。
- 原始交付69檔（website68＋Excel1）逐位元組相同，README／docs相對連結正常。`.env`及驗收憑證受Git忽略、未追蹤，建置前端未發現Google金鑰樣式。
- 正式開發庫五支migration已套用。API仍44操作、業務表仍20張，沒有新增schema／環境參數。
- 依MariaDB skill檢查正式Repository完整管理列表的EXPLAIN：attraction_admin為ref，媒體主鍵eq_ref；本次小樣本估計14筆。performance_schema為OFF，沒有擅自重啟DB或加索引；未做大資料量／負載測試。
- 實際主站回應具有nosniff、Referrer-Policy、CSP及no-store。安全審查沿用既有白名單、型別／字數、參數化SQL、授權／版本、來源URL防護與錯誤編碼；相應失敗／越權案例已實跑。未宣稱執行獨立SAST／容器漏洞掃描器。

## 實際瀏覽器操作

1. 公開景點頁：桌面6張卡片、搜尋無結果提示正常，清空搜尋恢復6張；實際桌面innerWidth1745／scrollWidth1732。手機innerWidth355／scrollWidth341，無水平溢出；導覽開關的aria-expanded及文字狀態正常。臨時尺寸已還原。
2. 後台人工新增：兩欄明示選填、required均false；無Google關聯時隱藏狀態列，七天營業時間完整。
3. Google實連一次Text Search New：成功取得一筆影像博物館候選及48字原文；需要確認才帶入，行政區東區，自填摘要／介紹保持原值。原文在摘要上方，桌面／手機實際顯示正常，手機無水平溢出。沒有保存本次Google候選、照片或原文到DB。
4. 修改名稱出現重新確認；確認後提示恢復，焦點回關聯區。解除後關聯區／參考區隱藏、焦點回景點名稱，自填摘要／介紹保留。
5. 空白編輯表單實際送出成功：兩欄空字串，MariaDB版本8→9；不是只檢查HTML標記。新增留空另由真實HTTP19項套件涵蓋。
6. 續作後實際登入隔離管理員，再開登入頁，最後網址回到 `/admin/index.html`，確認已登入時不會停留登入頁；證據為artifacts/full-review-auth-redirect.png。

截圖在本機忽略的artifacts：full-review-public-desktop.png、full-review-public-mobile.png、full-review-google-desktop.png、full-review-google-mobile.png。實測手機CSS尺寸是355，不宣稱已驗證精確390或所有瀏覽器。

## 保留界線

- [內容補位計畫](ATTRACTION-CONTENT-FALLBACK-PLAN.md)目前只完成兩欄選填。自家介紹自動摘錄、預覽、Google即時公開備援仍未實作；留空目前照實保存／輸出，不能寫成已自動帶入Google。
- 階段四以後的電影／場次、消息／優惠管理、收藏／行程保存、後續SEO等依[實作計畫](IMPLEMENTATION-PLAN.md)處理；目前展示頁的200不代表業務已串接。
- 原始網站本機file網址被瀏覽器工具政策阻擋，未繞過；本輪以原始檔完整性、現有來源及先前對照紀錄核對，沒有宣稱重新做全站原型逐頁截圖／像素比對。
- 圖片API、格式／大小／授權資料及保存整合已重跑；本輪沒有再次手動操作系統檔案選擇器。正式SMTP／HTTPS部署、Google帳戶條款／正式配額與客戶素材授權不屬本機驗收通過的保證。

逐套件原始紀錄：artifacts/full-review-results.json及full-review-*.log；頁面／imports／EXPLAIN紀錄亦在artifacts。報告只記實際執行內容，不以過往驗收代替本輪結果。


## 續作環境與資料清理

使用者回覆keep後，發現Docker Desktop未運行，原有測試記錄與檔案仍保留。已啟動已安裝的Docker Desktop並start既有app-test／web-test（含原本的frontend建置相依），沒有刪除volume、重建資料庫或修改程式／.env。主站與隔離站的景點頁、管理員登入頁及公開列表API共6項HTTP回應均為200。兩筆本輪保留供瀏覽器驗收的自有隔離草稿已刪除，重讀確認404；清理只按各自保留的ID執行。環境復原及清理紀錄在artifacts/full-review-environment-recovery.json、full-review-cleanup.json，與先前396項整合檢查分列。

最終app／app-test／web／web-test／db／mailpit均健康，publisher運行。恢復後沒有重跑整套396項，已用6項代表HTTP確認可用，沒有把恢復前後兩輪結果混為同一輪測試。

2026-10-19 後續紀錄見 [再次檢查報告](FULL-RECHECK-20261019.md)。本文件保留當次結果，不代表最新測試數與 migration 數。
