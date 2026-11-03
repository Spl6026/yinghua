# P0 部署與運維

2026-11-01更新：現行封裝已涵蓋階段十三／十四；階段十五重驗17項冷啟動、32項擴充還原與9項回退，見[驗收](archive/PHASE-15-ACCEPTANCE.md)。正式環境與安全差項尚未簽核；既有server交接見[清單](PRODUCTION-HANDOFF.md)。下方P0日期描述保留當時演練背景，命令仍由現有CLI驗證。

更新：2026-10-24。E-29 工程基線，實作／演練進行中；尚未正式部署。PHP8.5.12官方映像與 MariaDB12.3 pinned digest沿用已確認版本，實作日核對[PHP公告](https://www.php.net/releases/8_5_12.php)／[MariaDB發布紀錄](https://mariadb.com/docs/release-notes/community-server/12.3/12.3.3)。這不等於映像漏洞掃描已通過。

## 發布設定

本機仍用 compose.yaml；正式**只用 compose.production.yaml**，不要疊加兩份。複製 `scripts/operations/production.env.example` 到被忽略的 `.env.production`；設定正式 APP_URL、公開 APP_VERSION、當前完整 RELEASE_COMMIT、從未使用過的 app/web release tag、持久 APP_KEY／DB秘密／SMTP／Google金鑰、業主 TERMS_VERSION、憑證目錄（fullchain.pem／privkey.pem）。秘密只在部署端，禁止完整展開 Compose 到工單或公開輸出。

四個常駐服務：db、app、publisher、web。DB／FPM沒有宿主機port，只有web80／443；只有憑證目錄為唯讀 bind，其他為獨立named volumes。app/publisher同映像，web/static/renderer從同一release產生；沒有主機來源掛載、Mailpit、測試服務或品質工具。原始 website／Excel、tests／fixtures／本機dist／.env不封裝。正式不內建示範資料，需使用受控匯入／媒體CLI載入核准內容。

`scripts/operations/operations.py` 為 Python3標準函式庫＋Docker CLI，Windows／Linux共用；這是實作時對原POSIX工具初案的調整，不要求主機PHP或sh。命令中的python請使用主機既有Python3，或Windows已配置的bundled runtime。所有PHP仍在Docker執行。

```text
python scripts/operations/operations.py --env-file .env.production --project yinghua-production preflight
python scripts/operations/build-release.py --env-file .env.production --project yinghua-production
docker compose --env-file .env.production -f compose.production.yaml up -d db app
docker compose --env-file .env.production -f compose.production.yaml exec -T app php bin/console.php db:migrate
docker compose --env-file .env.production -f compose.production.yaml exec -T app php bin/console.php db:seed
docker compose --env-file .env.production -f compose.production.yaml exec app php bin/console.php admin:init
```

build-release拒絕未提交發布來源、錯誤commit及已存在映像tag；勿用development標籤覆寫正式映像。需要registry時發布到受控registry並改成digest reference，另保存實際app/web Image ID與release.json；manifest不透過HTTP公開。相容性以migration名稱／checksum／完成狀態實驗證，不能只看版本字串。

admin:init互動輸入Email、密碼與確認；不要把密碼當命令參數或貼到聊天。既有庫升級不重設APP_KEY、密碼或重新匯入原型。

既有站台升級新版本時使用 `upgrade`：先關閉 web／publisher，以新映像啟動 app、執行 `db:migrate`（自帶鎖與 checksum，可重跑），確認 readiness 與媒體後重新發布，最後經 `open` 的檢查才開放流量；不要先 `up -d` 再另外跑 migration，否則新程式會在舊資料結構上服務請求。

```text
python scripts/operations/operations.py --env-file .env.production --project yinghua-production upgrade
```

正式映像另載入 `docker/php/production.ini`（OPcache 不檢查檔案變動），autoloader 使用 `--classmap-authoritative`；PHP-FPM 為 16 個 worker、單一請求 30 秒上限。各服務設有 `mem_limit`（`DB_MEMORY_LIMIT` 等變數可調，見 production.env.example）。publisher 每輪更新心跳檔並有健康檢查，超過 2 分鐘沒更新會標成 unhealthy；Compose 不會自動重啟 unhealthy 容器，需由外部監控告警後重啟 publisher。內容／媒體匯入指令見 [公開內容指南](modules/public-content.md)，確認素材授權後再執行。

```text
docker compose --env-file .env.production -f compose.production.yaml exec -T -u www-data app php bin/console.php content:publish
docker compose --env-file .env.production -f compose.production.yaml exec -T app php bin/readiness.php
docker compose --env-file .env.production -f compose.production.yaml exec -T -u www-data app php bin/readiness.php public
python scripts/operations/operations.py --env-file .env.production --project yinghua-production open
python scripts/operations/operations.py --env-file .env.production --project yinghua-production status
```

空庫health503；HTTP不會偷偷migration／建admin。先成功發布才開正式web，worker每10秒tick；worker不依賴web，沒有啟動環。`bin/readiness.php core`檢核心；`public`另檢DB投影／快照generation一致。容器health只證明FPM或Nginx存活，不能當內容已發布。GET /api/v1/health200亦不代表SMTP、Google或業務內容全正常。

## HTTPS 與日誌

web直接TLS1.2／1.3，80回308到APP_URL、HTTPS有HSTS；Secure／HttpOnly／SameSite=Lax Cookie由既有API發出。憑證取得、更新與正式信任鏈尚待主機確定。若另有反向代理，另設計限制入口與可信代理，不能直接相信任意 X-Forwarded-*。隔離自簽測試只供驗證客戶端明確載入測試憑證，不改使用者信任庫／hosts、不忽略瀏覽器警告，不當正式TLS證據。

Nginx存取日誌只記時間、method、無query的uri、status與requestId；正式Nginx原始error log停用，避免上游錯誤把原始URL／token／IP帶入，改以安全access狀態及PHP例外類別診斷。FPM格式只記時間／method／status／requestId，無IP／URI。Docker輪替10MB×3為初始E值，不自動清除正常audit、auth_events或備份；保存／異地／加密政策仍待營運確認。

原型或特定UI差異、完整安全掃描、正式SMTP／Google／正式內容／條款與正式網域驗證依驗收紀錄，不從本文件推定已通過。P1／P2功能沒有提前開放。

## 備份與空目標還原

只由有部署權限的操作人員使用。工具拒絕本機 `yinghua` project，必須明確指定正式／演練的另一個project；正式主庫實際復原仍需另外確認目標與時間點。本次只演練自有 spl-p0-*，沒有覆寫主測試庫。

```text
python scripts/operations/operations.py --env-file .env.production --project yinghua-production backup --backup-directory /private/backups/p0-20261024
python scripts/operations/operations.py --env-file .env.production --project yinghua-production verify-backup --backup-directory /private/backups/p0-20261024
```

維護窗口會停止web／publisher，重啟FPM使已進入的請求退出，再取得同窗口的 MariaDB logical dump（single-transaction／quick／trigger／routine／event）及media tar／逐檔SHA256，連同release／image ID／generation存清單。禁止同時由其他CLI／DB工具寫入或DDL；網站入口停用不會限制部署人員的Docker／DB特權。完成或備份失敗後只恢復原本運行的入口，不自動開啟原本關閉的服務。

新備份目錄不可已存在，庫／圖檔以串流處理；倉庫內只准寫忽略的artifacts，或使用倉庫外的私有路徑。DB dump含個資與密碼雜湊，需權限限制、加密及異地保存；Windows須另配置ACL，Python的mode700不代表Windows ACL已設好。checksum驗意外毀損，不能當來源真實性簽章；只還原可信的受保護備份。APP_KEY、SMTP秘密、Google Key與憑證另作安全備份，清單只保存APP_KEY指紋，沒有原文秘密。不自動刪除任何正常audit或舊備份，保留期另定。

建立全新還原project／DB／media／publication資料卷，設定**另存的相同APP_KEY**、相容app/web release與新憑證目錄／origin。若 origin 或條款版本不同，先從相容來源重建對應 app/web，不能只改 runtime 環境；manifest 會比對兩者與編譯時值。先起db/app，不先migration或seed；restore只接受完全空庫及空media，拒絕原source project／已有資料，不提供force覆寫選項。

```text
docker compose --env-file .env.restore -f compose.production.yaml up -d db app
python scripts/operations/operations.py --env-file .env.restore --project yinghua-restore restore --backup-directory /private/backups/p0-20261024
python scripts/operations/operations.py --env-file .env.restore --project yinghua-restore open
python scripts/operations/operations.py --env-file .env.restore --project yinghua-restore status
```

restore先驗清單／dump／tar／圖檔hash、tar路徑及普通檔限制、migration相容與APP_KEY指紋；使用無CREATE DATABASE／USE的單庫dump還原到明確新庫。重新驗schema／DB圖片引用，撤銷所有帶回的session、reset token、challenge，再由DB重新發布。不還原舊current symlink，不刪帳號／內容／收藏／行程／正常audit。使用者須重新登入。restore成功**仍不開流量**；open再次檢manifest、核心、圖片及同版公開就緒，才起worker/web。

若SQL／媒體／發布途中失敗，入口保持關閉。勿對已部分還原的非空庫再執行restore；保留現場，建立另一個全空目標重演，或在確認原因後修好已還原資料的發布。因volume權限失敗可修正權限再執行content:publish／open，不能為過關忽略缺圖、跳過health或直接切舊HTML。

## 程式回退

先保存目前整組備份與manifest；將另一份部署設定指向經驗證的舊app/web image與對應APP_VERSION，DB／media沿用目前資料。執行rollback會關入口、用選定映像啟app、確認manifest與目前schema及圖片、從**目前DB**重產頁面，再經open檢查開流量。migration不相容會拒絕，不自動down migration；需要資料回退時只能走已確認目標／時間點的完整還原流程。

```text
python scripts/operations/operations.py --env-file .env.previous-release --project yinghua-production rollback
```

這不是任意版本都可相容的承諾。舊release若沒有本階段readiness／recovery CLI，須先驗獨立相容檢查與操作方法，不能把工具不存在當健康。演練量、耗時與實際相容版本見階段八驗收；小型測試資料的秒數不是正式RPO／RTO保證。
