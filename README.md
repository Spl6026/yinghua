# 映畫竹塹

PHP API＋MariaDB＋原生 JavaScript／jQuery，使用 Docker 開發。階段一至十五的本機範圍已完成；正式網域、外部寄信與分享／索引平台仍依交接文件驗證。

## 先看這幾份文件

- [專案資料夾與產物管理](docs/PROJECT-STRUCTURE.md)：各目錄用途、可清理項目、歷史證據位置。
- [全站架構與檔案導讀](docs/PROJECT-GUIDE.md)：前後端分層、API、資料表與操作。
- [Excel 30列逐項驗收](docs/EXCEL-FUNCTIONAL-VERIFICATION-20261102.md)：要求、實測結果及正式待驗。
- [後台操作指南](docs/ADMIN-OPERATING-GUIDE.md)／[正式server交接](docs/PRODUCTION-HANDOFF.md)。
- [開發流程](docs/DEVELOPMENT-WORKFLOW.md)／[Git規則](docs/GIT-WORKFLOW.md)／[實作計畫](docs/IMPLEMENTATION-PLAN.md)。

## 資料夾

| 目錄 | 用途 |
| --- | --- |
| `backend/` | PHP API、Controller／Service／Repository、資料庫migration與測試 |
| `frontend/` | HTML來源、JavaScript／CSS、共用模板、建置與測試 |
| `website/` | 原始交付範本，保留原狀 |
| `docs/` | 架構、階段計畫／驗收、模組指南與非敏感證據 |
| `docker/` | PHP與Nginx環境設定 |
| `scripts/` | 環境初始化、維運與交付驗證入口 |
| `tools/` | 開發品質／無障礙／驗證工具與依賴清單 |
| `.cache/` | 可重建的本機工具快取，不進Git／Docker build context |
| `artifacts/` | 本機驗收產物與私有歸檔，不進Git／公開網站 |

## 本機啟動

需要 Docker Desktop（Linux containers）；PHP、Composer及品質工具在Docker執行。

```powershell
./scripts/setup-env.ps1
docker compose --profile mail up -d --build
```

首次空庫才執行 `db:migrate`、`db:seed` 及 `./scripts/setup-admin.ps1`；先依[環境指南](docs/ENVIRONMENT-SETUP.md)設定 `.env`。既有資料庫不重設帳密、不重匯展示資料。日常啟動用 `docker compose --profile mail up -d --wait`。

- 網站：http://localhost:8080/；後台：http://localhost:8080/admin/login.html。
- 本機郵件：http://localhost:8025/；隔離驗收站：http://localhost:8081/。
- 技術帳號 `spl`、資料庫 `yinghua`；DBeaver連線見[全站導讀](docs/PROJECT-GUIDE.md)。
- `.env` 保留本機設定，禁止提交；`.env.example` 是欄位範本。

## 驗證與重建

```powershell
# Python驗證依賴獨立安裝；Windows與WSL各自建立相容快取。
python -m pip install --target .cache/validation/python -r tools/validation/requirements.txt
python scripts/validation/delivery.py inventory
docker compose run --rm --no-deps frontend node --test 'tests/*.test.mjs'
docker compose exec -T app-test php /var/www/tools/vendor/bin/phpunit -c phpunit.xml
docker compose exec -T app-test php /var/www/tools/vendor/bin/phpstan analyse --no-progress
```

完整整合測試須先建立自有 `yinghua_test` fixture，指令與清理見[交付驗證指南](docs/modules/delivery-validation.md)。部分舊認證測試會重設測試root，只能對隔離庫執行；不可用主庫帳號驗收。

公開內容由 `content:publish` 產生同版HTML，publisher每10秒檢查排程。部署至既有server不強制使用Docker，正式能力與設定依交接指南處理。
