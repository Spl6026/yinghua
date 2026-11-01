# 正式server交接與上線待辦

2026-11-01。依U-18可部署到既有server，不要求業主使用Docker；Docker是本機開發與可重跑演練環境。依U-21目前正式網址未定，本文件不表示已上線。

## 既有server需要提供的能力

PHP 8.5相容版本與PDO MySQL、mbstring、gd；套件版本以backend/composer.lock為準。MariaDB 12.3相容SQL／trigger／約束；Node 24供前端靜態建置與內容發布。PHP只提供API，Nginx／等價webserver提供HTML、靜態資產與受控媒體；不能用PHP模板替換現有前端。部署前依當時官方安全修補版本重新核對，不能把本機版本號當永久安全保證。

資料庫、媒體、publication與固定frontend輸出分開；PHP使用者需寫media與publication，不能讓公開靜態root曝露backend、.env、vendor、SQL、tests或備份。frontend/scripts、共用字型head helper及assets/js/font-boot.js要同版完整部署。環境變數依`.env.example`及`compose.production.yaml`逐一對照；秘密由server保護設定供應，APP_KEY在既有庫升級不得重設。

## 首次部署順序

1. 取得已核准的HTTPS origin、DNS／憑證、正式SMTP寄件資料、Google Places API設定、素材授權及政策文字。備份秘密於受保護位置。
2. 從同一Git commit安裝鎖定Composer依賴、建置前端、建立release清單；先不開公開流量。一般web請求不能自動migration或初始化admin。
3. 空庫執行migration／角色seed，再安全初始化固定admin。既有庫只跑未套用migration，禁止重跑示範內容匯入或重設帳密。受控素材匯入需業主核准。
4. 執行content:publish，核對核心與公開readiness、圖片引用、DB投影／generation一致後開流量。publisher每10秒tick的等價排程須獨立部署，不能假設有人開後台才會更新排程內容。
5. 依下表取得實際外部證據，再決定正式上線；備份還原先用新的空目標演練，不能在正式庫試還原。

Docker對應完整命令見[P0維運手冊](P0-OPERATIONS.md)，既有server須以其服務帳號、路徑及排程轉寫同等命令並實測。API契約見[OpenAPI](../backend/resources/openapi.json)，檔案用途與分層見[全站導讀](PROJECT-GUIDE.md)。

| 待驗項 | 負責者／需要輸入 | 可接受證據 |
| --- | --- | --- |
| 正式HTTPS／Cookie | 主機管理者；origin與憑證 | 信任鏈、HTTPS轉向、Secure／HttpOnly／SameSite、代理入口限制 |
| SMTP | 郵件管理者；寄件驗證及配額 | 外部收件成功、垃圾信檢查、重設信連結與失敗回應；Mailpit不能代替 |
| Google Maps | API專案管理者；正式key及限制 | Places新版啟用、計費配額、server來源限制及真網址解析；空介紹即時備援亦消耗配額，應用100次／日不是帳務保證；補齊Google條款／隱私說明，不公開金鑰 |
| 搜尋／分享平台 | 業主；正式網域及平台權限 | Search Console／Bing可抓sitemap，正式分享預覽與canonical正確 |
| 業主內容 | 業主 | 正式條款、政策版本、聯絡方式、影像與圖片授權 |
| 維運 | 主機管理者 | 異地加密備份、保留期、還原演練、日誌／health監控與通知；實測RPO/RTO |
| 正式瀏覽器效能 | 驗收人員 | 首幀字型鎖定已以真5秒延遲證明標題字形不再晚換；正式裝置／整頁CLS仍量實際值，不承諾通用零CLS |

資料庫logical dump含個資與密碼雜湊；備份需ACL、加密與異地保存。checksum只檢毀損，不能驗來源真實性。回退會從目前DB重新發布；schema不相容時應拒絕，不自動down migration。復原後撤銷session／重設token／驗證碼，要求重新登入。
