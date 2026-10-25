# 開發環境安裝與驗證

2026-10-26：新增供 U-19 背景驗收的 [隔離讀屏環境](modules/isolated-accessibility.md)，位於 `tools/accessibility/`，獨立 Compose 專案／loopback 6091／有上限的 CPU 記憶體與暫存。`run.ps1` 建置、重建並等待 healthy，`-Stop` 只停止該桌面；`collect.py` 保存真實 Orca 輸出及版本／隔離設定。已實際建置、啟動並採證，不需 Windows NVDA、主機桌面或音效掛載；不是正式網站容器，不改主站環境變數或 DB schema。完整 AA 仍以實際覆蓋判定。

2026-10-25 發布程式更新提醒：PHP bind mount 會讓新 FPM 請求讀新檔，但長駐 `publisher` 仍保留已載入的 class。修改發布相關 PHP 後執行 `docker compose restart publisher`，再 `docker compose exec -T -u www-data app php bin/console.php content:publish`，避免新 API 與舊 worker 互相判斷版本失效。正式不可變映像須同版更新 app／publisher。實測與安全診斷見 [P0 修正紀錄](P0-IMPROVEMENTS-20261025.md)，沒有新增環境變數或變更 DB port。

2026-10-22 安全維護：PHP 更新為官方 8.5.12 FPM／CLI 映像並鎖定 digest；已將原先應急的官方原始碼編譯配方換回官方預建映像。來源、重建命令與實際結果見 [PHP 安全更新](PHP-SECURITY-UPDATE-20261021.md)。下方 8.5.11 等內容保留 10 月 13 日的歷史環境紀錄。

> 本文件記錄 2026-10-13 應用實作前的環境安裝；目前應用、啟動與驗收狀態見 README.md 與 PHASE-2-ACCEPTANCE.md。

日期：2026-10-13。範圍：Docker、PHP／Composer、工具套件與本機郵件；不包含網站應用程式。

## 1. 已安裝環境與套件

| 工具／套件 | 版本 | 用途 |
| --- | --- | --- |
| PHP | 8.5.11 | PHP-FPM／CLI 環境 |
| MariaDB | 12.3.3 | 空的 yinghua 資料庫，帳號 spl |
| Nginx | 1.30.5 | 原始 website 靜態預覽 |
| Composer | 2.10.3 | 套件鎖定與安裝 |
| PHPStan | 2.3.1 | 後續型別分析 |
| PHP-CS-Fixer | 3.95.27 | 後續 PER-CS 格式 dry-run |
| Rector | 2.7.0 | 後續 PHP 更新建議，只用 dry-run |
| PHPat | 0.12.5 | 後續分層檢查 |
| PHPUnit | 13.4.2 | 後續單元／整合測試 |
| PHPMailer | 7.1.1 | 後續寄信依賴 |
| Mailpit | 1.31.5 | 本機郵件收集與檢視 |

Docker 映像使用明確 digest；依賴使用 tools/php/composer.lock，沒有更新套件版本。PDO MySQL、mbstring、intl、gd、zip 與 Composer／測試所需擴充備妥。

## 2. 目前配置

- Compose app 為 PHP-FPM 工具環境，工作目錄 /var/www/tools；僅掛載 tools/php/ 與 vendor_data，不掛載任何應用程式。
- Dockerfile 的 development target 安裝開發套件，runtime target 僅包含 PHPMailer 等執行依賴；兩者均不含網站 source、PHP 入口或模板。
- Nginx 只掛載原始 website/ 並提供靜態檔，無 PHP FastCGI 路由。/api/* 目前回 404，不提供 CSRF、health 或認證 API。
- MariaDB yinghua 沒有業務表、migration 表、角色資料或應用帳號；DB 連線使用者 spl 是環境帳號。
- Mailpit 使用可選 mail profile；UI 僅綁 localhost，SMTP 1025 僅在容器網路內，沒有正式 relay。
- .env 的秘密保留不輸出。PHP／Composer 主機不需另外安裝。
- 沒有應用 autoload、PHPStan 分析範圍、格式規則、Rector 專案規則、PHPat 規則或 PHPUnit 案例；開始實作時再建立。

## 3. 操作

```powershell
./scripts/setup-env.ps1
docker compose --profile mail up -d --build --wait
docker compose exec -T app composer install --no-interaction --prefer-dist --no-scripts --no-plugins
docker compose exec -T app composer validate --strict
docker compose exec -T app composer check-platform-reqs
docker compose exec -T app composer audit:locked
```

預覽 http://localhost:8080/；Mailpit http://localhost:8025/。工具版本命令見 README.md。composer.lock 改動時重建映像，並 install 同步 vendor_data。

## 4. 驗證範圍

環境驗證包括 Docker 建置與四服務健康、Nginx 靜態頁與私有檔阻擋、套件 manifest／lock／平台需求及工具版本入口、資料庫連線與空表狀態、Mailpit 可用。PHP-FPM 健康檢查只確認服務連接埠，不執行應用程式。

這些不是功能、架構、PHPStan、格式、Rector、PHPat、單元或整合測試的通過紀錄；目前根本沒有應用程式供測試。

本次實際環境檢查結果：

| 檢查 | 結果 |
| --- | --- |
| Docker 重建與啟動 | web／app／db／mailpit 四服務 healthy |
| Composer | manifest／lock、平台需求及依賴 audit 通過；套件版本未改動 |
| PHP 工具 | PHPStan、PHP-CS-Fixer、Rector、PHPUnit 版本入口可執行；PHPat／PHPMailer 已安裝 |
| MariaDB | spl 帳號連線成功；yinghua 有 0 張表 |
| Nginx | 設定檢查通過；57 個原型 HTML 的 HTTP 內容與原檔相同 |
| 未實作介面 | /api/v1/health、/api/v1/auth/csrf 回 404；無 PHP 網站入口 |
| Mailpit | 本機服務 API 可回應；未串接網站寄信 |
| 原始交付 | website 68 檔與第一筆提交逐位元一致；原始 Excel 無差異 |
| 文件 | 26 個 API 全部預計新增、13 張表全部計畫新增、30 項規格功能均未開始 |

## 5. 工具依據

- [PHPStan Getting Started](https://phpstan.org/user-guide/getting-started)
- [PHP-CS-Fixer](https://cs.symfony.com/doc/installation.html)
- [Rector](https://getrector.com/documentation/config-configuration)
- [PHPUnit](https://phpunit.de/getting-started.html)
- [Mailpit Docker](https://mailpit.axllent.org/docs/install/docker/)
