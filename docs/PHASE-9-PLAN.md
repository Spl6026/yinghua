# 階段九：消息／優惠管理與詳情前後篇

日期：2026-10-29。**僅規劃，尚未授權實作、未建立階段九分支，沒有執行 migration 或修改應用程式。** 現況為72 API／68固定頁／33表316欄／18 migration；P0功能與工程AA驗收結果見 [AA最終矩陣](P0-AA-FINAL-20261029.md)及[完整驗收](P0-ACCEPTANCE-20261029.md)。正式主機／寄信／素材及安全交接事項保持獨立，不冒稱已上線。

本階段屬P1，完成後仍須階段10–12才能宣告整個P1完成。摘要／介紹自動補位、電影結束時間、推薦路線、完整旅程、全站搜尋及營運後台均不納入本階段。

## 1. 規格及原型基準

S＝Excel「功能規格」明文，D＝工作簿中的API／資料表建議，P＝原始website已存在的介面，E＝具體工程設計。未定義欄位先核對原型，仍無依據者不增加阻擋保存／發布的限制。既有DTO／測試不是規格來源。

| 來源儲存格 | 本階段要求 | 實作對應 |
| --- | --- | --- |
| 功能規格 E8／G8／L8 | 電影獨立詳情、完整介紹／海報／場次、依發布排序的前後篇；無相鄰隱藏、鍵盤可操作 | 既有films詳情擴充、film neighbors |
| E10／G10／L10 | 消息正文、日期、封面、安全富文字及alt；新分頁開啟，前後篇同分類 | news詳情、event／city分組 |
| E12／G12／L12 | 優惠店家／期間／條款／使用限制及縮圖前後篇，過期明示 | offers詳情及有效狀態 |
| E22／G22／L22 | 消息CRUD、草稿／發布／排程、event／city限定、正文安全、與優惠獨立 | news後台三頁及管理API |
| E23／G23／L23 | 優惠CRUD、關聯店家存在、期間合理、條款必填、只更新優惠 | offers後台三頁及店家選擇 |
| E29／G29／L29 | 所有詳情同主題／分類的已發布前後篇、縮圖／可辨連結、無空連結、無燈箱 | 景點／電影／消息／優惠四類詳情共用呈現 |
| E34／G34／L34、串接總覽B15 | 標籤／焦點／錯誤／安全輸出與WCAG2.1AA、401／403 | 全部新API、表單及前後篇 |
| 資料表建議 A14–E16、A11–E11；API草案C7／C13／C32及功能規格H8／H10／H12／H22／H23／H29 | 既有news_posts／merchants／offers／媒體與各主題neighbors的設計參考 | 正式端點與DDL以本文件的E提案為準，草案不升格為硬性限制 |

已唯讀核對原始 `website/admin/news*.html`、`offer*.html`、`website/js/app.js` 的renderArticleNeighbors及既有後端。原型消息前後篇會混合活動與城市消息，與L10不同，採Excel同分類。原型的資料陣列順序不取代G8發布排序。

## 2. 真正既有能力與本次新增範圍

| 已存在，可復用 | 階段九需要補齊 |
| --- | --- |
| news_posts／news_aliases、offers／offer_aliases、merchants；012–014 migration | 管理列表／單筆讀取／刪除查詢及HTTP操作，不重建資料來源 |
| NewsInput／OfferInput／MerchantInput；NewsManagement／OfferManagement／MerchantManagement的匯入prepare／save | 對HTTP提供明確的應用方法；CLI與HTTP共用驗證、交易及稽核規則 |
| 公開消息／優惠API、分類／有效期、發布快照與公開HTML、歷史網址對照 | 完整詳情及前後篇資料與同版HTML |
| RichTextSanitizer、BodyImages、ContentCover、ContentStatus、OfferValidity | 可操作的正文與圖片編輯介面、欄位錯誤映射 |
| 共用媒體上傳，module已支援news／offers；權限字典已有兩模組 | 後台頁面真正串接，完成create-only／edit-only等權限組合驗收 |
| frontend/pages/admin下六頁仍為未開放的保留頁 | 還原原型版面與互動，替換示範資料與localStorage |

不能把既有匯入Service說成已有完整管理API；目前沒有 `/admin/news`／`/admin/offers` CRUD或四類neighbors路由。

## 3. 前端頁面與互動

| 網頁 | 預定完成 |
| --- | --- |
| `/admin/news.html` | 原型列表／新增入口／編輯／刪除確認；補搜尋、event／city、狀態篩選與分頁[E] |
| `/admin/news-add.html`、`news-edit.html?id={uuid}` | 原型標題／分類／日期／摘要；必要補正文、封面上傳、活動起訖、發布時間與單筆SEO欄位[S／E]；新增與編輯共用表單模組 |
| `/admin/offers.html` | 原型獨立優惠列表、編輯／刪除／新增；顯示店家、期間、有效狀態與發布狀態，兩者不混為一談 |
| `/admin/offer-add.html`、`offer-edit.html?id={uuid}` | 原型標題／店家／行政區／優惠內容／日期／圖片／介紹／條款；店家改用真實ID選取，提供收合式必要店家編輯區[E] |
| `/events.html`、`activities.html`、`city-news.html`、`news/{slug}.html` | 與後台保存／排程／下架同步；消息正文、圖片與同分類前後篇 |
| `/offers.html`、`offers/{slug}.html` | 優惠與店家資料同步；開始前／進行中／過期標示、條款及同主題前後篇 |
| `/films/{slug}.html`、`places/{slug}.html`、`museum.html` | 保留P0介紹與場次／營業時間；增加同主題前後篇，非當週電影仍可連到已發布介紹 |
| `film-detail.html`、`news-detail.html`、`offer-detail.html`、`place-detail.html` | 沿用現有合法ID／歷史對照與轉址，不把舊陣列索引當UUID |

版型沿用原型側欄、頂列、紙色／綠色、面板、表格及`article-neighbors`／`article-neighbor`卡片。原型縮圖是裝飾alt=""，由文字標題提供連結名稱；內容主圖仍依用途提供alt。新分頁入口使用真實anchor＋target=_blank／rel=noopener，並提示會開新分頁；同頁前後篇依原型一般頁面導覽，不加燈箱或強制JS開窗。頁面仍可直接貼網址閱讀。

正文先規劃原生JS的小型工具列（段落、標題、粗斜體、清單、連結、消息圖片）及可鍵盤操作的文字輸入／預覽；只提交HTML字串，不讓前端成為安全邊界。不預先安裝富文字套件；若原生工具不能可靠保留格式／選取／鍵盤操作，再獨立評估成熟套件並記錄相容性。安全白名單以既有Sanitizer實際能力逐項核對，不因工具列選項就擴大允許HTML。

保存前保留草稿；422定位欄位、409保留輸入並明確重新載入，401原頁恢復不自動重送；權限撤銷與唯讀模擬不得寫入。資料保存成功與公開發布成功分開呈現，不因發布暫失敗誤報資料未保存。

## 4. 欄位限制與日期語意

| 欄位 | 規劃限制與依據 |
| --- | --- |
| 消息標題 | 必填[P news-add required]；長度上限沿既有技術邊界[E] |
| 消息分類 | event／city[S G22]，select有預設；不另增加「必須人工選一次」 |
| 摘要／正文 | 原型摘要無required，Excel沒有指定正文必填；可留空。正文需安全富文字[S G10／G22]，不新增空正文禁止發布 |
| 原型消息「日期」 | 明確標成「發布日期與時間」，對應publishAt，延續P0公開卡片已顯示「發布日期」的語意；活動起訖另外列選填欄位，不把兩種日期混在一起。原型只有date，補時間控制與標籤是E提案；空白沿用立即發布，不增加必填 |
| 活動起訖 | 可空；兩者有值才檢查結束不早於開始[E，沿既有校正與DTO]；不套用電影U-16規則，也不強制city消息有活動時間 |
| 優惠標題／合作店家／優惠內容 | 必填[P offer-add required]；關聯店家須存在[S G23]，送merchantId，不能只以名稱匹配 |
| 使用條款 | 必填[S G23]，即使原型textarea未標required仍須補標籤及錯誤；禁止僅空白／空HTML假裝有條款[E] |
| 優惠介紹、開始／結束日期 | 原型未required；日期可各自留空，兩者都有時validTo>=validFrom[S資料表建議D16／G23]；期間含台北當天結束日，延續P0邏輯 |
| 封面／正文圖片 | 圖片本身不新增必填；有內容圖片須alt[S G10／G29、資料表D11]，裝飾圖依用途空alt。共用上傳大小／格式／重編碼限制標E，不擴成病毒掃描 |
| slug／發布狀態／publishAt | slug唯一[S G8及各資料表草案]，空白可自動產生[E]；草稿／發布／下架沿現有enum。選發布＋未來publishAt即排程；日期須有效[S G22]，發布時間空白沿用立即發布，不另加排程時間只能大於現在的普遍保存限制 |
| SEO／來源／授權說明 | 沿既有選填與公開head優先序；不因新增後台而變必填 |

既有schema的NOT NULL＋空字串預設，不等於介面必填。欄位長度、日期格式、媒體白名單及版本號均在契約中標E；正式實作前再做Excel→原型→DTO→DB逐欄對照，既有程式與來源不一致時修正程式，不反用程式作規格。

## 5. API 提案

以下皆為**預計新增**、不是已實作；路徑前綴 `/api/v1`。逐項寫入OpenAPI並補成功／拒絕案例，HTTP責任只提供JSON API。

| 方法／路徑 | 用途／權限 |
| --- | --- |
| GET `/admin/news` | 搜尋／type／status／page／pageSize列表；news.view |
| GET `/admin/news/{id}` | 編輯單筆；news.view或news.edit，回傳使用目的所需資料[E，延續create-only／edit-only設計] |
| POST `/admin/news` | 建立；news.create |
| PUT `/admin/news/{id}` | 更新，帶version；news.edit |
| DELETE `/admin/news/{id}` | 確認後軟刪，帶version；news.delete |
| GET `/admin/offers` | 搜尋／店家／status／有效狀態／分頁；offers.view |
| GET `/admin/offers/{id}` | 單筆；offers.view或offers.edit |
| POST `/admin/offers` | 建立；offers.create |
| PUT `/admin/offers/{id}` | 更新，帶version；offers.edit |
| DELETE `/admin/offers/{id}` | 軟刪，帶version；offers.delete |
| GET `/admin/offers/merchants` | 合作店家搜尋／分頁及必要公開profile欄位；offers.view或create或edit；不要求額外attractions.view |
| POST `/admin/offers/merchants` | 優惠表單內建立必要合作店家profile；offers.create；可不關聯景點，不擅加地址／電話／網站必填 |
| PUT `/admin/offers/merchants/{id}` | 修正共享profile，帶version；offers.edit；明示會影響同店家其他優惠，寫稽核並同步發布 |
| GET `/attractions/{slug}/neighbors` | 同類景點／美食／店家；museum依其景點分類；公開 |
| GET `/films/{slug}/neighbors` | 全部已發布電影按發布排序，不限本週；公開 |
| GET `/news/{slug}/neighbors` | 同event／city；公開 |
| GET `/offers/{slug}/neighbors` | 同優惠主題；公開，依公開詳情規則包含仍發布的過期內容並顯示狀態 |

合計提案17個新操作，若全部採用，72→89；不是實作結果。商家三個helper端點是讓優惠維護閉環的E補充，沒有獨立商家管理頁、刪除／匯出或新權限模組。若實作細設改成同一交易內建立店家，需先修訂本表與契約／操作數，不能兩套並存。靜態merchants路由先於`{id}`註冊，避免被識別為優惠UUID。

復用現有媒體上傳／列表，傳module=news或offers及用途create／edit；不新增重複的消息／優惠上傳API。店家可獨立於景點[D C15、既有MerchantInput]；不強制管理員先新增一個景點。新增優惠不得隱式改寫既有共享店家，修改profile須使用明確動作。

neighbors回應提案：`{previous: null|{slug,url,title,image,alt}, next: null|{...}, generation}`。當前不存在／未公開同為404；兩端為null而不是空href。排序及產生時間與公開快照同源，發布就緒保護保持；不得拿即時DB的一版neighbors接到另一版HTML。這是E契約設計，S只要求功能。

## 6. 資料表、排序與發布

**預計新業務表：0。** 不另存prev_id／next_id、不重建消息／優惠／媒體／店家表、不回填另一套內容副本。

| 既有表 | 預計用途／變更 |
| --- | --- |
| news_posts／offers | 復用既有欄位、版本、deleted_at、分類／日期與SEO；補管理查詢及軟刪，不預設新增欄位 |
| merchants | 復用name／district／address／phone／website／status／attraction_id／version；必要profile建立／修正，不搬遷原資料 |
| media_assets | 復用模組隔離、cover引用／正文圖片引用、來源與alt；刪除引用檢查需覆蓋新編輯流程 |
| news_aliases／offer_aliases、attraction_aliases／film_aliases | 改slug時保存舊網址對照，軟刪後舊址不復活內容 |
| attractions／films／film_screenings | 四類相鄰查詢與電影詳情既有場次；不增加ends_at或場地 |
| audit_logs／content_publications／SEO相關表 | 延續交易稽核、同版發布、head覆寫與下架一致性 |

既有news_public／offer_public索引先保留。管理篩選與同分類neighbors以MariaDB12.3真資料EXPLAIN驗證；只有證據需要時才提交新的增量migration，例如候選news `(deleted_at,status,type,publish_at,id)`，不先宣稱必須新增或提前改schema。既有18份migration不回改；沒有新增欄位／索引需求時，本階段可以0 migration。

E排序：同主題以 `(publish_at DESC,id ASC)` 穩定排序，同時間以UUID打破平手。上一篇＝此順序前一筆（較新），下一篇＝後一筆（較舊）。消息按type、景點按category；電影不按片長／本週、優惠不按店家限縮（Excel未要求同店家）。只有已發布且已到發布時間、未軟刪、符合該主題公開關聯規則者參與；優惠沿用合作店家可公開條件。過期優惠的詳情仍能閱讀，不因過期就當草稿隱藏。

單筆更新／軟刪、稽核與版本核對在同一DB交易；成功後由既有發布流程更新相關列表、詳情、首頁及全組相鄰卡片。改分類、slug、發布時間或下架時，要重算受影響的舊／新群組；更新店家profile需更新所有引用優惠。發布失敗維持就緒閘門與重試，不手動以SQL／localStorage修補。

## 7. 分層、復用與檔案提案

沿用 [架構](ARCHITECTURE.md) 的 Controller→Service→Repository 與建構子注入。Controller只解析請求／授權上下文及Response；Service負責DTO、跨表規則、交易、版本、稽核與發布觸發；Repository負責參數化SQL／映射，不碰HTML。HTML留在frontend建置／發布與瀏覽器。

| 預計新增或擴充 | 責任 |
| --- | --- |
| `backend/src/Controllers/AdminNewsController.php`、`AdminOfferController.php`、`OfferMerchantController.php` | 薄HTTP入口，不把SQL或HTML放Controller |
| 既有 `NewsManagement.php`／`OfferManagement.php`／`MerchantManagement.php` | 新增明確listing／get／create／update／delete應用入口；抽出CLI與HTTP共用的準備／保存規則，不讓HTTP偽造ImportItem依賴CLI格式 |
| `DTO/AdminNewsQuery.php`／`AdminOfferQuery.php`／`ContentNeighbor.php`／`ContentNeighbors.php`等 | 型別化查詢／相鄰輸出；復用NewsInput／OfferInput／MerchantInput，有限狀態沿用enum |
| 既有各主題Repository；提案 `Services/ContentNeighbors.php` | Repository各自查詢／映射；共用Service協調公開範圍與相鄰結果。若需要可替換來源才抽小interface／Strategy，不建立巨大通用CRUD基底或動態拼表名 |
| config routes／Kernel組裝／OpenAPI | 註冊依賴與17個操作，保持PSR／PHPat分層；實際類名依既有命名核對 |
| `frontend/assets/js/api/news-api.js`／`offers-api.js`及pages下admin-news／news-form／admin-offers／offer-form模組 | API Adapter、頁面列表／表單控制器，新增／編輯共用；不寫localStorage示範資料 |
| `components/content-neighbors.js`、必要正文工具列／店家選擇元件[E] | 共用安全DOM／模板、可存取操作；不為兩個表單創通用表單框架 |
| 既有core news-view／offer-view、scripts public-news／public-offers／publish | API與靜態發布共用顯示規則，SSG輸出完整正文／前後篇，無JS仍可讀；動態更新不重複插卡片 |
| `docs/modules/news-offers-admin.md` | 中文資料流、欄位來源、Controller／Service／Repository／頁面檔案、日期、發布及操作說明，隨實作交付 |

設計依據：[PHP-FIG PSR-11建議](https://www.php-fig.org/psr/psr-11/)避免在物件內使用Service Locator，延續顯式建構子依賴；不為此引入Spring／新容器。[OWASP XSS Prevention](https://cheatsheetseries.owasp.org/cheatsheets/Cross_Site_Scripting_Prevention_Cheat_Sheet.html)區分富文字清理與輸出context encoding，復用既有Sanitizer，不把CSP或前端預覽當安全驗證。這些是E實作依據，不冒稱業主指定design pattern。

公開neighbors的讀取必須從成功發布快照的typed內容集合計算，沿用PublicNews／PublicOffers等公開服務邊界；Repository只供管理查詢及產生發布快照，不在公開neighbors請求中即時查DB補另一版資料。共享的相鄰選擇規則亦供發布建置使用，API回應與無JS HTML由同一版集合產生。

## 8. 執行順序與功能提交

開始實作前，先按既有規則處理階段八的驗收／備份／tree與原始69檔核對／merge commit及保留分支，再從最新main建立提案 `phase/09-news-offers-admin`；不能把本計畫提交當成階段九已開始或已授權。正式交接差項沿U-18另列；不將其默默標成已驗。

| 子階段／功能提交 | 完整交付內容與驗證 |
| --- | --- |
| 9A 消息管理 | 列表／單筆／CRUD、交易／刪除／版本、三頁表單與正文／媒體、契約／測試／閱讀指南；原型桌手機對照 |
| 9B 優惠與合作店家 | 獨立CRUD、必要profile選擇／建立／修正、有效期及條款、共享引用／發布、契約／測試／三頁介面 |
| 9C 所有詳情與前後篇 | 四類neighbors、完整正文／海報／場次／條款、發布同源、所有詳情版型及導覽、契約／回歸／指南；按可獨立驗證的主題必要時再拆提交 |
| 9D 整合驗收與交接 | P0回歸、權限／安全／AA增量覆核、完整原型比對、API／表／頁盤點、全站導讀更新及隔離資料清理 |

只有確有跨功能共用前置時才另立準備提交，不把整階段擠成一筆，也不按php／js／sql拆提交。自己的修正歸回相應功能提交；完成驗收後按Git流程合併，保留階段分支。階段九結束不自動開始十。

## 9. 驗收清單（預定，未執行）

1. 逐欄Excel／原型／DTO／DB對照，摘要／正文／日期可空，優惠條款必填；沒有額外發布必填。
2. news的event／city／非法type、分類與優惠隔離；優惠CRUD不進news_posts。
3. 標題／slug、版本、日期格式／台北邊界、活動與優惠反向日期、開放日期及過期標示。
4. 六頁新增／編輯／刪除取消／確認、列表搜尋／篩選／分頁，重整及直接貼網址可用。
5. 合作店家已存在／不存在／停用／刪除／重名；不因顯示名稱相同選錯ID；獨立店家與可選景點關聯。
6. create-only、edit-only、delete-only、view-only、無權限、停用／撤權及固定admin唯讀模擬；API真MariaDB 401／403，helper不擴大其他模組權限。
7. 版本409／兩分頁競爭／刪除競爭、422保留輸入、舊回應不覆蓋新表單、保存中互斥與重新載入取消。
8. HTML script／事件屬性／危險URL／嵌套與畸形標記、正文圖片alt、跨模組媒體、封面修改與引用刪除；公開無注入。
9. 草稿→發布→排程到期→下架／軟刪；首頁／分類／詳情／歷史網址／網站導覽／SEO一致，資料保存與發布結果可辨。
10. 真實發布快照／API／HTML同版；發布中斷與過期閘門、重試、重啟保存，不能以舊HTML掩蓋下架。
11. neighbors零／一／多筆、第一／中／最後、同時間ID平手、消息跨分類、景點跨類、非當週電影、過期優惠；不產生草稿或空href。
12. 更新／改slug／改分類／刪除中間文章與店家狀態，前後鄰卡片同步更新；無JS及另開分頁直接讀取一致。
13. 以同筆測試內容比對原型六頁及四類詳情，桌面／390px／320px，表格局部捲動、圖片／場次／條款及前後卡片，逐項記錄S／E差異。
14. 鍵盤／反向Tab／Escape／原焦點返回、原頁到期恢復、錯誤摘要、輔具、200%／400%與文字間距；依既有AA矩陣覆核新元件，不指定NVDA為唯一工具，不只跑自動掃描。
15. PHP型別／PHPat／格式、Rector僅dry-run、JS行為、OpenAPI成功與拒絕、MariaDB查詢計畫，既有P0回歸；未執行不能列通過。
16. security-audit審查本次管理API及媒體／富文字邊界；保留實際風險與正式環境差項，測試假帳號／媒體／設定清理、原始website與Excel核對。

完成時更新本文件實際狀態、PHASE-9-ACCEPTANCE、PROJECT-GUIDE、IMPLEMENTATION-PLAN、需求／資料表來源、OpenAPI及中文模組指南；交付實測證據而非將預定清單直接勾滿。
