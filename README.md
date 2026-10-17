# 映畫竹塹

階段二、三已實作：PHP API-only、MariaDB、Docker，以及原生 JavaScript 的會員認證、管理員管理、權限矩陣、景點／店家管理、圖片上傳、地圖候選匯入與公開 HTML 發布。原始 `website/` 和 Excel 保留；新頁面在 `frontend/`，PHP 在 `backend/`，工程文件在 `docs/`。

景點、博物館及首頁景點精選已讀真實資料庫，管理新增內容會產生公開 HTML。電影、消息、優惠、路線、收藏與行程保存留後續階段，其餘後台仍顯示尚未開放。

營業時間已依使用者新增要求統一為每週表單，手動建立與 Google 候選共用，可填公休、全天、午休多段及跨日。舊文字保留為備註；格式、相容及升級方式見 [營業時間閱讀指南](docs/modules/opening-hours.md)，其他相近匯入缺口見 [欄位檢查與計畫](docs/OPENING-HOURS-PLAN.md)。

後續計畫已按 P0（階段 2–8）→ P1（9–12）→ P2（13–15）重排，階段一為共用準備；各級有整合驗收界線，詳見 [實作計畫](docs/IMPLEMENTATION-PLAN.md)。目前階段一至三完成本機驗收，P0 尚未完成。

## 本機啟動

需要 Docker Desktop（Linux containers），不需要主機 PHP、Composer、Node 或 MariaDB。

```powershell
./scripts/setup-env.ps1
docker compose --profile mail up -d --build
docker compose exec -T app php bin/console.php db:migrate
docker compose exec -T app php bin/console.php db:seed
# 在根目錄 .env 填 ADMIN_EMAIL，確認 ADMIN_INITIAL_PASSWORD 已有獨立密碼。
./scripts/setup-admin.ps1
# 選用：匯入原始六筆展示景點，不覆寫已有內容；素材仍須正式授權。
docker compose exec -u www-data -T app php bin/console.php content:seed-demo
# 不匯入展示資料時，可用 content:publish 產生空內容頁。
```

網站：http://localhost:8080/；後台：http://localhost:8080/admin/login.html；本機郵件：http://localhost:8025/。固定來源由 Docker Node 建置；景點公開 HTML 由前端 publish.mjs 產生到 publication volume，publisher 每 10 秒檢查排程及重試。Nginx 提供畫面並把 `/api/` 交給 PHP-FPM。

`.env` 是本機設定檔，被 `.gitignore` 排除；`.env.example` 只保留設定欄位與非秘密預設值。初始化腳本不覆蓋已有 `.env`，不列印密碼。Docker 注入環境變數給 PHP；PHP 不自行讀取 dotenv 檔案，也不把設定回傳給前端。

固定後台帳號是規格指定的 `admin`；技術帳號／MariaDB 使用者是 `spl`，資料庫是 `yinghua`。固定管理員 Email 已在本機依使用者提供設定；密碼只在 `.env`。`admin:init` 重跑不改已建立的帳號密碼；更改 `.env` 不會自動變更已建立的資料庫帳號。復原方式見認證指南。

既有階段三環境升級時，先備份資料再執行 db:migrate、重建 frontend，並重啟常駐 publisher：

```powershell
docker compose exec -T app php bin/console.php db:migrate
docker compose run --rm --no-deps frontend
docker compose restart publisher
docker compose exec -u www-data -T app php bin/console.php content:publish
# 僅既有受控展示資料需要：先預覽，再確認加 --apply。
docker compose exec -u www-data -T app php bin/console.php content:backfill-demo-labels
docker compose exec -u www-data -T app php bin/console.php content:backfill-demo-labels --apply
```

新增來源的圖片說明隨景點保存；共用媒體原始來源不受影響。標籤回填跳過已編寫內容，不重設網站帳號。

## 閱讀與驗收

- [全站架構與檔案功能導讀：前後端、資料庫、Docker 與操作流程](docs/PROJECT-GUIDE.md)
- [完整實作計畫](docs/IMPLEMENTATION-PLAN.md)
- [階段三 API／表／頁面對照](docs/PHASE-3-PLAN.md)
- [階段三驗收與差項](docs/PHASE-3-ACCEPTANCE.md)
- [景點與發布程式閱讀](docs/modules/attractions.md)
- [圖片上傳與權限](docs/modules/media.md)
- [地圖候選匯入](docs/modules/maps-import.md)
- [階段二 API／表／頁面對照](docs/PHASE-2-PLAN.md)
- [架構與成熟做法參考](docs/ARCHITECTURE.md)
- [規格與工程補充分別標記](docs/DESIGN-TRACEABILITY.md)
- [認證程式閱讀與帳號操作](docs/modules/authentication.md)
- [管理員與權限程式閱讀](docs/modules/admin-permissions.md)
- [階段二驗收記錄](docs/PHASE-2-ACCEPTANCE.md)
- [OpenAPI 契約](backend/resources/openapi.json)

```powershell
./scripts/setup-test-db.ps1
python backend/tests/api_integration.py
# 測試庫首次需 migrate、seed，並以 www-data 執行 content:seed-demo。
python backend/tests/attractions_integration.py
python backend/tests/content_contract.py
python backend/tests/opening_hours_integration.py
docker compose exec -T app /var/www/tools/vendor/bin/phpunit --configuration phpunit.xml
docker compose exec -T app /var/www/tools/vendor/bin/phpstan analyse --configuration=phpstan.neon --no-progress
docker compose exec -T app /var/www/tools/vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --dry-run
docker compose exec -T app /var/www/tools/vendor/bin/rector process --config=rector.php --dry-run --no-progress-bar
docker compose run --rm --no-deps frontend
docker compose run --rm --no-deps frontend node --test tests/*.test.mjs
```

整合測試使用獨立 `yinghua_test`、8081 與測試 root，只使用 Mailpit，不寄到外部信箱。它不清空 `yinghua`；執行時會復原測試 root 的密碼並撤銷其舊 session，因此不要同時操作 8081 的測試 root。測試輸出／憑證只放忽略的 `artifacts/`。

目前是本機 HTTP 開發設定。正式 SMTP、HTTPS 網域與正式條款尚未提供；部署前需另外設定及驗證，不將本機寄信成功視為正式信箱送達。
