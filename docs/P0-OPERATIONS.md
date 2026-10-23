# P0 部署與運維

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

admin:init互動輸入Email、密碼與確認；不要把密碼當命令參數或貼到聊天。既有庫升級不重設APP_KEY、密碼或重新匯入原型。內容／媒體匯入指令見 [公開內容指南](modules/public-content.md)，確認素材授權後再執行。

```text
docker compose --env-file .env.production -f compose.production.yaml exec -T -u www-data app php bin/console.php content:publish
docker compose --env-file .env.production -f compose.production.yaml exec -T app php bin/readiness.php
docker compose --env-file .env.production -f compose.production.yaml exec -T -u www-data app php bin/readiness.php public
docker compose --env-file .env.production -f compose.production.yaml up -d publisher web
python scripts/operations/operations.py --env-file .env.production --project yinghua-production status
```

空庫health503；HTTP不會偷偷migration／建admin。先成功發布才開正式web，worker每10秒tick；worker不依賴web，沒有啟動環。`bin/readiness.php core`檢核心；`public`另檢DB投影／快照generation一致。容器health只證明FPM或Nginx存活，不能當內容已發布。GET /api/v1/health200亦不代表SMTP、Google或業務內容全正常。

## HTTPS 與日誌

web直接TLS1.2／1.3，80回308到APP_URL、HTTPS有HSTS；Secure／HttpOnly／SameSite=Lax Cookie由既有API發出。憑證取得、更新與正式信任鏈尚待主機確定。若另有反向代理，另設計限制入口與可信代理，不能直接相信任意 X-Forwarded-*。隔離自簽測試只供驗證客戶端明確載入測試憑證，不改使用者信任庫／hosts、不忽略瀏覽器警告，不當正式TLS證據。

Nginx存取日誌只記時間、method、無query的uri、status與requestId；正式Nginx原始error log停用，避免上游錯誤把原始URL／token／IP帶入，改以安全access狀態及PHP例外類別診斷。FPM格式只記時間／method／status／requestId，無IP／URI。Docker輪替10MB×3為初始E值，不自動清除正常audit、auth_events或備份；保存／異地／加密政策仍待營運確認。

原型或特定UI差異、完整安全掃描、正式SMTP／Google／正式內容／條款與正式網域驗證依驗收紀錄，不從本文件推定已通過。P1／P2功能沒有提前開放。
