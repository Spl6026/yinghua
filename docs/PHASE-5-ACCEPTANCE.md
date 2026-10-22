# 階段五驗收：公開消息、優惠與首頁

2026-10-23 後續複驗改用固定 V01–V18 清單，31 組整合、PHP 64 項／263 斷言、前端 104 項與建置完整性通過；修正景點待上傳檔案的重載狀態。詳見 [本輪結果與未驗證範圍](RECHECK-PHASES-1-5-20261023.md)，不覆寫下列較早一輪的數字。

2026-10-23 已完成階段一至五的新一輪功能／原型介面複驗，涵蓋真實 MariaDB／HTTP、64 項 PHP、102 項前端測試、桌面與手機操作。新證據、修正及界線見 [複驗紀錄](REVIEW-PHASES-1-5-20261023.md)；下方保留 10 月 22 日原交付結果，未將後續階段列為完成。

日期：2026-10-22。分支：`phase/05-public-content-home`，由當時最新 main 的規劃提交開始。**本機階段五完成；P0 尚須階段六至八，不代表正式上線或完整 CMS 已交付。** 原始 Excel 與 website 未修改。

## 交付與來源

| 基準 | 已交付 | 界線／工程補充 |
| --- | --- | --- |
| 功能規格 E5／G5／H5／L5 | 首頁博物館、精選景點、本週電影及三則消息讀成功發布快照 | HomeSelection／HomeContent／聚合 GET 為 E-26；原型區塊及靜態文案保留，沒有新增優惠卡片區 |
| 功能規格 E9／G9／H9／J9／L9 | events 推薦＋全篇、活動／城市分類、完整分頁、空狀態與基本閱讀 | 依發布時間倒序，同時間 UUID 穩定排序；基本閱讀是 E-17 的 P0 必要前置 |
| 功能規格 E11／G11／H11／L11 | 優惠獨立列表、店家／期限／條款、有效期及基本詳情 | 截止日全天有效，空日期不限制該端，歷史詳情顯示狀態；店家停用隱藏優惠，均明列 E-26 |
| 功能規格 G22／G23 | 共用寫入的分類、排程、安全正文、優惠條款及有效店家檢查 | 受控 CLI 供已能存取 Docker 的維運者；完整後台 CRUD／前後篇仍在 P1 階段九 |
| 資料表建議 A14–D16 | news_posts、offers、獨立 merchants profile 與必要 FK／日期檢查 | 草案保持 D；alias、版本／用途欄、具體索引及 publish_at 命名為 E-26，見資料庫對照 |
| 功能規格 E30、串接總覽 B12／B15、SEO C17／F17 | 新公開頁預先生成唯一 head／canonical、圖片用途與替代、鍵盤與可讀狀態 | 全站 SEO 設定／覆寫仍留階段七；圖片沿 U-14 做內容檢查與重編碼，沒有病毒掃描服務 |

細節與逐欄限制見 [階段五計畫](PHASE-5-PLAN.md)、[資料庫對照](DATABASE-SPECIFICATION-MAP.md)、[閱讀指南](modules/public-content.md)。欄位列出不等於必填；標題／店名及優惠內容依原型，條款依 G23，有圖時有效 alt 依來源規則。摘要、正文、圖片及日期沒有誤加為必填。

## 實際架構與資料

- 新增七個公開 GET：`/api/v1/home`、`/news`、`/news/{slug}`、`/news/resolve-url`、`/offers`、`/offers/{slug}`、`/offers/resolve-url`，皆有 /api/v1 前綴。全案目前 61 個操作，契約為 backend/resources/openapi.json。
- 主／測試庫均為 MariaDB 12.3.3，28 表／276 欄、十五份 migration。012 擴充 merchants，013／014 建消息、優惠及各自 aliases；原已套用 migration checksum 不改。
- PHP 保持 API-only；Controller／DTO／Service／Repository、建構子注入及共用 Policy。CLI 重用 Service、整批交易與稽核；HTML 留前端 Node 工具，原生 JS 共用 view／列表控制器。沒有增加框架、對外 port、環境變數或第二套 publisher。
- ContentPublication 一次取時刻，所有公開來源、aliases 與首頁使用同一 generation。首頁與電影 API 共用週選取，不依次呼叫不同 API 拼湊版本。未生成新版時 gate 回 503，不回填展示快照。
- 正文使用鎖定的 HTML Purifier 4.19.1，保留安全標記後檢查圖片 alt／模組引用。CLI 本地圖片與 HTTP 真實上傳明確區分，兩者共用格式、尺寸、解碼／重編碼及用途驗證。
- 主庫升級前備份在忽略的 `artifacts/backups/phase5-before-migrations-20261022.sql`。已明確匯入四篇原型消息／三項優惠；不覆寫已有店家、內容或停用狀態，不猜活動時間或電影年份。

## 已執行檢查

| 檢查 | 實際結果 |
| --- | --- |
| PHPUnit／PHPStan／PHPat／格式 | Docker PHP 8.5.12：64 項、263 斷言通過；level 10 及分層規則通過，197 檔格式 dry-run 零修改 |
| Node | Docker Node 24：101 項通過，含消息／優惠／首頁真實生成、編碼、日期、下架及既有互動回歸 |
| 既有階段二至四完整回歸 | 21 組完成；獨立 JSON Schema 驗證 788 個 HTTP 回應與 260 個成功寫入請求，觀察到原有 54 個操作，契約零差異 |
| 消息 CLI→DB→HTTP／HTML | 40 項，12 個獨立契約回應；分類／深頁／正文／未知欄位／版本／整批失敗／排程／下架／renderer 故障 exit 4 及重試 |
| 優惠 CLI→DB→HTTP／HTML | 40 項，8 個獨立契約回應；店家獨立存在、條款、日期、active=false 不洩漏草稿、停用／恢復、aliases、圖片引用／模組隔離 |
| 首頁整合 | 21 項；各公開來源與 HTML 同版、前三篇／三部不同電影、詳情連結、無新增優惠卡區、發布故障與保存後重試 |
| 跨日／跨週 | 真實 MariaDB＋Node 20 項；23:59:59 有效、午夜到期／排程發布、台北週一更換電影場次、舊版本 gate、無變更不重產；交易回滾及獨立 /tmp 產物清除 |
| demo／全新初始化 | 既有 demo 10 項及空庫初始化 10 項；原景點店家 profile 重用、原網址、重跑、已編寫文章及停用店家不覆寫 |
| 空庫／保留資料升級 | 兩個隨機 schema 均 28 表／15 migration，重跑、FK／唯一／最少欄位及原會員／景點／店家保留；自有 schema／暫時授權最後移除 |
| 既有階段四 schema 工具 | 鎖定階段四 migration 集，空庫／升級仍為 24 表／12 份；最新結構由階段五工具驗證，避免新 migration 使舊工具誤報 |
| 新模組安全失敗／越權 | 27 項；HTTP 媒體分權、撤權、唯讀模擬、匿名、偽裝 SVG、alt、站外轉址／路徑及 CLI 參數限制；無 Google 呼叫 |
| 隔離容器重建 | 9 項；只重建 app-test／web-test，消息正文／媒體、優惠店家／條款、HTML／圖片位元組及 generation 保留；未重啟共用 DB 或刪除 volumes |
| Composer audit | 應用與獨立工具 lock 均無 advisories／abandoned；沒有以「未安裝」當作通過 |
| Rector | 僅 dry-run，29 檔風格簡化建議，實際 exit 2；檢視括號、nullsafe、closure／array_all 建議後未批次套用，沒有宣稱零建議 |

原始碼／結果在本機忽略的 artifacts 留有相應 JSON／log。獨立契約工具為 python-jsonschema 4.26.0。新增七個公開操作另由消息／優惠／首頁及安全測試覆蓋，不把舊工具的 54 個操作誤寫成包含新功能。

資料庫查詢以本案 12.3.3 實測 EXPLAIN／ANALYZE：news_public／offer_public 採 range，媒體與店家 JOIN 用主鍵 eq_ref；兩者排序仍有 filesort。測試庫當時只有四篇消息／三項優惠，不能據此宣稱大資料量效能。Performance Schema 為 OFF，未為本輪唯讀查詢重啟資料庫；沒有照草案增加未使用日期索引。公開分頁從成功快照切片，不冒充 SQL 深分頁效能。

## 瀏覽器與原型對照

使用 Brave 實際操作原始 website 的唯讀暫時伺服器與 localhost 主／隔離測試站。原型 CSS 與 frontend/assets/css/style.css 逐位元內容相同，新增 CSS 只補本輪狀態、正文與長文字處理。量到桌面 1440×900、手機 390×844；localhost 原有 110% 縮放由暫時 viewport 補償，最後清除覆寫，沒有改使用者縮放設定。

- events 的上方推薦、下方全篇、分類與日期 `YYYY / MM.DD`；activities、city-news、offers、消息／優惠詳情、首頁均檢查桌面／手機標題、卡片與橫向溢位。12 筆逐頁尺寸觀察另加 events 的兩種尺寸；未見水平溢位。
- 消息 city 選取後 1 篇、返回 4 篇；手機選單開／關與 Escape 實測。42 筆隔離內容另驗第二頁、重整及另一分頁直接網址：消息 20＋5，優惠 20＋4；換頁聚焦狀態且 URL 保留 page。
- 210 字連續英文標題不撐破手機；本機不存在的圖片 URL 實際顯示替代圖。缺圖、長標題與空清單均未使用原型資料補滿。
- 暫停 app-test，分類讀取失敗時保留原五張卡片，聚焦錯誤並提供重試；恢復服務後重試成功、URL 更新為 city、11 張測試卡片可讀。只影響隔離站，主站正常。
- 僅將隔離庫本階段 demo 暫時下架，實際觀察空推薦／消息、空優惠及首頁空消息；最後恢復原狀態。所有 UUID 畫面 fixture 及本輪上傳圖片已清理，測試帳號經正常 API 軟刪／停用及撤銷 session；append-only 稽核保留。

截圖與尺寸資料保存在 `artifacts/phase5-ui/`，不把大型驗收產物提交 Git。對照是版型、狀態與操作驗證，沒有宣稱像素完全相同。明確保留的資料／階段差異：

1. 真實消息依發布時間排序，不維持原型陣列順序；沒有活動日期時顯示發布日期，推薦標籤不虛稱本週。主庫沒有本週電影場次，所以首頁顯示空片單，不猜年份。
2. 優惠期限採原型 defaultOffers 的實際資料；舊 coffee-walk 301 至優惠。原型固定優惠網址會被 JS 的預設索引覆寫成第一項，本案依 canonical 顯示正確內容，不複製該錯誤。
3. 原型消息／優惠詳情的延伸閱讀／前後篇留階段九；本輪提供基本閱讀頁及真實日期／狀態，消息返回新竹現場。共用導覽／頁尾維持既有 frontend 的實作。

本輪依 php-modernization、mariadb-query-optimization、security-audit 及 web-design-guidelines 審查。表單／導覽、live status、焦點、URL、編碼及圖片引用已檢查；不是全站 WCAG 或人工螢幕閱讀器認證。Windows 防毒隔離了 security-audit 的 file-upload-security.md 範例參考檔，該工作樹刪除未納入功能提交，未停用防毒或還原該檔。Semgrep／Trivy／Gitleaks 未執行，不能當作掃描通過。

## 重現命令

PHP／Composer 在 Docker 執行；Python 只協調隔離 DB／HTTP。先啟動 README 中的 test／mail 服務並安裝 tests/requirements.txt 至忽略的依賴目錄。

```powershell
docker compose exec -T app php /var/www/tools/vendor/bin/phpunit
docker compose exec -T app php /var/www/tools/vendor/bin/phpstan analyse --no-progress
docker compose exec -T app php /var/www/tools/vendor/bin/php-cs-fixer fix --dry-run --diff --using-cache=no
docker compose run --rm --no-deps frontend node --test
python backend/tests/openapi_contracts.py --dependency-path artifacts/audit-jsonschema --include-films
python backend/tests/news_public_integration.py --dependency-path artifacts/audit-jsonschema
python backend/tests/offers_public_integration.py --dependency-path artifacts/audit-jsonschema
python backend/tests/home_public_integration.py --dependency-path artifacts/audit-jsonschema
python backend/tests/public_content_security.py --dependency-path artifacts/audit-jsonschema
python backend/tests/public_demo_integration.py
python backend/tests/public_content_schema_upgrade.py
python backend/tests/public_content_seed_fresh.py
python backend/tests/public_content_restart.py --dependency-path artifacts/audit-jsonschema
docker compose exec -T app-test php tests/public_content_clock.php
docker compose exec -T app-test php tests/public_content_persistence.php
docker compose exec -T app-test php tests/public_content_queries.php
```

這些測試會維護隔離帳號／fixture，不能改為主庫執行。瀏覽器深頁 fixture 另用 public_content_browser_fixture.py 建立，完成後加 --cleanup；不要與會改同一測試資料／故障注入的 suites 平行執行。

## 交付與正式環境差項

已更新 PROJECT-GUIDE、架構、來源、API／CLI schema、資料庫對照及實作計畫。Git 依 5A 前置、5B 消息、5C 優惠／店家、5D 首頁、5E 整合驗收分提交；自身修正併回所屬功能，備份與 tree 核對後用 merge commit 回 main，保留階段分支。原始第一提交及 website／Excel 的 69 檔均須在合併核對中保留。

正式消息／優惠內容、圖片授權、正式網域、HTTPS／郵件部署及全站無障礙仍待相應上線驗收。完整後台管理、前後篇、收藏／行程與 SEO 設定依總計畫分階段，不以本輪示範或共用 Service 冒充後續交付。
