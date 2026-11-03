# PHP 8.5.12 安全更新

開始日期：2026-10-21；官方映像切換：2026-10-22。本次為工程環境安全維護（E），不代表階段五開始。

PHP 官方公告 CVE-2026-109618／CVE-2026-109619，修補版為 PHP 8.5.12；原主程式及隔離測試的 CLI／FPM 為 8.5.11。PHP-FPM 亦在影響範圍，Docker 或 API-only 架構不能取代修補。來源：[PHP 公告](https://www.php.net/archive/2026.php)、[OOM 公告](https://github.com/php/php-src/security/advisories/GHSA-w8hw-w9fx-j79w)、[狀態殘留公告](https://github.com/php/php-src/security/advisories/GHSA-6wmf-4rv5-p3rp)。

## 最終建置來源

| 項目 | 固定來源 |
| --- | --- |
| 官方映像 | `php:8.5.12-fpm-bookworm` |
| OCI index digest | `sha256:d3db5340c7ecfb1e3a26af5d3d9de8b4e160cdd75692a830b5cb74b0b85db2cf` |
| linux/amd64 manifest | `sha256:01dffdbc0226d67553e2651e927e5db95429e265803a97786ef17760410301bd` |
| 官方配方提交 | `docker-library/php@f6048b5f6e3c2202ee082665a4ccc0d90de291a3` |
| Docker build 防護 | 實際 PHP_VERSION 必須為 8.5.12，並執行 php-fpm -v |

`docker/php/Dockerfile` 沿用專案原本的多階段結構，僅更新 PHP 基底的明確版本／digest 並加入實際版本檢查。Composer、Node、MariaDB、Nginx 與套件 lock 未變更；沒有更動業務欄位、資料表、migration 或前端版型。資料、媒體與發布 volumes 持續使用，不執行 down -v。

更新開始時，官方 8.5.12 標籤仍為空 OCI index，pull 回報沒有 linux/amd64 manifest；一般 8.5-fpm-bookworm 當時仍為 8.5.11。因此先用同一官方固定配方、原始碼 SHA-256 與簽章建置 8.5.12；其 PHP 55 項／233 斷言、PHPStan／PHPat 與 21 組整合已通過。10 月 22 日重查官方映像已可下載，依使用者要求立即換回，臨時原始碼編譯配方及 docker/php/upstream 工具全部移除。上述應急測試不冒稱為最終官方映像的測試結果。

## 重建與驗證命令

```powershell
docker compose --profile test build app app-test publisher
docker compose --profile test up -d --no-deps --force-recreate --wait app-test web-test
docker compose exec -T app-test php -v
docker compose exec -T app-test php-fpm -v
docker compose exec -T app-test composer check-platform-reqs
docker compose exec -T app-test php /var/www/tools/vendor/bin/phpunit --configuration=phpunit.xml
docker compose exec -T app-test php /var/www/tools/vendor/bin/phpstan analyse --configuration=phpstan.neon --no-progress
docker compose exec -T -u www-data app-test php bin/console.php content:publish
python backend/tests/openapi_contracts.py --dependency-path artifacts/audit-jsonschema --include-films
```

隔離驗證後替換主程式及發布服務，讓所有 worker 使用新 binary：

```powershell
docker compose up -d --no-deps --force-recreate --wait app
docker compose up -d --no-deps --force-recreate publisher
docker compose exec -T app php -v
docker compose exec -T app php-fpm -v
docker compose exec -T publisher php -v
docker compose exec -T -u www-data app php bin/console.php content:publish
```

publisher 原本沒有 healthcheck，分開啟動並確認運行狀態，不將它列為 healthy。測試工具只操作受 fixture 保護的 yinghua_test；主庫管理員未被本輪重設。主站確認首頁、後台登入頁、片單、公開電影 API 與兩種 CSRF 端點；專案沒有 /api/v1/health，不將不存在的端點當作健康檢查。

## 最終官方映像驗證

驗證日期：2026-10-22。下列為最終官方映像的結果，與前述應急編譯驗證分開記錄。

| 檢查 | 實際結果 |
| --- | --- |
| 實際版本 | app／app-test 的 CLI 與 FPM、publisher CLI 均為官方 8.5.12（built Oct 21 15:49:49） |
| 執行中的應用 image | 三服務同為 `sha256:a47c6c235e49459cda7271899af39ce300ff21754fe90d4c37914541bb320386` |
| PHPUnit | 55 tests、233 assertions 通過 |
| PHPStan level 10／PHPat | 無錯誤 |
| Composer 平台需求 | 應用與品質工具均通過 |
| PHP 擴充／FPM 設定 | 擴充清單與更新前相同；php-fpm -t 通過 |
| 主站 HTTP | 首頁、後台登入、片單、公開電影 API、會員／管理員 CSRF 共六個端點均回 200 |
| 服務狀態 | app、app-test、web、web-test、db、mailpit healthy；publisher running |
| MariaDB／HTTP／OpenAPI | 21 組通過；54 操作、788 回應、260 成功請求，0 契約錯誤；Google 外部呼叫 0 |

本機證據保存在被 Git 忽略的 `artifacts/php-security-official-*20261022.*`；包含建置、PHPUnit、PHPStan、平台需求、最終契約報告、主站 HTTP、執行中 image ID 與官方 OCI manifest。應用及品質工具的套件 lock 與更新前相同，本輪先前 Composer 鎖檔 audit 未發現安全公告。PHP binary 的修補判定依官方 release 與實際版本，不以 Composer audit 取代。

Git 交付使用 `maintenance/php-8.5.12-security` 的一筆完整維護提交；合併 main 前的全部 refs 備份為 `artifacts/php-security-before-merge-20261022.bundle`。原始 Excel／website 與既有階段分支保持原內容，不推送遠端。本輪沒有新增業務功能或 schema，正式 HTTPS／SMTP 等既有待驗項維持原狀。
