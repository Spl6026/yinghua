# 階段四驗收：電影、場次與公開發布

驗證日期：2026-10-19；交付文件更新：2026-10-20。**已完成本機實作與驗收**。範圍是 P0 階段四；P0 階段 5–8、P1／P2 及正式部署仍依後續授權。原始 Excel／website 保留，結束時間議題依 U-16 延後。

## 已交付

2026-10-21 補修：發布錯誤邊界、僅新增權限導覽與電影場次介紹原型對齊，詳見 [修正與驗證](REVIEW-PLAN-IMPLEMENTATION-20261020.md#2026-10-21-修正與驗證)。本輪沒有新增 API／資料表／migration；下方保留原驗收日期與數字。

- 後台 `/admin/films.html`、`film-add.html`、`film-edit.html`：電影搜尋／狀態／分頁、原型海報縮圖與網址預覽、共用圖片上傳、編輯／刪除、草稿／發布／排程／下架，以及失敗重試。
- 一部電影多筆場次：日期、開始時間、各場售票網址；未另填售票網址時沿用電影預設。新增／移除按保存才落 DB，version 保護整組。
- 公開 `/api/v1/films`、電影詳情、cinema 片單與首頁前三部不同電影；同一成功 generation，無 JS 仍可讀 HTML 與正確 head。
- 四個原型電影網址、legacy 0–3／UUID 及改 slug 歷史網址；只有已發布目標可轉址，草稿／刪除 404，不退回原型固定內容。
- 電影海報使用現有內容檢查／重新編碼機制；草稿本地圖片依實際模組授權，公開圖片只放行成功快照引用的資產。

完整程式閱讀見 [電影指南](../modules/films.md) 與 [全站導讀](PROJECT-GUIDE-20261103.md)。

## 規格與工程邊界

| 來源 | 本次對應與驗證 |
| --- | --- |
| 功能規格 E7–L7、J7=P0 | 真實每週電影／海報／日期時間／介紹連結，資料變更同步；多場次可顯示多列 |
| 功能規格 E21–L21、J21=P0 | 電影及多場次 CRUD、海報與前台同步、草稿不公開、唯一 slug；有效日期與開始時間檢查依 U-16 |
| 功能規格 G7／G21、串接總覽 B12 | 海報可不提供；有圖須有有效 alt，可繼承選用資產的 alt。用途覆寫不修改其他電影／原資產 |
| 原型 film-add／film-edit 的 name required | 電影名稱必填。類型、介紹、海報、售票網址及零場次可空，不因 F 欄列出就全部必填 |
| U-16；資料表建議 C13／D13 | 比較相同日期與開始分鐘；不同開始時間可並存，不計片長、結束時間或多場地。沒有 ends_at／venue 欄位 |
| 資料表建議 A12–D13；E-25 | 新增 films、film_screenings；歷史網址表、網址海報／用途覆寫、版本及排程具體實作為工程選擇，不把草案變成硬性必填 |
| 功能規格 E5／L21；E-17 | 首頁電影區與基本詳情可真實讀取；P1 同主題前後篇、電影存入會員行程不在本次 |
| 功能規格 30、SEO規格 C17／F17 | 基本 title／description／canonical／OG／Twitter 隨 HTML 發布；全站 SEO 設定、JSON-LD／sitemap 仍在後續 |
| 串接總覽 B7–B15、功能規格 34 | API 授權／CSRF／session／稽核、圖片內容檢查、發布一致性及本次表單／RWD／焦點檢查 |

E-25：台北星期一 00:00 至下星期一 00:00、結束不包含；開始時間採分鐘精度。草稿占用開始時間，下架／刪除釋放，恢復時重新比較。這些具體政策有標示來源，未冒稱 Excel 明文。寫入時間格式使用含秒及數字時區的 `YYYY-MM-DDTHH:mm:ss±HH:mm`，DB 保存 UTC。

slug 160、片名／類型／alt／來源／授權 255、介紹 20,000、網址 2,000、SEO 160／500、分頁預設 20／最多 100、JSON 64 KiB，以及圖片 8 MiB／6000 邊長／1200 萬像素屬技術邊界。未新增「最多 100 場」業務限制，已測 101 場。正式內容與完整放映年份未知時留空，不從原型月日或頁尾年份推測。

## 實際測試

Docker：PHP 8.5.11、MariaDB 12.3.3、Node 24。PHP／Composer／品質工具皆在 Docker 執行。

| 檢查 | 結果 |
| --- | --- |
| PHPStan level 10＋PHPat 分層 | 通過；Controller／Service／Repository 與 DTO 邊界未違反規則 |
| PHPUnit | 55 項／233 斷言通過 |
| 全部前端 Node 行為／實際 renderer | 88 項通過；含電影表單、動態場次、換頁失敗、跨年期間與 HTML 安全編碼 |
| 前端建置 | 64 份靜態 HTML 來源建置成功；新電影詳情另由 DB 動態產生，不限制於四份原型 |
| PER-CS dry-run | 149 個 PHP 檔，零待修正 |
| Rector 本階段相關檔 dry-run | 8 檔提出 new 呼叫括號、arrow closure、nullsafe 風格提案；已檢視，保留現有可讀寫法與 PHPStan 規則，未自動套用，不稱為零差異 |
| Composer audit --locked | 應用及獨立品質工具兩份 lock 均零已知公告；未宣稱已執行 Semgrep／Trivy／Gitleaks |
| 真實 HTTP／MariaDB／Mailpit | 20 套共 619 項通過；其中原階段二／三 460，電影公開 22、管理 61、場次 42、seed 8、重建持久化 8、分頁 18 |
| 獨立 OpenAPI | python-jsonschema 4.26.0：54 個操作均觀察到，768 回應與 247 成功 JSON 請求驗證零差異；另含 multipart 真實回應驗證 |
| 空庫／保留資料升級 | 兩個新建隨機測試庫，24 表／12 migration、重跑、FK／唯一、最少電影與舊會員／景點保留通過；自有測試庫與暫時授權已清除 |
| Nginx | 主／隔離設定檢查均通過；新電影網址與舊入口實際 HTTP 已驗證 |

重點失敗情境：匿名／四動作分權、跨模組圖片、CSRF／異來源、唯讀模擬、錯誤型別與欄位、重複 slug、舊版本、外來／重複場次 ID、同片／跨片同時、UTC offset 同一瞬間、非法日期／非整分鐘、下架／恢復、不同電影並行搶同一開始時間、稽核／場次寫入故障全份回滾。

公開產生故障明確回 202：DB 已保存，不能重複新增；API／HTML 不繼續供應錯誤的舊版本，授權編輯者可重試。排程跨界、同版景點共存、無資料與刪除／下架均已測。生成內容中的 `$&`／`$1` 保持文字，不被字串 replacement 當成指令。

## 瀏覽器與原型對照

使用 web-design-guidelines 當日規則及 browser skill；範圍限本次電影頁與資產。對照原型來源保留標題、側欄／頂列、表格／海報縮圖、日期時間、網址即時預覽、介紹、售票連結、取消，以及前台三張電影卡、cinema-row 與 detail-page。

實際觀察：新增電影 → 網址預覽保存 → 重開編輯 → 新增兩場 → 相同開始時間回錯誤並聚焦第二列 → 改成 15:00／15:30 → 圖片選擇器選取 480×720 自有 PNG → 上傳預覽 → 發布 → 後台兩場、首頁／片單／直接詳情一致，兩場顯示各自有效售票網址。另驗公開第 2 頁／返回第 1 頁、首頁三部不同電影、root 唯讀模擬，以及只有 films.create 的帳號保存成功後可再新增，不被導向無權限列表。

桌面實際 innerWidth 1440、innerHeight 900、clientWidth／scrollWidth 1426；手機實際 390×844、clientWidth／scrollWidth 376，未見頁面水平溢出。手機表單單欄、首頁第一張跨欄／其餘雙欄、片單原型手機排列；動態新增移焦日期，錯誤欄與提示相連。表格可在自身區塊水平捲動。未宣稱逐像素一致、所有瀏覽器或正式讀屏／完整 WCAG 認證。

截圖在本機 Git 忽略的 artifacts：phase4-admin-mobile.png、phase4-screening-error-mobile.png、phase4-readonly-mobile.png、phase4-create-only-mobile.png、phase4-film-detail-desktop.png、phase4-home-films-desktop.png、phase4-home-films-mobile.png。都是隔離測試資料；驗收暫存電影／分頁資料已清除，原型測試電影日期還原，不修改主庫放映排程。

## 資料庫、環境與運行

主／測試庫已套用 010／011，24 表／211 欄／12 migration；新增三表共 30 欄。沒有新環境變數、資料庫登入帳號、Google 金鑰或外部服務；.env 與秘密仍被忽略。升級前 SQL 備份保存在本機 artifacts/phase4-before-migrations-20261019.sql，不提交 Git。

使用同一 publication 寫入鎖協調電影及景點；電影、場次、稽核在同一交易，提交後才發布。此保證以目前單機共享 volume 為範圍，不延伸宣稱分散式鎖。公開期間在成功投影篩選，場次先批次載入；具體索引與當次 EXPLAIN 限制見 [資料庫對照](../DATABASE-SPECIFICATION-MAP.md)。

已 force-recreate 隔離 app-test／web-test，電影、兩筆場次、海報 bytes、HTML bytes、generation、版本與 session 保留。未刪 volume，未為驗收重啟共用 MariaDB 或 Docker Desktop。

更新既有環境（先備份）：

```powershell
docker compose exec -T app php bin/console.php db:migrate
docker compose run --rm --no-deps frontend
docker compose restart publisher web
docker compose exec -u www-data -T app php bin/console.php content:publish
# 選用：原型四部電影只匯入 metadata；重跑不覆寫人工內容，也不猜年份。
docker compose exec -u www-data -T app php bin/console.php film:seed-demo
```

主庫已保留四個原型電影網址，尚未提供完整放映年月日，所以本週片單可為空。到「後台 → 本週映演 → 編輯」填完整場次，將發布狀態選「已發布／排程」、確認發布時間後儲存即可公開；不要求至少一場才能發布，零場次詳情會說明尚未安排。

重現驗收（請勿平行執行同一隔離庫的 fixture）：

```powershell
./scripts/setup-test-db.ps1
# 首次測試庫需受控展示景點與固定測試 root；各 suite 初始化測試 root。
docker compose exec -u www-data -T app-test php bin/console.php content:seed-demo
docker compose exec -u www-data -T app-test php bin/console.php content:publish
python -m pip install --target artifacts/audit-jsonschema -r backend/tests/requirements.txt
python backend/tests/openapi_contracts.py --dependency-path artifacts/audit-jsonschema --include-films
python backend/tests/film_schema_upgrade.py
docker compose exec -T app-test php tests/film_persistence.php
docker compose exec -T app php /var/www/tools/vendor/bin/phpunit
docker compose exec -T app php /var/www/tools/vendor/bin/phpstan analyse --no-progress
docker compose run --rm --no-deps frontend npm test
```

## Git 與交付界線

階段分支 phase/04-films-screenings；資料層準備、公開電影、電影／海報管理、多場次、整合驗收／導讀五個功能邊界。代理自身修正併回對應功能，不另留虛假進度提交。整理與合併前保存並驗證 bundle，保留階段分支；交付時核對最終 tree、原始 69 檔及第一提交，不改遠端 refs／不推送。

正式 SMTP／HTTPS／內容與素材權利、正式完整放映年月日仍須正式環境確認。全站搜尋、收藏／行程、消息／優惠管理、同主題前後篇及全站 SEO 設定不在階段四完成範圍。
