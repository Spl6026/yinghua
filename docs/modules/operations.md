# 部署與運維閱讀指南

更新：2026-10-24。來源是階段八 E-29；具體診斷／時限不是業主新增的業務限制。實作進行中，正式上線與完整 P0 尚未驗收。

## 8A 穩定性診斷

`backend/src/Http/Csrf.php` 保留簽章、身分命名空間、Cookie 綁定、Origin 與到期拒絕。原本「到期時間不可超過現在加 TTL」在時鐘回撥一秒時也拒絕剛簽發的 token；現在僅對未來上限容許五秒校時差，到期下限不延長。一次驗證只讀一次時鐘。`backend/tests/Unit/CsrfTest.php` 以真簽章／可控 Clock 驗同秒、回撥一／五／六秒、到期與其他安全拒絕。不是關閉 CSRF 或讓前端無限重送。

拒絕日誌只有 `request=<id> csrf_reason=<有限原因>`：origin、anonymous-cookie、format、expired、future、signature；不記個資、Cookie、token 或 body。配合 Nginx requestId 判斷流程，不能從不同 requestId 推定同一使用者。

`backend/tests/stability_observation.py` 固定100輪 csrf GET → 空註冊 POST，預期422且不自動重試；僅寫 requestId／狀態／錯誤碼／到期時間差／耗時到忽略的 artifacts。2026-10-24 修正前100輪均為422，沒有回撥；受控測試另證明回撥邊界。歷史未捕捉回應的註冊失敗仍不能直接認定原因，整合觀察尚未結束。

測試站沒有常駐 publisher。這次其公開首頁503在執行 `docker compose exec -T -u www-data app-test php bin/console.php content:tick` 後恢復，測試容器 health 從 unhealthy 轉 healthy；這只能證明當次待發布可復原，不將所有歷史 PUBLICATION_PENDING 都歸同因。

命令：`docker compose exec -T app php /var/www/tools/vendor/bin/phpunit -c phpunit.xml --filter CsrfTest`；`python backend/tests/stability_observation.py`。Python 使用主機既有 runtime，PHP 僅在 Docker 執行。

## 8B 核心健康 API

`GET /api/v1/health` 匿名、唯讀，不需要或發出 Cookie。200 僅回 status、公開 APP_VERSION 與 requestId；503 為固定 SERVICE_UNAVAILABLE，不含 SQL、主機、密碼、migration 名稱或例外訊息。HEAD 同狀態且無 body，其他方法405／Allow。只有精確路徑提前分派；其他 API 繼續原權限管線。

資料流：`public/index.php` → `Composition::health()` → `Controllers/HealthController` → `Services/ApplicationHealth` → `Contracts/DatabaseHealthProbe`／`Adapters/PdoDatabaseHealthProbe`。`DTO/HealthReport` 限定輸出；`Config::applicationVersion()` 只允許64字內公開標籤，本機預設 development，正式環境必填。正常 API 的 eager DB 組裝不影響這條故障入口。

probe 獨立 PDO 連線，上限2秒，mysqlnd 讀取與 MariaDB statement／metadata lock 各2秒；只 SELECT 1 與一次 migration 清單，核對現有18份名稱／checksum／完成時間，不建表或 seed。schema 多出未知 migration 亦不健康，因此舊映像回退需明確驗證相容，不假定可向前相容。SMTP／Google／公開快照另驗，不放入每次健康請求。

普通API的共用Database連線也限2秒，連線失敗轉成安全SERVICE_UNAVAILABLE／503；不把一般SQL錯誤一概當服務中斷。Kernel共用錯誤映射，`tests/connection_boundary.php`另驗eager組裝前的拒絕；測試限定yinghua_test，不封入正式映像。PDO的TCP設定不涵蓋glibc DNS，Compose另設timeout:1／attempts:1／ndots:0；Nginx resolver／FastCGI connect各2秒，公開gate與上游失敗也映成503，不供應舊頁。端到端時限須連同此Docker環境驗證，不能僅憑PDO選項宣稱成立。

`backend/tests/health_integration.py` 實際 HTTP 與隔離 MariaDB 驗18項、六種 CLI 故障注入（連線拒絕、密碼、庫不存在、APP_KEY、version、origin）及普通API連線邊界；`tests/health_probe.php` 僅 test 環境可用，不封入正式映像。missing／未完成／checksum 錯誤後逐欄還原 migration journal，復原200。HTTP health 不發 Cookie，修改 journal 只由測試 fixture 完成。真實 DB 暫停／網路無回應的 HTTP 逾時另在部署隔離演練驗證。

目前72個 API 操作、67固定頁、33表／18 migration，沒有新增 schema。PHP 87項／302斷言、PHPStan10／PHPat通過。契約見 `backend/resources/openapi.json`；實際命令：`python backend/tests/health_integration.py`。完整整合驗收仍進行中。

## 8C 正式封裝與隔離部署

`docker/php/Dockerfile` 新增 release-frontend、release-base、release、release-web；保留 development target。正式base更新套件並依實際ELF連結保留runtime libraries，移除編譯器／headers／git；web改用已修補的官方Nginx1.30.6-alpine-slim，正式MariaDB仍12.3.3但更新官方digest。本機Compose的DB查看設定未改。release-manifest.mjs 在同個前端stage產生版本、commit、origin／條款版本及backend／migration／dist雜湊，app/web各有一份 `/var/www/release.json`；開站工具拒絕與runtime設定不符。`15-production-tls.sh` 每次從不變共用Nginx模板重建TLS設定，避免restart重複改寫；正式沒有另一套分叉API路由。

`compose.production.yaml` 只有四服務／三獨立資料卷，不與開發檔疊加。`bin/readiness.php` 共用既有 ApplicationHealth／ContentPublication，只提供CLI核心／公開就緒JSON。`operations.py` preflight驗隔離目標、HTTPS、秘密長度、服務／掛載／port與log輪替，status驗核心／快照／app-web manifest；錯誤不直接轉印帶秘密的Compose或DB輸出。`build-release.py`要求來源已提交、commit一致及新標籤。

`backend/tests/production_rehearsal.py` 限自有 spl-p0-* project，初次要求全空庫；直接建新資料卷，不複製主測試庫。實際已驗空庫HTTP503／migration重跑／固定admin初始化／首版公開、TLS／Cookie／308／HSTS、DB暫停503<5秒、FPM停止非200、worker停用到期拒絕舊HTML及重啟同generation。初次腳本有Secure旗標大小寫與公開settings URL錯誤，修正後明確resume已初始化fixture，沒有把這些誤報當應用缺陷。正式映像第一版亦修正templates目錄建立，歸本功能提交。完整乾淨發布重演與整階段驗收仍待後續記錄；不宣告正式環境通過。

完整部署設定／操作見 [P0-OPERATIONS](../P0-OPERATIONS.md)。新增設定只有公開APP_VERSION與release／Compose部署參數；本機DBeaver3306未改。沒有新API、表、migration或HTML頁。
