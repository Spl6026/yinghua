# 階段五：公開消息、優惠與首頁整合計畫

設計日期：2026-10-22。狀態：**5A–5E 已實作並完成本機驗收，P0 尚未完成**。分支 `phase/05-public-content-home` 從最新 main 建立。下文保留設計與驗收基準；實際檔案／數字及檢查見[公開內容指南](../modules/public-content.md)，未完成項目不當成已交付。

依據：[總計畫](IMPLEMENTATION-PLAN.md)、[原始 Excel](../../映畫竹塹_後台工程串接功能規格.xlsx)、原始 website、[架構](../ARCHITECTURE.md)及[規格基準](../DEVELOPMENT-WORKFLOW.md#規格基準u-122026-10-18)。S 是功能明文，D 是資料表／API 建議，U 是使用者指定，E 是工程設計。**欄位被列出不代表必填。下文保留細節設計，實際交付／檢查見 [階段五驗收](PHASE-5-ACCEPTANCE.md)。**

## 1. 交付範圍與優先級

本階段完成後，維運者可用受控 CLI 匯入、更新、發布／排程／下架消息及優惠；訪客在另一個瀏覽器看到真實列表、分類、分頁、首頁與可直接開啟的基本閱讀頁。優惠與消息使用不同資料來源。

| 來源 | 本階段交付 | 界線 |
| --- | --- | --- |
| S：功能規格 E5–L5，J5=P0 | 首頁博物館、景點、電影、消息讀同一成功發布版本；空狀態、錯誤與圖片替代 | 延用階段三／四已接入區塊，補齊消息與一致性；不另建首頁 CMS |
| S：功能規格 E9–L9，J9=P0 | events／activities／city-news 的分類、全部文章區、分頁與正確日期 | 只允許 event／city，優惠不能混入 |
| S：功能規格 E11–L11，J11=P0 | 獨立優惠列表、店家、期間、有效狀態與詳情連結 | 過期、未開始、草稿及下架不出現在預設有效列表 |
| E-17：既定 P0 前置方案 | 真實內容的受控 CLI、必要基本閱讀頁與網址相容 | 不是 Excel 指定 CLI，也不等於交付完整後台 CMS |
| S：串接總覽 B7／B12–B15；功能規格 E30–L30／E34–L34 的本模組部分 | 共用媒體、發布、基本 SEO head、輸出安全、RWD／鍵盤與狀態提示 | 不提前宣告階段七全站 SEO 設定或階段八部署驗收完成 |

完整 `admin/news*`、`admin/offers*` CRUD、店家管理介面、完整詳情的上一篇／下一篇與其 API，仍在 P1 階段九（功能規格 J10／J12／J22／J23／J29）。收藏／行程保存仍在階段六；全站搜尋在階段十三。優惠只展示內容與使用條款，不增加領券、核銷、訂單或付款。

CLI 的 DTO、Service、Repository 與安全正文處理會供階段九重用；不先開放一組未交付介面的管理寫入 API。

## 2. 原始 website 與預計頁面對應

所有修改來源均在 `frontend/`，原始 `website/` 保持不變。`.html` 網址、主要區塊順序、字體、色彩與卡片結構沿原型；不以「串接」為由改版。

| 原始 website | 預計 frontend／公開輸出 | 本階段作動 |
| --- | --- | --- |
| `index.html` | `frontend/pages/index.html` → 發布 HTML | 保留主視覺、博物館／精選、電影三卡、城市美食、消息三列與主題入口。消息改用真實資料，日期／標籤／地點不再寫死；其餘已串接區塊回歸 |
| `events.html` | 同名前端頁／發布 HTML | 保留上方分類、推薦主文與下半部「所有文章」。推薦主文取目前分類首筆已發布資料；下半部仍能分頁列全篇，不因上方推薦而漏文章 |
| `activities.html` | 同名前端頁／發布 HTML | 固定 event 分類，沿用原有標題與消息卡片，新增必要分頁 |
| `city-news.html` | 同名前端頁／發布 HTML | 固定 city 分類；不讓 query 將此頁切成活動或優惠 |
| `offers.html` | 同名前端頁／發布 HTML | 保留 place-grid／place-card、店家、行政區、優惠內容、期限與詳情按鈕；預設僅有效優惠，分頁不混消息 |
| `news/slow-market.html`、`street-photo.html`、`park-concert.html`、`old-town-notice.html` | 相同網址及新 `/news/{slug}.html` | 封面、分類、標題、摘要、正文、發布日期；活動日期／地點有資料才呈現。返回列表及原有一般導覽保留，無燈箱 |
| `news-detail.html?id=0..3` | 舊入口 → canonical | 用固定 legacy_id 對照原型四筆順序，不用現在列表位置；公開目標 301，其他 404 |
| `offers/or-crafts.html`、`shanfang.html`、`autumn-coffee.html` | 相同網址及新 `/offers/{slug}.html` | 真實封面、店家、優惠、介紹、條款與有效期間；已過期但仍發布的詳情顯示明確過期提示 |
| `offer-detail.html?id=101..103`、原型使用的 0..2 舊索引 | 舊入口 → canonical | 固定對照，不把 UUID／legacy ID／位置混用；已下架或無效目標 404 |
| `news/coffee-walk.html` | 受控舊網址相容 | 原型內容明列「店家優惠」，目前獨立優惠另有 autumn-coffee。建議保留舊路徑作該優惠的 301 alias；只在對應優惠已發布時生效，不建立假 city 消息（E-26） |
| `admin/news.html`、`news-add.html`、`news-edit.html`；`admin/offers.html`、`offer-add.html`、`offer-edit.html` | 現有前端守門／待開放頁 | 階段五不串接 CRUD，不顯示匯入內容已可由後台管理；階段九才交付 |

原型差異須明列與驗證：

- 首頁沒有獨立優惠卡片區，只有優惠導覽／主題入口；本階段不擅自增加區塊。
- 首頁消息列的 `.event-date`／`.event-type`／`.event-location` 要保留。額外資料 `eventLabel`、`eventLocation` 為原型顯示需求的 E-26 選填欄位，不新增業務必填限制。
- 原型 `website/js/app.js` 的消息卡日期補固定 2026，正式版改從完整日期格式化，不能跨年仍顯示 2026。活動日期與發布日期分開，不把兩者互代保存。
- 原型優惠 HTML 與 app.js 的日期／條款有差異，實際載入後 app.js 會覆寫優惠內容。受控示範來源以原型 `defaultOffers` 的 101–103 三筆為主，記錄來源差異；正式內容另行匯入，不把示範文案當客戶正式資料。
- `frontend/assets/js/public.js`、`pages/public-details.js`、`components/content-cards.js` 目前使用 prototype 資料；新增受管標記及專屬初始化，避免 API／SSR 被展示資料覆寫。搜尋未實作的狀態保留，不能因串接消息宣稱全站搜尋完成。
- 基本閱讀頁先移除原型假前後篇，階段九才接真實同分類 neighbors；這是已排定的 P0／P1 差異，不能宣稱本階段已完全交付詳情規格。

## 3. 欄位與限制逐項對照

### 3.1 消息／活動

| 預計輸入欄位 | 規則 | 來源 |
| --- | --- | --- |
| `title` | 必填且非空白 | 原型 news-add／news-edit 的 title 有 required；F9／F22 只列欄位 |
| `type` | event／city 白名單，匯入時明確指定 | S G9／G22；原型分類選單固定兩類 |
| `summary` | 選填，空白不從 Google 或示範內容自動補位 | 原型 desc 無 required；U-12；不延伸已延後的 U-11 |
| `body` | 選填；接受受限安全富文字，保存淨化後版本供 API／HTML 共用 | 原型未定義正文必填；S G10／G22 的安全要求在共用寫入先落實，完整編輯 UI 留 P1 |
| `eventStartAt`／`eventEndAt` | 各自可留空；提供時須為有效完整時間，兩者都有時 end >= start。相等不自創禁止；不要求每則 city 或 event 都填一組日期 | S G9 日期區間、F9／F22；原型 date 非必填，沒有強制完整區間 |
| `eventLabel`／`eventLocation` | 選填；原型首頁的「市集・生活」與地點文字。未填顯示分類／省略地點，不猜地址 | 原型 index 的 event-row；E-26，不是 Excel 欄位硬性要求 |
| `slug` | 留空由固定 ID 產生；提供時驗格式、唯一性與歷史網址占用 | D C14 UNIQUE；沿用階段三／四 E 的網址規則 |
| `coverMediaId`／`coverUrl` | 選填且擇一；安全 HTTP／HTTPS 圖片網址不由伺服器任意下載 | 原型消息無圖片必填；D C14、S B12；外連方式沿用電影 |
| `coverAlt` | 有圖須有有效 alt，可沿用選定媒體 alt；沒圖不要求輸入 | S 串接總覽 B12、G10／G29；不把有圖條件擴成必須有圖 |
| `coverSource`／`coverLicense` | 選填保存，不新增授權文字必填門檻 | D 資料表建議 C11；沿用既有媒體來源／用途覆寫 |
| `status`／`publishAt` | 沿用 draft／published／archived；新增預設草稿，發布且未填 publishAt 才取本次時間；更新省略保留原值，明確清空表示本次時間。未來時間排程 | S 串接總覽 B13、G5／G9；D C14；具體 enum／空值語意為 E |
| `seoTitle`／`seoDescription` | 選填覆寫；未填用本站內容生成基本 head，不增加 SEO 必填 | S E30／SEO規格 F16；沿用現有發布，完整設定在階段七 |
| `id`／`version` | 新增 ID 由 Service 建立；更新帶已讀版本防覆蓋，不能只靠標題 upsert | D C14；E-26 匯入識別／並行控制 |

活動結束不等於消息下架：Excel 未要求自動隱藏過往活動文章，archive 仍按發布狀態列全篇；可顯示已結束資訊。不得以優惠的有效期規則刪除消息歷史。

### 3.2 優惠與店家

| 預計輸入欄位 | 規則 | 來源 |
| --- | --- | --- |
| `title`／`merchantId`／`discount` | 必填；店家須存在、未刪除，標題／優惠內容不能全空白 | 原型 offer-add／edit 的 title／merchant／discount 有 required；S G23 關聯店家存在 |
| `terms` | 必填，不能只有空白或沒有文字的標記；不替使用者產生通用條款 | **S G23「條款必填」**；原型 textarea 無 required，Excel 優先；階段五寫入規則先共用，P1 不再另改一套 |
| `description` | 選填，純文字段落；不擅自自動補正文 | 原型 desc 無 required，F23 非必填清單 |
| `validFrom`／`validTo` | 選填 DATE；有值必須有效 YYYY-MM-DD，兩者都有時 validTo >= validFrom | S G11／G23；原型 startDate／endDate 無 required；不把比較規則升格成兩欄必填 |
| 日期空值與有效日 | 建議空起日表示不限制起日，空迄日表示未設定截止，顯示「依店家公告」；台北日期包含起迄當日，截止日隔天 00:00 才過期 | 原型 endDate 空值顯示「依店家公告」；E-26 的完整判斷，Excel 沒有時區或空值算法 |
| `slug`／封面／alt／來源／授權／狀態／發布時間／SEO／版本 | 與消息同一共用規則；封面資產須屬 offers，不能挪用不可見的他模組資產 | S B12／B13／E30／G34，D C16；E-26 共用技術設計 |
| 店家 `name` | 建立獨立店家時必填非空白，以承接原型必填 merchant 名稱；不依同名自動合併 | 原型 merchant required；D C15；UUID 識別為 E |
| 店家 `district`／`address`／`phone`／`website` | 選填。行政區沿原型北區／東區／香山區／新竹市；電話與網址非空才驗格式 | 原型 area 選單；D C15／D15，格式細節沿既有共用規則並標 E |
| 店家 `attractionId` | 選填唯一連結；店家可以只為優惠存在，不要求先上架成景點 | D A15–E15 並未要求每個 merchant 必須是景點；E-26 相容現有關聯 |

優惠詳情保留已發布的未開始／過期資料並顯示狀態；下架、草稿、未到發布時間、刪除則 404。店家停用／刪除不硬刪歷史優惠；建議停止對外公開其優惠，恢復前由 Service 重驗。這項可見性政策為 E-26，須在實作驗收中明示。

### 3.3 工程邊界與安全富文字

沿用已有欄位容量：slug 160、標題／店名／標籤／alt／來源／授權 255、摘要 500、URL 2000；正文／介紹／條款先採 20,000 字且 SQL 容量能容納多位元文字。分頁預設 20、最大 100；批次 JSON 建議 2 MiB／最多 500 筆，超限明確報錯，可拆批。以上是 **E-26 可調整技術設定**，不是客戶規格數字，須同步 DTO、CLI schema、OpenAPI 及 DB。

`body` 預計透過 `RichTextSanitizer` 介面與成熟函式庫 Adapter 處理，候選為 HTML Purifier；不自行用正規表示式移除 script。允許段落、換行、標題 h2–h4、列表、強調與安全連結。正文圖片若提供，必須有 alt 且來源為受控公開媒體／安全外連，並納入該內容的媒體引用與發布授權；不得因 cover 以外的圖片而繞過草稿保護。不允許 script、iframe、表單、事件屬性、任意 style、javascript:／data: 內容。淨化差異在 dry-run／匯入結果回報，淨化後不再次拼入未驗證標記。

已在 Docker PHP 8.5.12 安裝並鎖定穩定 4.19.1，實測及 Composer audit 通過，未依賴 master。圖片內容檢查沿 U-14／既有上傳邊界，不增加病毒掃描服務。

## 4. 預計資料表及升級

規劃開始時為 **24 表、十二份 migration**，最高編號 011；002 有兩份不同名稱，因此下一份應從 **012** 開始。保留已套用 migration，新增三份升級，預計新增四表、擴充一表，完成後為 28 表、十五份 migration；已由 012–014 完成，實際為 28 表／276 欄／十五份 migration。

| 預計檔案／資料表 | 主要欄位與關係 | 依據 |
| --- | --- | --- |
| `012_merchants_profile.sql` → 擴充既有 `merchants` | UUID `id` PK；原 `attraction_id` 改為可空 UNIQUE FK；增加 name、district、address、phone、website、status(active/inactive)、version、updated_at、deleted_at，保留 created_at | D C15／D15；E-26 既有資料相容 |
| `013_news_posts.sql` → 新 `news_posts` | UUID PK、slug UNIQUE、legacy_id 可空 UNIQUE、type enum(event/city)、title、summary、body、cover_media_id FK、cover_url／alt／source／license、event_starts_at／event_ends_at 可空 UTC、event_label／event_location、status、publish_at、seo_title／description、created_by／updated_by FK、version、timestamps、deleted_at | D C14／D14 為主，原型顯示及共用工程欄位另標 E |
| 同上 → 新 `news_aliases` | path ASCII PK、news_post_id FK，保存舊 slug／固定網址 | E-26，重用 attraction_aliases／film_aliases 模式 |
| `014_offers.sql` → 新 `offers` | UUID PK、slug UNIQUE、legacy_id 可空 UNIQUE、merchant_id FK、title、discount_text、description、terms、cover_media_id FK、cover_url／alt／source／license、valid_from／valid_to 可空 DATE、status、publish_at、SEO、created_by／updated_by、version、timestamps、deleted_at | D C16／D16；publish_at 沿現有排程命名，不並存另一個同義 published_at |
| 同上 → 新 `offer_aliases` | path ASCII PK、offer_id FK；允許明列的舊 coffee-walk 路徑，不能任意占用別的 news 路徑 | E-26，舊網址保留，不把優惠塞進 news_posts |

### 4.1 既有 merchants 的相容處理

現有 `merchants` 只有 attraction_id PK／created_at；`AttractionRepository::save()` 會對非景點 INSERT、改成景點時 DELETE。不能只新增 offers FK 就繼續硬刪這些紀錄。

1. 升級為現有每列建立穩定 id，建議沿用其 attraction UUID，保留 attraction_id、created_at；從對應 attraction 一次回填店名、區域、地址、電話、官網。只有當時可公開的來源先標 active，其餘 inactive，避免把草稿資料當公開店家；維運者可明確確認後啟用。升級前先在保留資料副本核對欄位、孤兒參照與筆數。
2. 店家 profile 是優惠模組的明確資料，不在公開查詢時偷偷 JOIN 草稿景點取得未公開聯絡內容。一次回填後由受控店家更新維護；景點改名不靜默改寫優惠店家 profile，文件要說明兩者用途與可選連結（E-26）。
3. 景點寫入改透過 `MerchantRepository`／共用連結流程：新非景點建立尚不存在的 profile，不覆寫已有 profile；草稿來源建立 inactive profile，不因後續景點保存自動啟用優惠店家。改分類時，有優惠引用的 merchant 保留並解除 attraction 連結，不刪歷史優惠。不新增「有優惠就不能改分類」業務阻擋。
4. 擴充景點刪除引用檢查：有未刪優惠引用時按既定 E-17 共用保護處理，回可理解的引用錯誤，不先刪 merchant 或留下孤兒；無引用則可沿既有軟刪流程處理。交易／共同發布鎖處理同時建立優惠與刪景點的競態。
5. 獨立 merchant 無 attraction 也能建立／更新；商家停用／軟刪保留 FK。此階段只提供受控 CLI 所需的店家能力，沒有新店家後台頁。

SQL 限制與 Service 同步：type 白名單、slug 唯一、實際 FK、兩日期都有值才比較（`valid_from IS NULL OR valid_to IS NULL OR valid_to >= valid_from`）；活動區間亦採可空條件。NOT NULL 的空字串預設不代表業務必填，選填日期不可存假日期；title／discount／terms 的明文／原型必填不得被低層寫入繞過。

### 4.2 查詢與索引

公開查詢重用成功發布快照；DB 主要負責建立發布投影及受控更新。不能把 API 在快照內分頁誤寫成每次都查 DB OFFSET。

候選索引是 news `(deleted_at,status,publish_at,id)`、需要分類投影時的 type 前綴版本；offers 同類公開投影索引、merchant_id FK 索引；aliases 的 path PK 與 owner FK；merchants 的 attraction_id UNIQUE。D16 的 `(status,valid_from,valid_to)` 是建議，兩個日期範圍不保證同時有效利用整段索引；只依實際投影、排程及到期查詢的 MariaDB 12.3 EXPLAIN 保留必要組合，不全加。

公開消息以 `publishAt DESC, id ASC` 穩定排序；優惠同樣依發布時間與固定 ID，不用剩餘天數造成無需求的排序變動。目前維持 page／pageSize 以符合原型分頁；若量級需要 cursor，另做契約設計，不把大量 OFFSET 掃描當成已解決。實作驗證大／小樣本並記錄方案；本次不啟用 Performance Schema、不調 DB 或重啟服務。

重用 media_assets、audit_logs、content_publications、身分與權限表，不新增另一套發布、媒體、分類管理或稽核表。匯入以內容 UUID 為可重跑識別，不先增加一張沒有查詢需求的 import_jobs 表。

## 5. 預計新增 API 與頁面

規劃開始時有 54 個 API 操作。本階段已新增 **7 個公開 GET**，合計 61 個，路由與 OpenAPI 均已接入。路徑來自 D／E，功能來源另列。

| 方法／路徑 | 主要輸入／輸出 | 對應頁面／來源 |
| --- | --- | --- |
| GET `/api/v1/home` | 無參數；museum／featured／films／news、generation 與電影週期；不先輸出未建立的 site_settings 假資料 | index；S H5 允許聚合，D API草案 C5–G5；基礎聚合，階段七再接設定 |
| GET `/api/v1/news` | type 可省略或 event／city、q、page、pageSize；items／total／page／pageSize／generation | events／activities／city-news；S H9，D API草案 C10–G10 |
| GET `/api/v1/news/{slug}` | 已發布基本文章、封面／有效 alt、日期、正文、canonical；不包含 neighbors | 基本閱讀頁；E-17 對 D C11–G11 的必要 P0 子集 |
| GET `/api/v1/news/resolve-url` | 受限 path 或舊 id → 站內 canonical；舊 coffee-walk 可查明列優惠 alias | news-detail／舊 slug／舊 coffee-walk；E-26 |
| GET `/api/v1/offers` | active 預設 true、q、district、page、pageSize；優惠／店家摘要、有效狀態與分頁 | offers；S H11，D API草案 C12–G12；pageSize 為共用 E |
| GET `/api/v1/offers/{slug}` | 已發布基本優惠、店家、條款、有效期、active／upcoming／expired 狀態、canonical | 基本閱讀頁；E-17 對 D C13–G13 的必要 P0 子集 |
| GET `/api/v1/offers/resolve-url` | 受限 path 或舊 id → 站內 canonical | offer-detail／舊 slug；E-26 |

`active=false` 明確表示取消「目前有效」過濾，可列已發布的未開始／過期優惠，**仍不能看草稿、排程未到、下架、已刪或停用店家內容**。公開頁預設不新增過期切換控制項；API 的區域／關鍵字能力不等於改版增加表單。q 只搜尋該資源，與 P2 全站搜尋不同。

home 在一次 `ContentPublication::read()` 後組出全部區塊，避免各 Service 再讀不同 generation；不另造首頁資料表。回傳首頁實際選出的至多三筆消息與三部本週電影，景點／博物館沿原有選取規則；消息依上述發布時間排序，可同時含 event／city。沒有資料回空區塊，不用 prototype 補滿。

resolver 在 `{slug}` 路由前註冊；只接受明確站內路徑及固定舊識別，不能外站／任意檔案轉址。可公開舊網址由 Nginx 301，目標不存在／不可公開為 404。無效輸入 422，發布未完成沿既有 `PUBLICATION_PENDING`／503 及 Retry-After；不可把來源故障變成 200 空清單。

既有 API 必要修改不增加操作數：`publication/access` 納入消息／優惠與到期版本；`admin/media` 上傳／列表／access 的 MediaModule 白名單增加 news／offers，按各自 module/action 授權、隔離草稿預覽。只交付共用媒體前置能力，不開管理內容 CRUD。正文中的本地圖片同樣計入目前成功版本的有效引用。

## 6. 受控內容操作：CLI 設計

### 6.1 命令與可重跑方式

預計在既有 `backend/bin/console.php` 註冊，解析與流程移入專責 Adapter／Service，避免 console.php 持續累積大型業務分支。以下命令已實作，操作詳見公開內容指南：

| 預計命令 | 責任 |
| --- | --- |
| `content:import --module=merchants\|news\|offers --file=...` | 預設 dry-run，回逐筆 create／update／unchanged／錯誤，不寫 DB／圖片／發布；加 `--apply` 才套用。各模組 DTO 明確分開 |
| `media:import --module=news\|offers --file=... --alt=...` | 受控本地檔匯入，回 media UUID；共用解碼／重編碼、大小／格式／alt、稽核與清理，不從任意 URL 下載圖片 |
| 既有 `content:publish` | 重試整份目前資料的公開同步；DB 已存但 HTML 失敗時使用，不重跑新增 |
| 既有 `content:tick`／`content:watch` | 排程及每日優惠有效期切換，不新增第二套 publisher |

正式匯入檔放被 Git 忽略的本機位置，不寫進 demo JSON。建議 JSON schema 含 schemaVersion、items；新增列可帶匯入端預先產生的 UUID，沒有則 dry-run 產生並輸出保存用 manifest，apply 使用同一 manifest。更新必須帶 id／version；相同 id、相同正規化內容可回 unchanged，不能因舊版本不相同卻覆寫已有新內容。`slug`／標題／店名不是隱含 upsert key。

未列在批次中的資料保持；不以「這次沒匯入」推論刪除。下架用明確 archived，更新是完整內容 replacement，選填欄位空值與省略語意由各 DTO schema 清楚定義；版本及 publishAt 的保留規則不能被 replacement 混淆。正式內容不允許利用 demo 命令覆寫。

操作範例（已實作）：

```powershell
# 將已準備的 JSON 複製到現有 app，無須把內容掛入公開目錄。
docker compose cp ./artifacts/content-import/news.json app:/tmp/phase-5-news.json
docker compose exec --user www-data app php bin/console.php content:import --module=news --file=/tmp/phase-5-news.json
docker compose exec --user www-data app php bin/console.php content:import --module=news --file=/tmp/phase-5-news.json --apply
# 結果為 saved_publication_pending 時，只重試發布。
docker compose exec --user www-data app php bin/console.php content:publish
```

範例 JSON 假設已含固定 id／更新 version；若原檔未帶 id，先保存 dry-run 回傳的 manifest，再對該 manifest 執行 apply，避免每次生成不同 ID。相同流程先匯入 merchant，再以其 id 匯入 offers；不要用同名查找猜店家。CLI 僅供已能進入 Docker／主機的受信維運者，延用固定 active admin 作維護稽核身分，ClientContext 明列 cli／requestId；這不代表登入了任何人的瀏覽器 session。不得設公開 HTTP import 入口、任意 `--actor` 冒名參數或改權限矩陣。使用者密碼／金鑰不進命令、JSON 或 log。正式操作仍需主機／Docker 存取管理，不能宣稱 CLI 有網頁登入防護。

### 6.2 一致性與錯誤結果

1. 先解碼及檢查未知欄位、格式、必填與淨化變動，回 JSON-pointer／欄位與筆序錯誤，不只印例外類名。dry-run 不會建立媒體或改時間。
2. apply 取得既有發布排他鎖，在同一 DB 交易重驗內容版本、店家／媒體／alias 引用、操作者狀態及規則；同批任一列失敗整批 DB rollback，不能回成功但只存一半。跨模組批次不假裝是一個交易，指南明示先後順序。
3. 成功後回傳新 UUID／version／逐筆結果與批次 requestId，稽核不保存整篇正文或敏感檔案路徑。媒體先獨立受控匯入，後續內容失敗可保留為未引用資產，不宣稱兩個命令跨交易原子。
4. DB 提交後才同步 HTML。結果區分 `validation_failed`、`conflict`、`saved`、`unchanged`、`saved_publication_pending`；約定 exit 0 成功、2 驗證失敗、3 衝突、4 已保存待發布、1 非預期錯誤。未知錯誤保留 requestId，不能回傳 SQL／密碼。
5. 發布失敗不回滾已提交內容，也不能讓維運者誤以為應重新新增；重試 `content:publish` 只重建公開版。`unchanged` 但目前發布尚未 ready 時亦回報待發布，不能誤報完全完成。

示範來源與正式內容分離：原型四篇消息、三則優惠、固定 aliases 另有明確 `content:seed-public-demo` 模式，只建立不存在內容，不覆寫正式資料。消息原型 renderNews 明寫 2026，日期可追溯為 2026-10-10／11／18／20；只到日的發布日期採台北當日 00:00 作 E-26 編碼，不捏造活動開始／結束時間。優惠採 defaultOffers 的完整日期／條款；原始圖片授權仍為示範，正式上線前替換。驗收用有明確時間的隔離 fixture，不修改主庫日期湊出有資料的畫面。

## 7. 分層、重用及預計檔案

```text
公開 HTTP → PublicNews／PublicOffer／Home Controller
         → Query DTO → Public Service → 一次讀成功發布快照 → JSON
受控 CLI → Import Adapter → Write DTO → News／Offer／Merchant Service
         → 共用驗證／Policy → 同連線 Repository＋稽核交易
         → ContentPublication → NodeContentRenderer → 前端 HTML＋snapshot
前端 HTML／JS → 共用 view／分頁／圖片處理 → 公開 GET（需要互動時）
Nginx → 共用 publication gate → 目前 generation 的 HTML／媒體
```

| 預計新增／修改位置 | 責任及模式 |
| --- | --- |
| 新 `Controllers/PublicNewsController.php`、`PublicOfferController.php`、`HomeController.php` | 僅處理 HTTP／DTO／回應；不拼 HTML、不查 SQL |
| 新 `DTO/NewsPost.php`、`NewsInput.php`、`NewsQuery.php`、`Offer.php`、`OfferInput.php`、`OfferQuery.php`、`Merchant.php`、`MerchantInput.php`、匯入結果 DTO；`Models/NewsType.php` | 明確輸入／公開輸出、readonly 值物件／enum；不直接序列化 DB row |
| 新 `Services/PublicNews.php`、`PublicOffers.php`、`PublicHome.php` | 同一快照的公開篩選、穩定排序與首頁選取；已有景點／電影選取規則適度抽共用，不複製兩份不同的規則 |
| 新 `Services/NewsManagement.php`、`OfferManagement.php`、`MerchantManagement.php` | CLI 與未來管理 API 共用的使用案例；保留 Authorization／交易／版本／稽核責任；不把 CLI 寫成繞過 Service 的 SQL seed |
| 新 `Repositories/NewsRepository.php`、`OfferRepository.php`、`MerchantRepository.php` | PDO／資料映射／引用查詢，沿既有 Repository 做法；明確查詢，無通用字串表名 Repo |
| 新 `Contracts/RichTextSanitizer.php` 與 Adapter；日期有效期／封面 Policy | 隔離第三方 HTML 淨化；日期規則由 Clock／共用 Policy 決定，CLI、公開服務與發布器共用 |
| 新受控 Import Adapter、JSON schema／示範資料；修改 `bin/console.php`／`Composition.php` | 命令解析與注入；輸入 schemaVersion、結果／exit contract；只有這層知道檔案輸入 |
| 修改 `Services/ContentPublication.php`、`Adapters/NodeContentRenderer.php`、媒體模組／access、景點店家連結 | 共同投影及 generation、模板版本、媒體安全、引用保護；不新建另一套發布協調 |
| 新 `frontend/assets/js/core/news-view.js`、`offer-view.js`、公開 API 包裝及 `pages/news.js`／`offers.js` | SSR／瀏覽器相同卡片標記、日期、alt／外連及分頁；沿 `film-view.js` 模式，避免複製卡片到多處 |
| 修改 `frontend/scripts/publish.mjs`、相關 pages、`public.js`／`pages/public-details.js`／`components/content-cards.js` | 原型版型接入；發布時生成首頁／各分類首屏／基本詳情／head；隔離 prototype 初始化 |
| 修改 `docker/nginx/default.conf`、必要 build 檔 | 新受管 HTML 路徑及 aliases 解析；沿既有容器，不增加 DB／快取／CMS 服務 |
| 新功能測試與 `docs/modules/public-content.md`、`PHASE-5-ACCEPTANCE.md`；更新契約／對照／導讀 | 中文說明實際新增檔案、資料流、命令、限制與真實驗收結果；現在不先建立假驗收紀錄 |

模式依既有 Service Layer／Repository／DTO／DI／Adapter；不引入 Spring Boot、Laravel、ORM、事件匯流排或為兩個模組建立過度泛化 CMS。Service／Policy 共用真正相同的規則，消息分類與優惠期限仍各自清楚。

## 8. 發布、日期切換及畫面行為

- news、offers、可公開 merchant 摘要、aliases、媒體引用與既有景點／電影一起計算 generation。CLI 寫入後只在整份 HTML／snapshot 完成時原子切換 current。
- 優惠的 active 狀態包含台北日期；投影須把當日判斷／必要邊界納入 generation，否則沒人編輯也會跨日仍顯示過期優惠。由注入 Clock 取得日期，發布器沿目前十秒輪詢偵測；API／Nginx gate 在跨日新版本未完成時返回待更新，不能繼續公開錯誤有效清單。不得直接把每秒 now 加入 generation 導致永遠重建。
- 下架／刪除／改 slug／停用店家及無資料時，同步列表、首頁、詳情、舊網址、公開媒體。`events`／`activities`／`city-news`／`offers`、`news/*.html`、`offers/*.html` 都納入 Nginx gate，不能找不到後退回 dist 的原型文章。
- 第一屏 HTML 已含真實內容與 head；分頁／篩選互動讀 API，URL 保留分類／頁碼，可重整、返回及貼給另一瀏覽器。預計使用 `/events.html?type=event&page=2` 等既有頁名＋query；activities／city-news 固定分類，offers 預設 active=true。renderer 依實際筆數產生各分類每一頁，Nginx 以白名單 type 與正整數 page 映射到 current 內的分頁產物；產物目錄只供內部存取，仍走 publication gate，不能直接拼任意 path。無效分類／不存在頁次有明確錯誤，不回第一頁冒充第二頁。
- 無 JS 也能讀到對應分頁／詳情，選單以 GET 表單可送出、分頁為真實連結，原型選單外觀不改。每頁 head／canonical 使用正規化分類與頁碼，page=1／空 type 收斂同一網址；不另外手工維護一套文章。UI 固定 pageSize=20；API 可調 pageSize 不代表任意 query 都能生成公開 HTML。
- 翻頁只接受當前頁次請求結果，防慢回應蓋掉新分類；載入失敗可重試，維持已選分類與焦點，不顯示「沒有內容」掩蓋服務錯誤。API 分頁與 SSR 使用同一排序、頁大小及 generation；版本切換後重新載入可理解。
- 封面缺省／載入失敗沿既有替代處理，不拿別筆景點圖片假裝本文照片。詳情頁維持原型主內容寬度、封面、標題／lead、meta、正文及按鈕；日期空值顯示依公告或省略，沒有假 1970／今天。
- S E30 的新頁唯一 head／canonical／OG／Twitter 由 renderer 輸出，安全標題、摘要與圖片取同內容；全站設定優先序、robots 管理與 sitemap／JSON-LD 仍分別在後續階段。

## 9. 實作順序與功能提交

本文件先完成規劃，再依使用者授權建立階段五分支。以下 5A–5E 已按完整功能實作與驗證，不按副檔名拆散：

| 次序 | 完整提交單位 | 隨提交驗證／文件 |
| --- | --- | --- |
| 5A 店家相容與內容共用前置 | 三份 migration、DTO／Repository、正文／日期共用規則、景點連結／引用保護及隔離資料層證據 | 空庫與保留資料升級、FK／可空日期／版本／分類變更／刪除競態；DB 對照／導讀 |
| 5B 公開消息與受控維護 | 消息 CLI／Service、三個 GET、原型分類／推薦／全篇分頁／基本閱讀頁／aliases、共用發布／媒體 | 安全正文、分類／日期／排程／越權／CLI 重跑與失敗、桌面手機；OpenAPI／模組指南 |
| 5C 獨立優惠與店家維護 | 店家／優惠 CLI、三個 GET、原型優惠卡與基本閱讀頁、有效期／過期提示／legacy aliases、媒體隔離 | 條款／店家引用、同日起迄／空日期／跨日、停用／恢復、同步失敗；契約／指南 |
| 5D 首頁同版整合 | home GET、原型消息三列與已串接區塊共用選取、無資料／圖片／錯誤、跨模組日期切換 | HTML／API／第二瀏覽器一致，電影跨週與優惠跨日，不混假資料；首頁指南 |
| 5E 整合驗收與交付 | 端到端／故障／安全／UI 回歸、持久化、操作說明及 PROJECT-GUIDE 實際清單 | 產出 PHASE-5-ACCEPTANCE，未執行不能填通過；完整驗收後備份／merge --no-ff，保留分支 |

開發時發現的自身錯誤歸回對應功能提交，不另留一串修正進度。每個功能提交一起包含相關前後端、契約、測試及閱讀說明；新增第三方套件的 manifest／lock／環境與用途一起交付。

## 10. 驗收清單與完成條件

| 類別 | 必須驗證的情境 |
| --- | --- |
| 規格／欄位 | 每個欄位對照本表及 Excel 儲存格；條款必填；摘要／正文／圖片／日期不被誤加必填；未知欄位有結果；不把 D 當硬性來源 |
| CLI／DB | dry-run 零寫入；同批任一失敗全 rollback；重跑 unchanged 不多建；更新 version 衝突；merchant／media／alias 有 FK／模組保護；示範不覆寫正式內容；既有資料升級保留 |
| 日期 | validFrom=validTo 當天有效；前一天／起日／截止日 23:59:59／隔日 00:00；空一端／兩端、反向區間、無效日期、不同 offset、未來發布與到點發布；活動結束文章仍可在 archive 讀取 |
| 公開 API | 七個新操作的成功／無資料／錯誤契約、排序、分頁全部可到；events／city 與 offers 完全分流；active=false 仍無草稿；無權看內容的狀態不透過 resolver／media 洩漏 |
| 發布故障 | renderer／DB 發布紀錄寫入故障、已保存待同步、重試、下架後舊 generation、別名、跨日／換週；不能 DB 已存卻誤導重複新增，不能回退原型 HTML |
| 正文／圖片安全 | XSS、編碼／屬性／URL、script 結尾注入、錯誤 HTML、正文圖片 alt／媒體引用、跨模組草稿、路徑穿越／open redirect、CLI 檔案邊界；真實 MariaDB 與 HTTP 的越權／失敗驗證 |
| 原型介面 | 用固定真實 fixture 與 website 並排比首頁、events 的推薦＋全篇、兩分類、優惠卡與兩類詳情；1440 桌面／390×844 手機、字體／間距／封面／按鈕、鍵盤焦點／縮放／空資料／重試／圖片失敗 |
| 真實資料流 | 匯入→發布→第二個瀏覽器讀取→更新→排程→到期／下架→列表／首頁／詳情一致；無 JS 原始 HTML 可讀、深分頁重整／直接網址／新分頁可開、無斷鏈 |
| 回歸／持久化 | 階段二認證／唯讀模擬／媒體授權、階段三景點／地圖／刪除、階段四電影／海報／場次；隔離庫與測試 volume 重建後資料／圖片／HTML 可恢復，不清主庫或改主庫帳號 |

PHP／Composer／PHPUnit／PHPStan／PHPat／格式檢查／Rector dry-run 在 Docker 執行；Node 針對真正共用 view／互動做必要測試，契約作獨立 HTTP 驗證。實作時依流程套用 php-modernization、security-audit、mariadb-query-optimization、web-design-guidelines，瀏覽器驗收搭配 browser skill；規劃提交時未執行功能測試；實作後的真實結果另見階段五驗收。

宣告完成前同步更新 OpenAPI、DATABASE-SPECIFICATION-MAP、DESIGN-TRACEABILITY、IMPLEMENTATION-PLAN、PROJECT-GUIDE、中文模組指南與實際驗收文件，核對檔案／連結／表數／API 數。階段五完成後仍未完成 P0；須完成六、七及八的界線驗收。

## 11. 設計參考與待確認項目

- [Fowler Service Layer](https://martinfowler.com/eaaCatalog/serviceLayer.html)：CLI 與未來管理 HTTP 共用交易及業務操作；這是本案套用方式，不是照搬框架。
- [Fowler Repository](https://martinfowler.com/eaaCatalog/repository.html)：集中資料映射／查詢，避免 Controller、CLI、發布器各寫一套 SQL。
- [OWASP XSS Prevention](https://cheatsheetseries.owasp.org/cheatsheets/Cross_Site_Scripting_Prevention_Cheat_Sheet.html)：純文字按 context 編碼；需要保留 HTML 的正文另做淨化，CSP 不替代這些處理。
- [HTML Purifier 官方 composer](https://github.com/ezyang/htmlpurifier/blob/master/composer.json)：截至本次查閱，master 宣告支援 PHP 8.5；實作已鎖定穩定 4.19.1，Docker PHP 8.5.12 相容性及依賴安全檢查通過。
- [MariaDB 分頁](https://mariadb.com/docs/server/ha-and-performance/optimization-and-tuning/query-optimizations/pagination-optimization)、[索引設計](https://mariadb.com/docs/server/ha-and-performance/optimization-and-tuning/optimization-and-indexes/building-the-best-index-for-a-given-select)：以本專案 12.3 與實際查詢驗證；公開快照分頁不冒充 DB 索引效能。

尚待正式上線內容／環境提供：實際消息正文、活動時間／地點、合作店家與優惠條款、可使用的圖片與正式網域。這些不阻擋隔離開發，但不能以示範資料代替正式上線驗收。

本計畫的空日期開放邊界、店家停用可見性、profile 與景點分工、coffee-walk 單一相容 alias、排序／上限均標 E-26，屬可調整設計，沒有偽稱 Excel 已明定。若實作時發現新的、會影響業務的來源衝突，先列出具體差異請使用者決定；不能根據現有測試反推規格。
