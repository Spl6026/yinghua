# 部署與運維閱讀指南

> **2026-11-03 更新（使用者決定）：** 公開讀取不再逐次比對資料庫，改為一般網站的做法：直接提供最近一次成功發布的版本。後台儲存會立即重新發布；排程時間到、跨日、換週與直接改資料庫，由 publisher 每 10 秒 `content:tick` 比對後重新產生，最多延遲約 10 秒。發布失敗時繼續提供上一版，後台顯示失敗並可重試；只有從未成功發布過時才回 503（`publication_reason=missing_snapshot`）。下方提到「內容改變即回 503、記錄 projection_changed 區段」的段落是舊行為的歷史紀錄。測試站沒有常駐 publisher，需手動執行 `content:tick` 或 `content:publish`。


2026-10-31：階段十二已完成同成功generation的JSON-LD、robots與sitemap，沒有獨立索引資料表或備份。沿DB／媒體／release的content:publish可一起重建；原規則及正式平台交接維持。新檔案用途／命令見[索引指南](search-index.md)，實際結果見[階段十二驗收](../PHASE-12-ACCEPTANCE.md)。

2026-10-30 複驗：本機長駐publisher未載入階段九PHP，公開快照缺detailNeighbors而被版本閘門拒絕；新程序DB唯讀連線正常。依本文件重啟既有publisher後由watch自行同步，恢復公開入口；本輪未手動在主庫執行content:publish，未變更業務資料、服務定義或環境變數。

## 2026-10-25 發布順序與安全診斷修正

消息／優惠 alias 現與景點／電影一樣先按鍵排序，避免無業務變更時因資料庫列順序改變 generation。[PublicationPending](../../backend/src/Exceptions/PublicationPending.php) 僅產生 `publication_reason`（missing_snapshot／projection_changed）、格式已驗的 published／expected hash 及固定白名單 `sections`，不記內容、設定值、Cookie、token 或任意資料鍵。Kernel 的公開 API 與 PublicAttractionController 的 HTML gate 在邊界加 requestId；對外仍是既有安全錯誤／503，gate 仍 403，不放寬發布檢查。

本機 bind mount 更新 PHP 發布邏輯後，既有 `content:watch` 不會卸載已讀入的 class。請先 `docker compose restart publisher`，再 `docker compose exec -T -u www-data app php bin/console.php content:publish`；否則新 API／CLI 可能與舊 worker 交替產生不同版本。正式採不可變映像，更新需同版 app／publisher 一起重建服務，不以只換檔案代替發布。此次主站診斷確實捕捉到 alias 版本差異，重載後多個週期維持一致。

新回歸 [publication_alias_order.php](../../backend/tests/publication_alias_order.php) 在真測試庫交易內強制兩種查詢順序，產物使用自有 `/tmp`、資料 rollback；[publication_diagnostics.py](../../backend/tests/publication_diagnostics.py) 驗 API／gate requestId、拒舊與清理。7／8 項通過；完整結果與歷史 503 尚不能追認的限制見 [本輪修正](../P0-IMPROVEMENTS-20261025.md)。沒有新資料表、HTTP 路由或監控服務。

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

## 8D 備份、復原與流量閘門

operations.py增加backup／verify-backup／restore／open／rollback。備份停止HTTP／worker並退出在途FPM請求，串流logical dump與media，記錄image ID／manifest／generation及hash；restore只接受另一個明確全空目標，完整驗證後才寫入。不完整復原、checksum／缺圖／版本錯誤不能經open開流量。回退只由目前DB重新發布，沒有down migration／舊HTML捷徑。

`Repositories/RecoveryRepository` 只查media keys及撤銷session／reset／challenge；`Adapters/RecoveryMediaInspector`驗UUID平面普通圖檔、DB引用與SHA256，拒絕連結／遺失／未知檔或暫存殘留。`bin/recovery.php`是CLI組裝，撤銷模式另需明確restore-window環境值；普通HTTP無此能力。撤銷三表在同個PdoTransactions內，帳號hash／權限／私人資料與audit保留。

`backend/tests/recovery_rehearsal.py` 用全新自有volume、真GD圖片、兩種真session、設定API及audit、個人權限、收藏、行程順序／時間／備註／version42與發布／下架／別名。最終r5的25項通過：完整業務表逐列hash一致、1張圖hash一致、舊兩種Cookie失效、root trigger保留、損壞dump／缺圖／不相容／非空目標拒絕、發布故障維持關閉及修復。還原target的origin不同，先從相同已提交來源重建其app/web，不能沿用不符origin的manifest。測試fixture初版有重複權限／固定token hash，已改更新既有矩陣與獨立隨機fixture，未變更業務約束；fixture只留在演練volume，結束統一清理。

最終r5小型備份3.500秒、還原4.172秒（1圖）；只量測本輪流程，不是正式SLA。標準unittest另驗壞metadata、tar traversal／symlink與安全CLI錯誤4項。rollback_rehearsal.py完成9項：r6→r5兩個release label切換、全業務資料不變、下架404、schema錯誤關站及修正恢復、正常／上游故障日誌不含秘密query。兩版是同個已提交程式來源，只證明工具與相容manifest流程，不冒稱任意歷史code可回退。

最終部署r5先13項後因Docker DB健康標籤延遲中止；保留分類結果後明示resume14項，與冷啟動3項合計17個不同驗收，不改成全程一次無失敗。工具先等待DB容器healthy，不以API200替代Compose相依條件。當前完整結果及正式差項以 [階段八紀錄](../PHASE-8-ACCEPTANCE.md) 為準；健康契約由phase8_health_contract.py以jsonschema4.26.0驗6個真HTTP回應，沒有假造503。

映像掃描命令：`python scripts/operations/scan-images.py --help`，固定Trivy版本且要求新的私有輸出目錄；exit1有漏洞必須保留。正式r5仍有上游HIGH／CRITICAL，詳見 [安全報告](../PHASE-8-SECURITY-REVIEW.md)，不因沒有vendor修補就算通過。
