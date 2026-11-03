# 階段二、三：業主要求逐項對照與校正

日期：2026-10-19。基準是原始 Excel，並保留使用者後續明確決定。這份文件取代「測試通過即代表全部規格符合」的判斷方式：每個要求分別記錄來源、實作及驗證，後續階段與正式環境驗收另列。

這次確認並補齊兩項漏接：功能規格 E20 的既有範例景點試填，以及 G6 的電話格式流程。原首頁的 1933／1937／1998 是館舍歷史年表，核對後保留，沒有將歷史敘述改成資料欄位。前次 [完整檢查](FULL-RECHECK-20261019.md) 的結果仍是當時執行事實，但沒有覆蓋這兩個漏項，不能當成已全面符合 Excel 的證明。

## 來源與界線

- S：Excel 明確功能、條件與驗收。每項附工作表／儲存格。
- D：Excel「建議／草案」。API 路徑與表的具體設計不自動升格成硬性要求；例如 API草案 F7 的 neighbors，不據此提前開發後續詳情導覽。
- U：使用者明確指定；最新確認密碼必填且一致（U-15）覆蓋先前確認選填。八字密碼／取消首登／具權限的一般管理員自我操作依 U-13；固定 root 及最後管理權不受影響。U-09 共用營業週表、U-10 Google 原文只供參考、U-11 內容選填及延後補位、U-14 圖片內容檢查亦維持。
- E：必要工程決定。資源上限、實際表名、格式演算法、錯誤結構及具體 API 路徑各自說明，不宣稱由 Excel 指定數值。

F 欄是資料欄位清單，不等於全部必填。未明定者核對原型與 U 指示；本次未增加電話、範例、地址、圖片、摘要、介紹或營業時間的業務必填門檻。

## 階段二逐項核對

表中路徑從 backend/ 或 frontend/ 開始；測試都固定使用 yinghua_test，不把主庫當 fixture。

| Excel 來源 | 業主要求 | 對應實作 | 驗證依據 |
| --- | --- | --- | --- |
| 功能規格 E16／F16 | Email、名稱、密碼與驗證碼註冊 | DTO/RegisterInput、Services/Authentication、register.html | api_integration：真實建立會員及欄位錯誤 |
| 功能規格 G16／L16 | Email 格式、唯一、密碼政策、同意條款 | DTO/Input、RegisterInput、AccountRepository、ConsentRepository、UNIQUE | api_integration／specification_alignment；八字與確認依 U-13／U-15 |
| 串接總覽 B10；功能規格 G16／L16 | 伺服器隨機驗證碼、短效、一次性、限次、流程綁定與 IP／帳號限速 | Services/Captcha、Authentication、RateLimiter、ChallengeRepository | api_integration：錯誤、過期、重用、綁定、並行及限速；答案不以文字回傳 |
| 串接總覽 B9；資料表建議 A2 | 密碼安全雜湊，不能回傳或明碼保存 | PasswordHasher 的 Argon2id、輸出 DTO、摘要欄位白名單 | api_integration／response_contract／account_persistence |
| 功能規格 E17／G17／L17 | 真實 session、登入／登出、輪替，錯誤不洩漏帳號存在 | Services/Authentication、SessionRepository、Http/Csrf | api_integration：401、登出失效、舊 token 拒絕及通用登入錯誤 |
| 功能規格 G17；串接總覽 B9 | CSRF、限速、鎖定 | Http/Kernel、Csrf、Services/RateLimiter | api_integration：缺 token、跨來源、舊 token、429／Retry-After |
| 串接總覽 B9 | 忘記／重設密碼 | PasswordReset、ResetRepository、PHPMailer Adapter、獨立前端頁 | api_integration：兩種身分、過期／重用／撤銷；正式送達另列 |
| 功能規格 E26／L26 | 一般管理員 CRUD，刪除用確認，不建刪除專頁 | AdminController → AdminManagement → AccountRepository、admin-list／admin-form | api_integration／specification_alignment；取消、409、401 與載入失敗由 Node 測試覆蓋 |
| 功能規格 G26／L26；串接總覽 B14 | admin 保留、root 不可編輯／刪除／降權，保留最後管理權 | PermissionPolicy、RootInitializer、001／005／008 trigger、UI 隱藏操作 | HTTP PUT／DELETE root 403；root_identity_constraints 的 16 項直接 DB 探測全部回滾 |
| 功能規格 E27／L27 | 逐人逐模組四動作；角色模板複製；刷新持續 | PermissionManagement、PermissionRepository、RoleTemplates、permissions.js | api_integration：保存重讀、角色模板、並行版本、稽核失敗回滾 |
| 功能規格 G27 | root 不降權、稽核；防越級為可選策略 | PermissionPolicy、PdoTransactions、EventRepository；本案採防越級 E-10 | api_integration／admin_preview：越權及稽核回滾 |
| 權限矩陣 A2／F5–F44；串接總覽 B14 | 模板是建議；前端隱藏不取代每請求驗權 | Http/Kernel＋Authorization 重讀 DB，角色不是唯一權限來源 | 真實 HTTP：會員 Cookie、停用、移除權限、直接偽造請求都拒絕；未開放業務不因字典有模組而宣告完成 |
| U-06（非 Excel 原功能） | 固定管理員唯讀模擬 | owner／effective 身分、Kernel 統一拒絕寫入 | admin_preview 58 項；Node 及實際表單唯讀操作 |

## 階段三逐項核對

| Excel 來源 | 業主要求 | 對應實作 | 驗證依據 |
| --- | --- | --- | --- |
| 串接總覽 B7／B16 | 保留獨立 HTML，DB 為真實來源，正式資料不存 localStorage | PHP API、Controller／Service／Repository、Node HTML 發布 | content_contract 路由對照；公開 HTML 與 API 使用同一成功發布版本 |
| 功能規格 E6／F6／L6 | 館舍介紹、地址、開放時間、交通、外連，更新後同步 | 共用 attractions、GET /museum、publish.mjs | attractions_integration／opening_hours_integration／owner_requirements 的博物館與靜態 HTML |
| 功能規格 G6 | 電話／網址格式，標示更新日 | **本次補齊** PhoneNumber、phone／phoneUrl、選填 tel 輸入、009 migration；HTTP／HTTPS 官網原協定保留 | PhoneNumberTest；owner_requirements：有效／無效／清空／省略、DB 重讀、博物館 HTML 與安全撥號連結 |
| 功能規格 E5／G5／L5 的景點部分 | 首頁館舍／景點精選同步、只讀已發布、空狀態及圖片替代 | ContentPublication、publish.mjs、attractions.js 的圖片失敗替代 | attractions_integration／owner_requirements：後台修改後首頁文案同步；原歷史年表保留。電影／消息區塊在後續階段 |
| 功能規格 E13／L13 的景點部分 | 分類、區域、關鍵字及獨立詳情；店家與景點區分 | PublicAttractions、explore／food／places 頁及類別標記 | attractions_integration：分類、篩選、空結果及詳情；關鍵字按原型renderPlaces比對名稱／列表文案p.desc／分類補充，現行對應name／summary／typeLabel。收藏／行程保存依 U-08 留階段六 |
| 功能規格 G13 | 必要欄位、slug 唯一、座標範圍、公開狀態 | AttractionInput、enum、唯一索引、座標 CHECK；名稱必填沿用原型 | specification_alignment／attraction_constraints；slug 可自動產生，其他選填不擴張 |
| 功能規格 E19／L19 | 列表、搜尋、狀態、獨立編輯；刪除用通知／確認 | AttractionController／Management、admin/places、place-edit | attractions_integration；前端確認、版本衝突及失敗保留草稿 |
| 功能規格 G19／L19 | 刪除權限、引用檢查、soft delete、稽核 | Service 交易、routes／route_attractions 最小引用保護、EventRepository | 真實 DB／HTTP 引用阻擋、越權、409、交易回滾；未冒充路線管理 |
| 功能規格 E20 | 可選既有模擬景點試填 | **本次補齊** GET /admin/attractions/samples；選單、確認及共用套用元件 | owner_requirements：讀取不寫入、create-only 不取得一般列表、draft／未到期／已刪除不提供；Node：取消、重試、唯讀與確認競態 |
| 功能規格 E20／L20 | Maps 短網址預填，確認後儲存，失敗不自動保存 | MapResolver、Google Mapper、候選確認按鈕、AttractionInput | 既有實連驗收保留；本輪 map_fields／google_link 與安全輸入使用隔離／合成資料，不重算成即時 Google 成功 |
| 功能規格 G20；串接總覽 B11 | HTTPS／Google 網域、服務端展開、防 SSRF、金鑰只在伺服器 | GoogleUrlPolicy、SafeHttp、GooglePlaces、MapResolver | unsafe URL、重導向／DNS／IP／逾時邊界；無效輸入不呼叫 Google、不寫入景點 |
| 功能規格 G20 | 帶入欄位仍可人工修正、唯一 slug | attraction-form、共用週表、GooglePlaceLinkPolicy | map_fields／google_link；地點變更需確認或解除屬 U-10，不把 Google 原文存成網站介紹 |
| 串接總覽 B12；資料表建議 D11 | MIME、大小、掃描、用途 alt、授權資訊保存 | LocalImageStorage：JPEG／PNG／WebP、解碼／重編碼、受控 volume；媒體欄位 | attractions_integration／attraction_corrections；依 U-14 為內容檢查，非獨立病毒掃描。來源／授權選填但保存欄位 |
| 串接總覽 B13；功能規格 G13／L13 | 已發布且到期才公開；寫入後刷新 | 發布鎖、投影、原子 generation、Nginx gate、重試與排程 | 草稿／下架／未到期 404，產生失敗不公開舊版，恢復後同步；既有 persistence 套件另有重啟驗收 |
| SEO規格 C17／F17；功能規格列30的景點部分 | 不執行 JS 仍有正確 head／canonical | publish.mjs 產生完整 HTML | attractions_integration／Node publisher：直接 HTML、轉址與 canonical；全站設定仍留階段七 |

## 共通要求與正式交付差項

| 來源 | 已落實／已驗證 | 尚不能宣告通過的部分 |
| --- | --- | --- |
| 串接總覽 B9：TLS、HttpOnly／Secure／SameSite | Cookie 統一 HttpOnly／SameSite=Lax；production 設定拒絕 HTTP 或未開 Secure，已驗三種組合 | localhost 是開發 HTTP；正式網域／憑證／TLS 終止及實際 Secure Cookie 需在部署時驗收 |
| 功能規格 E34／G34／L34；串接總覽 B15 | server validation、參數化 SQL、context escaping、CSP、401／403、label、錯誤關聯、status、鍵盤及手機操作 | 完整 WCAG 2.1 AA 包含人工讀屏、全頁對比／縮放；本機 DOM 或 Node 測試不等於正式讀屏驗收 |
| 功能規格 H34 | 監控 /health 已在階段八排程 | 本次不提前建立或宣告完成；容器 health 不是同一項 API |
| 功能規格 E13、列5其餘區塊、列30／32全站 | 依 U-08 及既定 P0 階段分工 | 收藏／行程保存階段六；電影階段四、消息／優惠公開讀取階段五；全站 SEO 階段七，P0 整體仍未完成 |

以上是具體尚待驗收／後續範圍，不能用「階段二、三程式已處理」遮蔽，也不自行擴張本次實作範圍。

## 本次新增流程與工程設定（E-24）

範例 API 只讀資料庫原始六筆 legacy_id 1–6 中已發布、到期且未刪除的基本資料，最多六筆；名稱或文字以當前 DB 為準。不從 prototype.js／localStorage 重新生成資料。具 attractions.create 即可讀取，不因此授予 attractions.view。回應白名單不含 slug、version、mediaId、發布狀態、isMuseum 或 Google 關聯。空清單或讀取失敗不阻擋人工新增。

按「帶入範例」後先確認，取代基本資料與週表並解除舊 Google 關聯；圖片、使用者已填 slug、首頁／館舍旗標及發布設定保留。沒有自動 POST／PUT，只有按儲存才新增；這不是延後中的摘要／介紹自動補位。Google 候選切換也不沿用上一個地點的人工電話；沒有增加 Google 電話欄位、配額或付費查詢。

電話為選填，省略／空白保存空字串；提供時才驗證格式。80 字上限、接受國碼／空格／括號／點／連字號及 ext／x／#／分機為工程設定，不限定地區、市話或固定號碼長度，不推測國碼。phoneUrl 由後端衍生，不能當成可寫欄位。HTML 只輸出經驗證且 escaping 的文字與安全 tel 連結。參考 [MDN tel input](https://developer.mozilla.org/en-US/docs/Web/HTML/Reference/Elements/input/tel) 的地區格式差異與 [RFC 3966](https://www.rfc-editor.org/rfc/rfc3966) 的分隔符／分機語意；不宣稱可驗證號碼真實存在。

009 migration 只新增 attractions.phone VARCHAR(80) NOT NULL DEFAULT ''，不修改已套用的 001–008；既有內容不猜測電話。累計十份 migration、21 張表、181 欄；45 個 API 操作。兩庫已先備份再升級，正式密碼／.env／原始交付均保留。

另外依功能規格E34／G34實測原型提示文字對比3.28／3.46，低於一般文字4.5門檻。phase2.css只加深muted、表單提示與標題說明，不更改版型、主色或website原檔；以瀏覽器實際computed style及背景合成後複核，新提示文字為5.62；新增表單所抽測的可見文字沒有低於適用門檻。門檻與計算依 [W3C SC1.4.3](https://www.w3.org/WAI/WCAG21/Understanding/contrast-minimum.html)。這是必要的可讀性修正，不等同完整WCAG認證。

## 驗證紀錄

本輪真實結果：

| 檢查 | 實際結果 |
| --- | --- |
| 14組真實HTTP／MariaDB／Mailpit整合 | 460項通過；其中25項專門覆蓋本次E20／G6，內含3種範例排除條件彙總。原先root16項探測亦包含於原有彙總，未重複加總 |
| 獨立OpenAPI驗證 | 562份回應、182份成功JSON請求與1份合成Mapper候選，0格式差異；觀察41種操作，完整路由清單另核對45種一致，不宣稱每個狀態都已schema驗證 |
| PHPStan level10／PHPat | 通過 |
| PHPUnit | 44項、213斷言通過 |
| Node | 73項通過，含範例確認／取消／重試／唯讀／競態及舊電話清除 |
| 前端建置 | 64頁通過 |
| PER-CS | 127檔，零差異 |
| Rector | 限定4個PHP變更檔dry-run；提出兩個既有風格建議，已檢視未套用，不記成零建議 |
| 真實DB帳號失敗／回滾 | account_persistence.php的6項通過，不含於460項 |
| Production設定防護 | 3種HTTP／HTTPS／Secure組合符合預期；僅一次性程序改環境，不變更.env |
| 空庫及資料保留 | 十份migration、重跑、seed、固定admin初始化、六筆範例通過；還原主庫舊備份後六筆景點所有原有欄位逐筆一致；臨時庫及授權已清除 |

PHP／Composer 在 Docker 執行，測試 fixture 與所有秘密留在被忽略的 artifacts。本輪零次Google實連，不以合成候選替代以前的實連證據。未重跑無關的SMTP故障／容器重啟測試，不把前次數量混入本輪。瀏覽器以實際桌面／手機畫面與DOM確認：範例六筆、取消不改輸入、確認才套用、不複製slug／圖片／館舍旗標／發布狀態；桌面新增可保存電話，編輯頁重讀一致，錯誤時aria-invalid／錯誤關聯及第一欄焦點正確，Enter確認重載後錯誤清除。手機以Enter確認範例並保存空電話草稿；唯讀模擬直接開新增網址時，範例、電話及儲存均停用。手機請求390×844，實際innerWidth355／scrollWidth341，沒有水平溢出。最後恢復桌面尺寸；只按確切ID軟刪除本次兩筆景點與一個普通管理員，沒有刪除其他測試／主庫資料。

公開頁亦實際確認電話連結與更新日期。桌面量測為innerWidth1745／scrollWidth1732，三個範例控制項同列；範例操作沿用原型Grid並局部調整三欄／手機單欄，沒有修改原始style.css。原型57個HTML入口均保留，現有64頁來源及成品齊全，無破損資產引用、主要導覽差異或無效JSON-LD。原始69檔逐位元組一致；不是全站逐像素對照或正式讀屏認證。

七個重整後功能快照已通過Docker PHPStan／適用單元測試、前端建置／測試與路由契約檢查；包含共用前端、景點CRUD、週表、Google欄位、地點關聯、內容選填及獨立驗證工具。修正歸回對應原功能，不新增代理失誤的進度提交；本逐條對照及現況導讀是新的文件交付。合併前保留完整Git bundle並核對原始交付、最終tree、階段分支及遠端refs。

主要重現命令：

```powershell
docker compose run --rm --no-deps frontend npm test
docker compose exec -T app php /var/www/tools/vendor/bin/phpstan analyse --configuration=phpstan.neon --no-progress
docker compose exec -T app php /var/www/tools/vendor/bin/phpunit --configuration=phpunit.xml
python backend/tests/owner_requirements.py
python backend/tests/openapi_contracts.py --dependency-path artifacts/audit-jsonschema
```

兩個 Python 命令有共用隔離 fixture，需循序執行；後者已包含前者，通常只需後者。額外 Python 工具依賴依 tests/requirements.txt 安裝，不是網站執行依賴。
