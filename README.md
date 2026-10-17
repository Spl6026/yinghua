# 映畫竹塹

階段二已實作：PHP API-only、MariaDB、Docker，以及原生 JavaScript 的會員認證、管理員管理與權限矩陣。原始 `website/` 和 Excel 保留；新頁面在 `frontend/`，PHP 在 `backend/`，工程文件在 `docs/`。

景點、電影、消息、優惠、路線、收藏與行程的正式業務流程留後續階段。公開內容仍是原始交付的唯讀展示資料，其餘後台頁顯示尚未開放，不模擬保存。

後續計畫已按 P0（階段 2–8）→ P1（9–12）→ P2（13–15）重排，階段一為共用準備；各級有整合驗收界線，詳見 [實作計畫](docs/IMPLEMENTATION-PLAN.md)。目前仍只有階段一、二完成。

## 本機啟動

需要 Docker Desktop（Linux containers），不需要主機 PHP、Composer、Node 或 MariaDB。

```powershell
./scripts/setup-env.ps1
docker compose --profile mail up -d --build
docker compose exec -T app php bin/console.php db:migrate
docker compose exec -T app php bin/console.php db:seed
# 在根目錄 .env 填 ADMIN_EMAIL，確認 ADMIN_INITIAL_PASSWORD 已有獨立密碼。
./scripts/setup-admin.ps1
```

網站：http://localhost:8080/；後台：http://localhost:8080/admin/login.html；本機郵件：http://localhost:8025/。前端由 Docker Node 建置成固定 HTML，Nginx 提供畫面並把 `/api/` 交給 PHP-FPM。

`.env` 是本機設定檔，被 `.gitignore` 排除；`.env.example` 只保留設定欄位與非秘密預設值。初始化腳本不覆蓋已有 `.env`，不列印密碼。Docker 注入環境變數給 PHP；PHP 不自行讀取 dotenv 檔案，也不把設定回傳給前端。

固定後台帳號是規格指定的 `admin`；技術帳號／MariaDB 使用者是 `spl`，資料庫是 `yinghua`。固定管理員 Email 已在本機依使用者提供設定；密碼只在 `.env`。`admin:init` 重跑不改已建立的帳號密碼；更改 `.env` 不會自動變更已建立的資料庫帳號。復原方式見認證指南。

## 閱讀與驗收

- [全站架構與檔案功能導讀：前後端、資料庫、Docker 與操作流程](docs/PROJECT-GUIDE.md)
- [完整實作計畫](docs/IMPLEMENTATION-PLAN.md)
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
docker compose exec -T app /var/www/tools/vendor/bin/phpunit --configuration phpunit.xml
docker compose exec -T app /var/www/tools/vendor/bin/phpstan analyse --configuration=phpstan.neon --no-progress
docker compose exec -T app /var/www/tools/vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --dry-run
docker compose exec -T app /var/www/tools/vendor/bin/rector process --config=rector.php --dry-run --no-progress-bar
docker compose run --rm --no-deps frontend
docker compose run --rm --no-deps frontend node --test tests/*.test.mjs
```

整合測試使用獨立 `yinghua_test`、8081 與測試 root，只使用 Mailpit，不寄到外部信箱。它不清空 `yinghua`；執行時會復原測試 root 的密碼並撤銷其舊 session，因此不要同時操作 8081 的測試 root。測試輸出／憑證只放忽略的 `artifacts/`。

目前是本機 HTTP 開發設定。正式 SMTP、HTTPS 網域與正式條款尚未提供；部署前需另外設定及驗證，不將本機寄信成功視為正式信箱送達。
