# 映畫竹塹：專案準備

目前只有原始交付、實作計畫、Docker 環境、PHP 工具套件與 skills。**網站應用程式尚未開始：沒有 backend/、frontend/、正式 API、業務資料表或應用測試。**

website/ 與根目錄 Excel 保留原始交付。Nginx 只提供原型 HTML／CSS／JS 預覽，頁面的 localStorage、模擬登入與模擬後台仍是原型功能；預覽不表示已完成正式功能。PHP-FPM 是備妥的執行環境，目前不處理網站請求。MariaDB 的 yinghua 是空資料庫，登入帳號為 spl。

## 計畫與架構

- [完整實作計畫](docs/IMPLEMENTATION-PLAN.md)
- [階段二細部計畫：API／資料表／頁面](docs/PHASE-2-PLAN.md)
- [API-only 架構與程式閱讀方式](docs/ARCHITECTURE.md)
- [規格、文件草案、使用者指定與工程補充](docs/DESIGN-TRACEABILITY.md)
- [原始功能規格對照](docs/REQUIREMENTS-MAP.md)
- [開發流程與 skills](docs/DEVELOPMENT-WORKFLOW.md)
- [環境安裝與驗證](docs/ENVIRONMENT-SETUP.md)

後續從零建立 frontend/ HTML／原生 JS／jQuery 與 backend/ PHP API，採 Controller／Service／Repository、DTO 與共用政策分工。沒有既有後端可遷移。

## 啟動環境

```powershell
./scripts/setup-env.ps1
docker compose --profile mail up -d --build --wait
docker compose exec -T app composer install --no-interaction --prefer-dist --no-scripts --no-plugins
```

原型預覽：http://localhost:8080/。本機郵件：http://localhost:8025/。不需要郵件時可省略 --profile mail。

setup-env.ps1 只在 .env 不存在時產生秘密，不覆蓋已有設定，也不印出密鑰。主機不需另裝 PHP、Composer 或 MariaDB；Docker Desktop 使用 Linux containers。正式部署與正式 SMTP 尚未設定。

## 日常操作與工具確認

```powershell
docker compose ps
docker compose exec -T app composer validate --strict
docker compose exec -T app composer check-platform-reqs
docker compose exec -T app composer audit:locked
docker compose exec -T app php vendor/bin/phpstan --version
docker compose exec -T app php vendor/bin/php-cs-fixer --version
docker compose exec -T app php vendor/bin/rector --version
docker compose exec -T app php vendor/bin/phpunit --version
docker compose stop
```

目前只能驗證環境與套件；沒有應用可進行型別、格式、單元或整合測試。開始寫程式時再建立工具規則、PSR-4 autoload、migration／seed 與測試。Rector 永遠先 dry-run。

## 目前目錄

| 位置 | 用途 |
| --- | --- |
| website/、根目錄 Excel | 原始交付，保持不變 |
| docs/ | 實作／架構計畫、來源對照、開發與環境文件 |
| compose.yaml、docker/ | 原型靜態預覽、PHP-FPM、MariaDB、Mailpit 環境 |
| tools/php/composer.json、composer.lock | 獨立工具及依賴清單，不含應用 autoload／程式 |
| scripts/setup-env.ps1 | 本機 .env 初始化 |
| .agents/skills/ | 開發流程使用的 skills |

vendor_data 保留套件，db_data 保留資料庫環境，mail_data 保存本機郵件。一般 stop／restart／down 不刪 volume；需要保留資料時勿使用 down -v。.env、vendor、快取與 artifacts 備份不提交。

## Git 規則

第一筆提交 56733e5 保存原始網站與規格，不修改。提交反映實際專案進度；代理自身失誤的修正合併回對應內容，不另算進度。重整歷史前備份，之後確認原始交付與最終檔案完整保留。規則見 AGENTS.md。
