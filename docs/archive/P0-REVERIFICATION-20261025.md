# P0 全範圍複驗：2026-10-24–25

後續狀態：使用者另行授權後已修正 R01–R03、可重現的發布 alias 順序誤判並補診斷，見 [修正與實際驗收](P0-IMPROVEMENTS-20261025.md)。本文件以下保留修正前這一輪結果；歷史 503 不追認根因，完整 P0 尚未通過。

**結論：P0 尚未通過。** 本輪重新核對 Excel 的 16 列 P0、真實 MariaDB／HTTP、前端操作、隔離 TLS 部署及復原，確認多數已實作流程可用，但找到三項可重現的介面／資料一致性缺陷，另有一次尚未定位的優惠 API 503。完整人工 AA、正式環境與映像安全差項仍未完成。本文件不把後續成功重測改寫成首次全部通過。

分支為 `phase/08-p0-validation`；受測應用來源 `1fd4f14fc938a82c122a4809e8b5e5e6e1fdb3bd`。本輪未修改應用 PHP、前端 JS／CSS、API 或 schema；僅改善兩個驗證工具及更新工程文件。未合併 main、未推送遠端、未進入 P1。

## 基準與範圍

- 原始 [Excel](../../映畫竹塹_後台工程串接功能規格.xlsx) 只讀核對：「功能規格」J 欄的 P0 共 16 列：5、6、7、9、11、13、16、17、19、20、21、26、27、30、32、34。流程看 E、規則看 G、驗收看 L；F 欄列出欄位不等於必填。
- 「串接總覽」B17 是建議順序；本案優先級依功能列及使用者指定，不能把電影、收藏或 SEO 因總覽建議而排除本輪。
- `website/` 作為原型介面與互動依據，原始交付 69 檔未改。資料來源由 localStorage 換成 API／資料庫；空態、錯誤、登入與伺服端授權依 Excel／使用者指定。未追求不同內容下的逐像素相同，也沒有以保留原型為由豁免 AA。
- U-11 摘要／介紹選填、自動補位延後；U-14 圖片內容檢查而非病毒掃描；U-15 確認密碼必填一致；U-16 只比放映日期＋開始時間，結束時間／場地延後。來源說明見 [追溯表](../DESIGN-TRACEABILITY.md)。
- 維持 72 API、67 固定 HTML、33 表／316 欄、18 份 migration，沒有新增 DDL。表／欄對照沿用 [資料表規格映射](../DATABASE-SPECIFICATION-MAP.md)，本輪另核對實際 schema 及 migration 完整性。

## 未通過項與重現方式

| ID | 結果與影響 | 位置／規格來源 | 後續驗收標準 |
| --- | --- | --- | --- |
| R01 | 部分小字對比不足。實際不透明背景下 `.section-kicker` 13px 為 3.35:1、`.film-meta` 13px 為 3.03:1、`.demo-note` 10px 為 2.93:1，低於一般文字 4.5:1。 | [style.css](../../frontend/assets/css/style.css) 第 6 行內相應 selector；功能規格 G34／串接總覽 B15。 | 調整新前端的相關色彩並核對真實背景、互動狀態、桌機／手機；完整 AA 另驗。原型不改。 |
| R02 | 電影頁底部的館舍時間仍寫死「週二至週日 09:30–17:00」，與同站 museum 實際資料（週二至四 09–17、週五至日 09–21、週一休館）不一致，後台更新不能同步到此處。 | [cinema.html](../../frontend/pages/cinema.html) 第 53 行；串接總覽 B7／功能規格 E6、L6 的資料來源與刷新原則。 | 電影頁顯示同一館舍資料，修改後跨頁一致；維持既有排版。這是資料同步差異，並非電影詳情連結失效。 |
| R03 | 後台將 events.html 的標題覆寫為「P0 消息覆寫驗收25」後，首次載入 title／OG 正確；切「活動」分類後 URL 為 `?type=event`，title／OG 變成硬編碼「活動｜映畫竹塹」，同 URL 重新整理又恢復後台覆寫。description 仍保留自訂值，造成首次／頁內導覽不一致。 | [public-list-controller.js](../../frontend/assets/js/core/public-list-controller.js) 第 25–27 行、[news.js](../../frontend/assets/js/pages/news.js) 第 9 行；功能規格 E30、E32、L32。 | 分類及分頁更新沿用已發布 SEO 設定與覆寫優先序，驗初次載入／切換／上一頁／重新整理，不能只驗無 JS head。 |
| R04 | 第一輪優惠整合在合法區域篩選時收到 503，工具隨後讀 `total` 觸發 KeyError。保留日誌：2026-10-24T11:22:00Z，requestId `e2a20b241df85f051b1447a9e176dd2c`；同窗口 web-test 的 `/index.html` 探測也 503，清理重新發布後恢復。 | `offers_public_integration.py`、公開發布就緒；功能規格 E11／L11、串接總覽 B7／B13。 | 首次沒有保存錯誤 body，無法確定 code 或根因。補安全回應觀測後有限重測 40 項／8 契約通過，仍不能宣告已修復；需定位並補對應回歸。 |

R01 對比判定依 [W3C WCAG 2.1 對比最低標準](https://www.w3.org/WAI/WCAG21/Understanding/contrast-minimum.html)。本輪只證明上述具體失敗，沒有把抽樣換算成全站對比報告。R02 的資料讀值與 R03 的前後 head 均有本輪觀測，並非只靠程式推測。

R04 診斷在同一 repeatable-read snapshot 比較四種 session optimizer 設定、固定歷史分鐘切點及發布 generation；未重現不一致，沒有變更全域 DB 設定／索引，也沒有把查詢排序假設當成已確認原因。首次失敗、診斷與有限重測各自保存。

## 16 列本輪驗證

「通過列明範圍」只指下表實際測到的流程；每列仍受共用 AA／正式環境差項影響。完整頁面／API／資料表映射見 [P0 矩陣](P0-ACCEPTANCE-MATRIX.md)。

| 功能規格來源 | 本輪重新執行 | 判定／限制 |
| --- | --- | --- |
| E5／G5／L5 首頁 | home_public_integration、公開安全與原型內容匯入；首頁原型／新前端桌機觀察。 | 已驗公開狀態、各區空態與同步；R01 及正式精選素材另列。 |
| E6／G6／L6 館舍 | owner_requirements、map_fields、公開資料與 museum 桌機／手機。 | 館舍頁本身讀資料；其他頁共用館舍時間有 R02。 |
| E7／G7／L7 片單 | films_public、screenings、pagination；片單「場次介紹」到獨立詳情，兩場次清單。 | 連結、照片／日期與兩場次可用；沒有替原型缺年份資料猜年份。 |
| E9／G9／L9 消息 | news_public_integration；320px 實際切活動分類與 URL／卡片。 | 內容分類／空態通過；分類後 SEO 有 R03。前後篇仍 P1。 |
| E11／G11／L11 優惠 | 首次失敗保留；加觀測的有限重測及工具改善後受影響套件重測。 | 有 R04，不能判整列穩定性已通過；40 項／8 契約成功是後續結果。管理 CRUD 仍 P1。 |
| E13／G13／L13 景點與本人保存 | attractions／member 三組 DB 回歸；篩選、無結果、收藏、加入、重新載回、移除、登出。 | 本機已驗；完整旅程管理仍 P1，沒有以 disabled 控制項冒充可用。 |
| E16／G16／L16 註冊 | 認證及規格校正、100 輪 CSRF；手機表單 required／autocomplete。 | 確認密碼必填一致、驗證碼失敗／限速由 API 重驗；本輪未在 UI 解 CAPTCHA 或送出同意條款建立帳號，不稱 GUI 完整註冊通過。 |
| E17／G17／L17 登入／登出 | 真 DB 認證、輪替、401／403、reset、SMTP 中斷；合成會員與管理員 UI 登入，隔離 TLS Cookie。 | 本機列明流程通過；正式寄信／可信憑證另驗。SMTP 工具耦合問題已修正，見下節。 |
| E19／G19／L19 景點清單 | 搜尋／分頁／版本／軟刪／關聯／audit API；新建的具名景點出現列表。 | 本機列明範圍通過；本輪未逐一重做所有刪除確認 UI 狀態。 |
| E20／G20／L20 景點表單／Google | 真選檔 PNG 320×240／alt；空摘要介紹仍可發布並重開；週一休息；安全 URL／SSRF API；實際 Google Text Search 一次。 | 公開影像博物館網址回傳一個候選，現有表單值未自動覆寫或儲存。本輪未選候選後再次匯入整套欄位；該映射由 API 回歸覆蓋。服務 Key 未送前端。 |
| E21／G21／L21 電影管理 | 管理、持久化、多場次 API；UI 相同日 12:00／12:00 拒絕，改 18:00 儲存，列表兩場。 | 已驗 U-16；場地／結束時間未加入。 |
| E26／G26／L26 管理員 | 管理／規格／越權 API；固定 admin 列無編輯刪除，新增表單必填讀取。 | 本機權限與保護通過；未在 UI 改密碼或新建帳號／升權。 |
| E27／G27／L27 權限 | admin_preview／review_regressions：每請求驗權、範本、防越級與唯讀拒寫。 | 本輪 API 覆蓋；root 40 格 disabled 的畫面沿用前輪證據，未冒稱本輪重新操作。 |
| E30／G30／L30 公開 SEO | 無 JS head 74 HTTP、seo_publication；實際逐頁標題覆寫後分類與重新整理。 | 靜態 head 測試通過，頁內互動有 R03。正式分享預覽仍待驗。 |
| E32／G32／L32 SEO 後台 | 設定 74 項／32 契約，發布 60 項／12 契約；UI 有效覆寫保存，測完復原。 | 保存／發布可用，但公開分類互動 R03；本輪 UI 未重新製造每個 422／409，API 有重測。 |
| E34:H34／G34／L34 共用 | health、錯誤／越權、安全編碼、發布故障、品質工具、scanner、RWD、表單／焦點／選單。 | R01、R04 與映像風險保留；人工讀屏、200% 文字、400% 縮放未完成，不能宣告 AA。 |

## 自動檢查：成功及中止都記錄

| 組別 | 本輪實際結果 |
| --- | --- |
| PHP／JS 品質 | Docker PHP 8.5.12：PHPUnit 87 tests／302 assertions；PHPStan level 10／PHPat 通過；格式 dry-run 257 檔零修改。Node 145、運維 host unittest 4 通過。 |
| Rector | dry-run 提出 40 檔語法建議（nullsafe／arrow、array_first／last／all、new 後省括號等），exit 1 表示提案；已檢視，未套用、不當測試失敗或零建議。 |
| 舊模組契約 | 21 套全跑完，788 HTTP 回應、260 成功輸入 schema、54 個直接觀測 operation，零 schema 差異；不聲稱 72 操作所有狀態全覆蓋。其後消息套件通過，在優惠 R04 中止，沒有把整批標綠。 |
| 後續模組 | 首頁／公開內容安全／原型匯入通過；會員三組 467 檢查／219 契約、5 輪發布；SEO 設定 74／32 契約、發布 60／12 契約、政策 13、無 JS head 74 回應零差異。 |
| 健康／穩定性 | health 18 檢查、6 種 CLI 故障、6 真 HTTP jsonschema 回應；100 輪預期 422 的 CSRF 觀察無非預期拒絕、無重試、無期限倒退。這不會抵消另一路徑的 R04。 |
| Schema／持久化 | member、SEO、film、content 四組新庫／含資料升級與 rollback；member 重建 17／7 契約、SEO 重建 4、content 重建 9、fresh seed 通過。 |
| SMTP | 原腳本挑到前一套件已停用的「最後一位會員」，所以未產生 reset token；屬工具 fixture 耦合，非證明應用 SMTP 異常。改為自己建立的 active 隔離會員後，503／通用 200／失敗 token 撤銷／恢復後單次請求四項通過，最後停用自身 fixture。 |
| 前端完整性 | 67 固定頁、14 資產、81 HTTP、零缺檔／結構問題；這只證明建置與 HTTP 完整性，不代表全部頁面互動／視覺皆正確。 |

執行器第一次 member-recreate 少了主機 jsonschema 搜尋路徑，未進入應用測試；保留原始錯誤後校正 PYTHONPATH，僅接續未完成項目。不是應用修復，也不算第一次通過。Docker／瀏覽器中途重啟後繼續原輪證據，不覆蓋先前結果。

兩個測試工具修改與用途：

- [mail_failure.py](../../backend/tests/mail_failure.py)：自有 UUID 會員，不依賴其他套件最後一筆資料；finally 恢復 Mailpit 並停用自身 fixture，固定上限等待服務健康後只送一次恢復業務請求。
- [offers_public_integration.py](../../backend/tests/offers_public_integration.py)：先確認預期 HTTP 狀態，再讀業務欄位；非預期回應只存 route／expected／status／code／requestId，不保存 raw body／秘密，不自動重試。故意非法參數的 422 另明確指定。

## 隔離發布、備份及還原

使用同一已提交來源建立新 a／b release label，在獨立 Compose project `spl-p0-recheck-191148`、回環 TLS `p0.invalid:18443` 演練；測試自簽憑證由 Python 明確信任且檢查 hostname，沒有停用驗證或寫入 OS 信任。

- 冷啟動 17 個不同檢查，一次完成；空庫 503、migration／首發布、TLS／Cookie、DB pause／FPM／publisher 故障與恢復。DB pause 到安全 503 約 2.000 秒。
- 還原 25 項通過；含一張真 GD 圖、業務資料逐列 hash、media hash、trigger、帳密保留；舊 session／reset／challenge 撤銷，毀損 dump／缺圖／不相容 schema／非空目標／發布失敗均拒絕開流量。
- 回退 9 項通過。a／b 是相同 source、不同 release label，不推論任意歷史程式都相容。
- 本輪備份 3.688 秒、還原 4.172 秒，只是小型隔離資料量，不是正式 SLA／RPO／RTO。
- 主 `yinghua` DB 未還原或重建。本輪三個具名演練 project／卷與 UI 合成內容已清理；主 DB 容器 ID／卷與前輪相同，未動其他 project。私有備份、原始失敗與掃描證據保留，不提交秘密。

## 安全掃描與尚未關閉的風險

這次確實執行先前未做的 Semgrep／Gitleaks；工具有命中時保留 exit 1，沒有新增 suppress／ignore 把結果變綠。完整映像風險判讀與官方來源見 [安全審查更新](PHASE-8-SECURITY-REVIEW.md)。

| 工具／範圍 | 結果與判讀 |
| --- | --- |
| Composer `audit --no-dev` | 應用鎖定依賴無已知公告／abandoned。不是全系統無漏洞證明。 |
| Trivy 0.76.1、新 a 映像 | app 78 HIGH／3 CRITICAL；web 0／0；DB 25 HIGH／1 CRITICAL，三者秘密命中 0。app 當前 vendor fix 0；DB 26 個 scanner 所列修補涉及 gosu 內 Go metadata，尚未換成修補後官方二進位。保留既有可達性限制與正式安全門檻。 |
| Gitleaks 8.30.1 | 全可達 Git refs、完整遮蔽、離線掃描；18 命中，均位於第三方 security-audit 的教學參考文件（8 個唯一檔案／行位置在歷史 refs 重複），人工核對為範例字串；應用路徑命中 0。不對外驗證金鑰，也不宣稱掃描能證明無秘密。 |
| Semgrep 1.182.0 | 官方規則 commit `16b9e6d45f10649f6a531ec4838ed9924372c3bc`；256 rules、298 source files、115 命中（9 ERROR／94 WARNING／12 INFO）、0 parse errors。所有命中有逐筆分類；沒有直接把 ERROR 全當已利用漏洞。 |

Semgrep 逐筆判讀：72 項核對信任邊界（escape／HTMLPurifier／內部 UUID／argv＋stdin／同源 returnTo 等）、22 項規則與實際寫法不符、17 項 replaceAll 為目前 Node／Brave 相容性選擇、1 項 OS 更新是安全維護選擇。剩餘 3 項工程強化待辦：兩個 container master 使用 root（worker 已降權）及一個建置 pipeline 未設 pipefail。這些待辦不靠標成誤報消除，也沒有在純驗證工作中自行更改容器權限或建置流程。

Gitleaks／Semgrep 掃描時關閉網路，僅掛載所需 Git 或選取的 source／本地規則，不掛 `.env`、Docker socket 或備份。工具固定映像與 digest 記於私有 summary，來源為官方 [Gitleaks 8.30.1](https://github.com/gitleaks/gitleaks/releases/tag/v8.30.1)、[Semgrep 1.182.0](https://github.com/semgrep/semgrep/releases/tag/v1.182.0)。

## 真實瀏覽器與原型對照的邊界

本輪有實際桌機 1440×900、手機 390×844／320px 寬觀察與代表互動；公開頁盤點檢查 main、圖片 alt、表單 label、文件橫向溢出。home 原型／新前端同桌機的 header 82px、hero 710px；原型首頁箭頭原本就連 explore，不把原型已有行為誤報為回歸。

手機導覽關閉／Escape、分類篩選、註冊必填提示、景點新增／真選檔、Google 候選、電影編輯、SEO 保存及合成會員收藏／行程都有本輪操作。部分跨頁導向後瀏覽器控制會失去分頁連線，改開同路徑新測試分頁後能確認登入狀態和保存資料，沒有將工具逾時寫成網站登入循環。

本輪沒有實際讀屏、200% 文字或 400% 瀏覽器縮放。320px RWD 不等於 400% 縮放驗收。第一次 viewport 切換後工具曾回傳前一畫面，該張 `film-detail-320.jpg` 不採為手機畫面證據；後續新鮮狀態／截圖另存。沒有重複把前輪權限頁 40 格、所有 422／409 或完整 GUI 註冊當成本輪新結果。

## 證據、收尾與下一步

本輪證據目錄為忽略的 `artifacts/p0-recheck-20261024-191148/`，與其 `previous-evidence/` 舊輪複本分開。主要索引：

- `quality-results.json`、`api-results.json`／`api-continuation-results.json`／`api-restarters-results.json`：每批實際起迄、首次中止與接續；原失敗 log 保留。
- `full-recheck-contract-validation.json`、`phase7-member-regression.json`、`phase8-health-contract.json`、`phase8-stability-observation.json`：契約與固定觀察。
- `original-offers-window.log`、`alias-query-diagnosis.json`、`offers-http-observation*.json`、`offers-observed-final.log`、`smtp-owned-fixture.log`：失敗／診斷／工具改善後結果。
- `seo-after-filter.json`、`contrast-observations.json`、`browser-page-inventory.json`、`browser-final-observations.json`、`news-filter-320.jpg`、`planner-persisted.jpg`：真實介面證據。
- `deployment-results.json` 及三種 rehearsal JSON、`image-scan/summary.json`、`static-scan/summary.json`／`triage.json`：部署與掃描。
- `cleanup.json`、`delivery-check.json`：具名清理、主 DB 保護、原始檔／migration／schema／連結／秘密忽略與 main 不變的最終核對。

下一步先修 R01–R03，對 R04 補足可定位的失敗觀測／原因及受影響回歸，再補完整人工 AA 與正式部署條件；依功能提交與同步導讀。這是缺口處理順序，不代表本輪已修正上述應用問題，也不授權提前進入 P1。

最終交付核對：原始交付 69 檔完整；18 migration 未變；真 DB 33 表／316 欄；1,072 個本地文件連結無新增缺失（既知防毒隔離 skill 參考檔除外）；8080／8081 health 均 200。兩個修改的 Python 工具通過語法編譯及各自受影響的實際測試；應用來源未變，沒有因只改文件而重跑整套測試。main／origin/main 不變，`.env`／私鑰／fixture／備份與掃描證據維持 Git 忽略。收尾腳本首次將短／完整容器 ID 混比而誤判，統一格式後確認清理完成；該工具錯誤未寫成網站缺陷。
