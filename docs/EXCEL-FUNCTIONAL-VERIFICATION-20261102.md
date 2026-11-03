# 原始 Excel 全網站功能逐列核實（2026-11-02）

本輪重新讀取原始工作簿全部7張工作表，以「功能規格」E5:L34共30列的E功能、G限制、L驗收逐列核實。**30列的本機功能沒有留下已確認而未修復的失敗；正式平台／HTTPS條件仍未簽核，不能解讀為全案正式上線通過。** 部分完整AA、原型尺寸及既有整合沿用明列的有效基線，不冒稱所有情境都在本輪重新執行。

工作簿SHA256：`d445ae7f8478b10fc938f91d9bcf85802dc48bcc3487dd01664ae06cfbaeeef5`。原始website／工作簿69檔保持。優先級為P0 16列、P1 11列、P2 3列；API草案及資料表建議保持草案，不自行推定欄位必填。

## 本輪實跑與修正

- 自有 `yinghua_test` 資料：150 個不同案例最終通過，另四區塊空快照實際產頁、真Browser操作及故障／恢復。逐例來源與結果見[機讀證據](evidence/EXCEL-FUNCTIONAL-20261102.json)。這個數字不含沿用基線，也不把重試次數當新案例。
- 前端Docker全套278／278；PHP122／409斷言，PHPStan／PHPat零錯誤。新增十個刪除成功／取消行為回歸與一個首頁分區故障回歸。68固定頁＋25資產共93筆HTTP檢查零問題。
- 最後唯讀核對MariaDB34表338欄、20個migration與journal校驗碼一致；原始69檔未變，1533個文件內部路徑均存在。沒有把此路徑盤點當外部平台／所有anchor驗證。
- 修復：景點／電影／消息／優惠／管理員刪除重載後焦點落BODY；五類Browser實測焦點分別回place-list-status／film-list-status／content-list-status／page-status。
- 修復：首頁發布不可用原僅回整頁503純文字；現在同樣維持503、不供舊資料，四模組有各自錯誤提示及重試。回應強制noindex、沒有舊資料／JSON-LD，1440／390／320無橫向溢出。內部回應資產不能從公開URL讀取。
- 一致性修正：首頁消息入口與消息列表同樣另開分頁，含預告名稱及noopener noreferrer。沒有改原始website或正常首頁排版。
- 使用者本輪另明確同意套用localhost:8080重新發布與Nginx重載；兩站共10個關鍵頁資產檢查通過。先前主站503為新版程式尚未重新產生同版公開檔，重新發布後恢復；不把它混算成驗證碼或CRUD缺陷。

證據目錄：`artifacts/excel-functional-verification-20261102/`。原始讀表、逐步state／HTTP、故障前後、Browser、清理與品質log均保留。初稿測試把模組專用圖片混用、409／403／201／204誤當422／200、查錯public envelope／neighbors端點等錯誤均保留在首次log；是測試假設錯誤，未修改產品去迎合。Windows直接執行舊發布測試的file URL路徑失敗，標準Docker同套278通過；沒有把未執行當通過。

## 逐列：要求、實際結果與界線

### 5. 首頁（P0）—本機通過

來源：功能規格 E5／G5／L5；頁面：`index.html`。

- 功能 E5：顯示影像博物館主視覺、精選景點、最新片單與消息；資料應由各模組公開 API 取回。
- 限制 G5：僅回傳 published；各區塊有空狀態與錯誤狀態
- 驗收 L5：首頁精選資料與後台狀態同步；圖片載入失敗有替代圖。

首頁與公開 API 同版、草稿不公開。真實移開自有圖片後 Browser 顯示替代圖；空快照實際產頁，四區塊皆有空狀態。不同版故障原只顯示整頁文字，已改為四區塊錯誤／重試、503與noindex；1440／390／320實測，恢復後按重試正常。

本輪案例：`PLACE-DRAFT-HIDDEN`、`HOME-SYNCHRONIZED`、`HOME-NO-MIXED-GENERATION`、`HOME-BLOCK-ERROR-STATES`、`INTERNAL-ERROR-ASSET-NOT-PUBLIC`。

### 6. 館舍介紹（P0）—本機通過

來源：功能規格 E6／G6／L6；頁面：`museum.html`。

- 功能 E6：呈現館舍簡介、地址、開放時間、交通與外部連結。
- 限制 G6：電話／網址格式；資訊標示最後更新日
- 驗收 L6：手機與桌面讀取一致，後台更新後頁面資料刷新。

建立獨立館舍，核對介紹／地址／時間／交通／電話／官網／最後更新；更新後 API、HTML與Browser顯示新內容。1440與390主內容相同且不水平溢出。原館舍指定已還原。

本輪案例：`PLACE-COORDINATE-URL-VALIDATION`、`MUSEUM-FIELDS`、`MUSEUM-UPDATE-SYNCHRONIZED`。

### 7. 本週片單（P0）—本機通過

來源：功能規格 E7／G7／L7；頁面：`cinema.html`。

- 功能 E7：依日期／場次展示電影照片、片名、類型及詳情連結。
- 限制 G7：場次時間有效；海報 alt 必填；未發布不公開
- 驗收 L7：本週片單可看海報；海報與場次資料更新後一致。

草稿電影404；發布兩筆當週場次後，片單有海報alt與場次。無效24:00及同日期開始時間重複被拒絕；刪除一場後，公開API／HTML同步只剩一場。

本輪案例：`MEDIA-ALT-MIME`、`FILM-DRAFT-HIDDEN`、`FILM-VALID-TIMES-OVERLAP`、`FILM-WEEK-POSTER-SCREENINGS`、`SCREENING-REMOVE-SYNCHRONIZED`。

### 8. 電影詳情（P1）—本機＋有效基線通過

來源：功能規格 E8／G8／L8；頁面：`films/{slug}.html、film-detail.html`。

- 功能 E8：每部電影獨立 HTML 路徑；顯示完整介紹、海報、場次及上一篇／下一篇縮圖標題連結。
- 限制 G8：slug 唯一；上下篇依發布排序；無相鄰文章時隱藏對應連結
- 驗收 L8：直接開啟網址可呈現正確電影，無燈箱；上下篇可鍵盤操作。

兩部電影獨立slug／HTML可直連，完整介紹、海報與場次可讀；獨立neighbors API提供標題與URL，產頁與既有P1基線檢查縮圖、鍵盤及無燈箱。

本輪案例：`FILM-DRAFT-HIDDEN`、`FILMS-DIRECT-DETAIL`、`FILMS-NEIGHBOR-STRUCTURE`。

### 9. 活動／消息列表（P0）—本機通過

來源：功能規格 E9／G9／L9；頁面：`events.html／activities.html／city-news.html`。

- 功能 E9：活動、城市消息分頁／篩選；列表頁下半部顯示全部文章；優惠不混入消息。
- 限制 G9：分類白名單；日期區間正確；只公開發布內容
- 驗收 L9：可列全篇、分類正確、無優惠混入，空清單可理解。

活動與城市消息、草稿與2099排程分開；分類／q／每頁1筆／第2頁實際結果不同，草稿與未到期排程不公開。Browser切分類後只顯示活動，底部完整文章列表保留。

本輪案例：`NEWS-TYPE-AND-DATES`、`NEWS-DRAFT-FUTURE-EXCLUDED`、`NEWS-CATEGORY-PAGINATION`、`NEWS-OFFER-SEPARATION`。

### 10. 消息詳情（P1）—本機通過

來源：功能規格 E10／G10／L10；頁面：`news/{slug}.html、news-detail.html`。

- 功能 E10：獨立 HTML 文章頁，顯示正文、日期、封面與前後篇縮圖標題。
- 限制 G10：正文允許安全富文字；圖片替代文字；發布狀態檢查
- 驗收 L10：新分頁開啟；相鄰文章按同一分類排序。

活動一／二與另一城市消息建立分組，正文onclick及script被清理。Browser確實另開文章分頁，縮圖與命名連結存在，Enter開同分類下一篇。首頁消息入口亦統一另開分頁並說明。

本輪案例：`NEWS-XSS-SANITIZED`、`NEWS-DIRECT-DETAIL`、`NEWS-NEIGHBOR-STRUCTURE`。

### 11. 優惠列表（P0）—本機通過

來源：功能規格 E11／G11／L11；頁面：`offers.html`。

- 功能 E11：優惠依有效期與狀態呈現，連結至獨立優惠詳情頁。
- 限制 G11：validTo >= validFrom；過期與草稿不出現在有效優惠預設列表
- 驗收 L11：優惠管理的內容不會出現在活動消息頁。

有效、過期及草稿優惠同時建立，預設公開列表只含兩筆有效優惠；消息與優惠ID集合不混用。有效日期反轉被拒絕，空日期保持原規則。

本輪案例：`OFFER-TERM-DATE-MERCHANT`、`OFFER-ACTIVE-ONLY`、`NEWS-OFFER-SEPARATION`。

### 12. 優惠詳情（P1）—本機通過

來源：功能規格 E12／G12／L12；頁面：`offers/{slug}.html、offer-detail.html`。

- 功能 E12：獨立詳情頁顯示使用方式、店家、期間、條款、縮圖前後篇。
- 限制 G12：有效期、條款、使用限制清楚可見
- 驗收 L12：網址可直接開啟，過期狀態明確標示。

優惠直接HTML與API可讀，過期優惠詳情明確標示過期／結束，列表仍隱藏過期項目；相鄰連結只提供有效資料。商家關聯與條款驗證有真DB證據。

本輪案例：`OFFER-EXPIRED-DETAIL`、`OFFERS-DIRECT-DETAIL`、`OFFERS-NEIGHBOR-STRUCTURE`。

### 13. 景點／美食（P0）—本機通過

來源：功能規格 E13／G13／L13；頁面：`explore.html、food.html、places/{slug}.html`。

- 功能 E13：分類、區域、關鍵字篩選；可加入行程、收藏；各景點獨立詳情頁。
- 限制 G13：必要欄位、唯一 slug、座標範圍、公開狀態
- 驗收 L13：管理新增景點後依發布狀態顯示；店家類別與一般景點區分。

建立景點／美食並依名稱、分類、東區查詢；草稿404、重複slug衝突、錯誤座標及javascript網址被拒絕。收藏／加入行程後重登仍存在；在地店家與美食分類沿用已驗證公開模組。

本輪案例：`PLACE-DRAFT-HIDDEN`、`PLACE-SLUG-UNIQUE`、`PLACE-COORDINATE-URL-VALIDATION`、`PLACE-CATEGORY-DISTRICT-SEARCH`、`ATTRACTIONS-DIRECT-DETAIL`、`ATTRACTIONS-NEIGHBOR-STRUCTURE`、`MEMBER-PERSISTENCE-AFTER-RELOGIN`。

### 14. 推薦行程（P1）—本機＋有效基線通過

來源：功能規格 E14／G14／L14；頁面：`routes.html`。

- 功能 E14：顯示可套用的路線主題與包含景點。
- 限制 G14：路線至少一個有效景點；不能帶入下架景點
- 驗收 L14：後台更改路線順序後前台相同。

兩條主題路線依sortOrder公開排序，包含兩個已發布景點；空路線、重複站點及草稿站點被拒絕，套用路線成功。完整原型互動及編輯／排序沿用階段十／十一與本日P1有效基線。

本輪案例：`ROUTE-PUBLIC-SORT`、`ROUTE-APPLY`。

### 15. 會員旅程（P1）—本機＋有效基線通過

來源：功能規格 E15／G15／L15；頁面：`planner.html`。

- 功能 E15：加入／移除／排序景點、時間安排、套用推薦路線、填旅行名稱日期、保存及載入。
- 限制 G15：登入必須；只可讀寫本人資料；禁止重複項目或已刪景點
- 驗收 L15：重新登入後可從伺服器載回，異動僅影響本人。

以兩位會員真實登入，命名／日期／站點順序／10:30／備註保存；另一會員GET／PUT皆404，重複地點422。套用推薦路線後重登載回，內容及順序不變。完整刪除／目前行程相容沿用P1及上一輪同日重驗。

本輪案例：`TRIP-ORDER-TIME-NOTE`、`TRIP-OWNER-ISOLATION`、`TRIP-DUPLICATE-DISALLOWED`、`ROUTE-APPLY`、`MEMBER-PERSISTENCE-AFTER-RELOGIN`。

### 16. 會員註冊／驗證碼（P0）—本機通過

來源：功能規格 E16／G16／L16；頁面：`register.html`。

- 功能 E16：輸入帳號（email）、名稱、密碼、圖形驗證碼，建立會員。
- 限制 G16：Email 唯一且格式正確；密碼政策；驗證碼伺服端限時一次性；同意條款
- 驗收 L16：重複 Email、過期／錯誤碼、速率過快皆由服務端拒絕並提供欄位錯誤。

正確註冊201落DB及兩筆同意紀錄；錯碼、過期、已用、重複Email、跨session／跨Email、未同意條款與確認密碼缺少／不一致皆拒絕。PNG只提供圖像不洩答案，驗證信限率429；本輪正向註冊以隔離API執行，未代操作Browser CAPTCHA或接受條款。

本輪案例：`REGISTER-WRONG-CAPTCHA-FIELD-ERROR`、`REGISTER-TERMS-REQUIRED`、`REGISTER-PERSISTED`、`REGISTER-ONCE-LOGOUT`、`REGISTER-EXPIRED-CAPTCHA`、`REGISTER-DUPLICATE-EMAIL`、`CAPTCHA-SESSION-BOUND`、`CAPTCHA-EMAIL-BOUND`、`REGISTER-CONFIRM-MISSING`、`REGISTER-CONFIRM-MISMATCH`、`CAPTCHA-EMAIL-RATE`、`CAPTCHA-IMAGE-NO-ANSWER`。

### 17. 會員登入／登出（P0）—本機通過

來源：功能規格 E17／G17／L17；頁面：`login.html`。

- 功能 E17：安全登入後建立 session；登出清除 session。
- 限制 G17：限速、鎖定、CSRF、session rotation；不得回傳密碼
- 驗收 L17：無效密碼不洩漏帳號是否存在；登出後受保護 API 回 401。

登入前後session token改變，回應無密碼／hash；不存在與錯密碼同訊息，五次失敗後429且暫時拒絕正確密碼。登出後舊cookie401，缺CSRF／跨Origin403。Secure實際正式HTTPS仍待server。

本輪案例：`REGISTER-ONCE-LOGOUT`、`LOGIN-NONENUMERATION`、`LOGIN-ROTATION-NO-PASSWORD`、`LOGOUT-OLD-SESSION-401`、`SETTINGS-CSRF`、`ANONYMOUS-MANAGEMENT-member`、`ANONYMOUS-MANAGEMENT-admin`、`LOGIN-CSRF-ORIGIN`、`LOGIN-RATE-LOCK`。

### 18. 全站搜尋（P2）—本機＋有效基線通過

來源：功能規格 E18／G18／L18；頁面：`search.html`。

- 功能 E18：查電影、消息、景點、美食、優惠；結果依類型分組並有直接詳情連結。
- 限制 G18：q 長度上限、特殊字元安全、分頁
- 驗收 L18：搜尋結果類型與詳情 URL 一致，無 XSS。

景點、美食、電影、消息、優惠五類各查到自有公開ID與直連HTML；分頁1／2不同，特殊SQL字元按字面，XSS字串不出現結果，超長查詢422。分組、空／重試UI沿用階段十三及上一輪有效Browser。

本輪案例：`SEARCH-PLACE`、`SEARCH-FOOD`、`SEARCH-FILM`、`SEARCH-NEWS`、`SEARCH-OFFER`、`SEARCH-LITERAL-XSS`、`SEARCH-LENGTH`、`SEARCH-PAGING-SPECIAL`。

### 19. 景點列表／刪除（P0）—本機通過

來源：功能規格 E19／G19／L19；頁面：`admin/places.html`。

- 功能 E19：列表、搜尋、狀態篩選；編輯連獨立頁；刪除只跳通知／確認，不另建刪除頁。
- 限制 G19：刪除需權限及關聯檢查；以 soft delete 優先；審計記錄
- 驗收 L19：新增資料出現在清單；刪除被關聯路線時有安全處理。

獨立新增／編輯頁，列表與搜尋可用；Browser先取消保留、再確認刪除，沒有刪除獨立頁。引用中的館舍刪除409；軟刪除、稽核、同步與刪除後焦點都已核實。

本輪案例：`PLACE-DRAFT-HIDDEN`、`PLACE-REFERENCE-DELETE-SAFE`、`AUDIT-RECORDED`、`BROWSER-TEMPLATE-UPLOAD-SAVE-DELETE`。

### 20. 景點新增／編輯／地圖／媒體（P0）—本機通過

來源：功能規格 E20／G20／L20；頁面：`admin/place-add.html、place-edit.html`。

- 功能 E20：新增可選既有模擬景點試填；貼 Google Maps 短網址後預填，使用者確認再儲存。
- 限制 G20：URL 必須 HTTPS 且允許 Google 網域；服務端展開且防 SSRF；欄位仍可人工修正；唯一 slug
- 驗收 L20：有效地圖網址預填；無法解析時顯示錯誤、不自動儲存；非法網域拒絕。

原生圖片選擇器真上傳320×240圖片，alt必填／SVG拒絕；帶入範例需確認，可手改名稱及slug後另存草稿。真Google回1候選且含Place ID，不自動儲存，不記錄簡介原文；HTTP、私網、偽Google網域及userinfo網址422。短網址redirect／SSRF由本輪PHP測試與既有真整合基線驗證，未冒稱任意外部短網址都可成功。

本輪案例：`MEDIA-ALT-MIME`、`PLACE-DRAFT-HIDDEN`、`PLACE-SLUG-UNIQUE`、`MAP-UNSAFE-0`、`MAP-UNSAFE-1`、`MAP-UNSAFE-2`、`MAP-UNSAFE-3`、`MAP-UNSAFE-4`、`MAP-NO-AUTOSAVE`、`MAP-LIVE-PREVIEW`、`BROWSER-TEMPLATE-UPLOAD-SAVE-DELETE`。

### 21. 電影／場次管理（P0）—本機通過

來源：功能規格 E21／G21／L21；頁面：`admin/films.html、film-add.html、film-edit.html`。

- 功能 E21：新增、編輯、刪除場次；前台片單與詳情讀同一電影資料。
- 限制 G21：海報需 alt；場次時間格式與重疊檢核；slug 唯一
- 驗收 L21：儲存後前台清單／詳情照片與日期同步，草稿不公開。

電影新增草稿→發布→編輯→下架→刪除全流程；公開片單與海報／場次同步。場次時間與重複開始驗證依U-16，不額外要求場地或結束時間。Browser刪電影焦點返回film-list-status。

本輪案例：`MEDIA-ALT-MIME`、`FILM-DRAFT-HIDDEN`、`FILM-VALID-TIMES-OVERLAP`、`FILM-WEEK-POSTER-SCREENINGS`、`films-EDIT-PUBLISH-f0807813-0a8d-42c2-adb3-0136ab895330`、`films-UNPUBLISH-f0807813-0a8d-42c2-adb3-0136ab895330`、`films-DELETE-f0807813-0a8d-42c2-adb3-0136ab895330`、`SCREENING-REMOVE-SYNCHRONIZED`。

### 22. 消息管理（P1）—本機通過

來源：功能規格 E22／G22／L22；頁面：`admin/news.html、news-add.html、news-edit.html`。

- 功能 E22：新增／編輯／刪除、草稿／發布／排程；與優惠資料獨立。
- 限制 G22：type 只允許 event/city；排程時間有效；正文安全處理
- 驗收 L22：發布消息出現在對應分類列表；優惠不會混入。

建立活動／城市／草稿／排程；錯分類與反向活動區間拒絕，不安全正文清理。Browser編輯摘要保存，DB再核對內容；另跑發布、編輯、下架404、刪除404。消息列表刪除焦點修復及實測。

本輪案例：`NEWS-TYPE-AND-DATES`、`NEWS-XSS-SANITIZED`、`NEWS-DRAFT-FUTURE-EXCLUDED`、`NEWS-OFFER-SEPARATION`、`news-EDIT-PUBLISH-9663ec93-60af-4230-a9d2-37614b3b242a`、`news-UNPUBLISH-9663ec93-60af-4230-a9d2-37614b3b242a`、`news-DELETE-9663ec93-60af-4230-a9d2-37614b3b242a`、`news-EDIT-PUBLISH-9bbcb692-cc6b-477f-902f-9c80bbaeb676`、`news-UNPUBLISH-9bbcb692-cc6b-477f-902f-9c80bbaeb676`、`news-DELETE-9bbcb692-cc6b-477f-902f-9c80bbaeb676`、`BROWSER-NEWS-SAVE-PERSISTED`。

### 23. 優惠管理（P1）—本機通過

來源：功能規格 E23／G23／L23；頁面：`admin/offers.html、offer-add.html、offer-edit.html`。

- 功能 E23：維護店家、優惠內容、期間、規則、圖片與上架狀態。
- 限制 G23：期間合理、條款必填、關聯店家存在
- 驗收 L23：新增優惠只更新優惠列表與詳情，不進消息列表。

建立合作商家及優惠，空條款、無效商家、反向日期皆拒絕，前台優惠與消息獨立；編輯／下架／刪除同步。Browser優惠刪除焦點修復及實測。

本輪案例：`OFFER-TERM-DATE-MERCHANT`、`OFFER-ACTIVE-ONLY`、`NEWS-OFFER-SEPARATION`、`offers-EDIT-PUBLISH-3a6a252b-361c-4ee4-ae73-186456001e24`、`offers-UNPUBLISH-3a6a252b-361c-4ee4-ae73-186456001e24`、`offers-DELETE-3a6a252b-361c-4ee4-ae73-186456001e24`。

### 24. 推薦路線管理（P1）—本機＋有效基線通過

來源：功能規格 E24／G24／L24；頁面：`admin/routes.html、route-add.html、route-edit.html`。

- 功能 E24：選擇景點與排序，維護主題、天數、說明、發布狀態。
- 限制 G24：至少一站；每個 attraction 必須存在且可用；順序不得重複
- 驗收 L24：保存後前台推薦行程與會員一鍵套用同步更新。

兩條路線與兩個站點可建立、排序、公開及會員套用；空路線／重複站點／草稿站點不可存。完整更新／移除／刪除及越權沿用P1契約181回應與路線58項有效基線，不因本輪已列入而冒稱全部重新跑。

本輪案例：`ROUTE-EMPTY-DUPLICATE-UNAVAILABLE`、`ROUTE-PUBLIC-SORT`、`ROUTE-APPLY`。

### 25. 會員目錄（P2）—本機通過

來源：功能規格 E25／G25／L25；頁面：`admin/members.html`。

- 功能 E25：分頁搜尋會員；依最小權限只展示必要資料。
- 限制 G25：個資欄位遮罩；匯出另行授權並稽核
- 驗收 L25：無權限無法呼叫 API；管理列表不含密碼或驗證資料。

新增21筆無法登入的自有假目錄資料，實際20筆／次頁與總數正確且不重複；Email及名稱遮罩，只出五個最小欄位。撤權即403；沒有匯出入口或可繞權的匯出API，／export404，不把條件式匯出要求當成必須新增匯出。

本輪案例：`MEMBERS-MASK-MINIMAL`、`MEMBER-PAGING-SEARCH`、`MEMBER-DIRECTORY-NO-EXPORT`、`EACH-REQUEST-REVOKED-RIGHTS`。

### 26. 管理員管理（P0）—本機通過

來源：功能規格 E26／G26／L26；頁面：`admin/admins.html、admin-add.html、admin-edit.html`。

- 功能 E26：管理非固定管理員帳號；刪除採通知／確認，不需要刪除專頁；root admin 永遠鎖定。
- 限制 G26：admin account 保留字且不可變更；至少保留一位 root；不可自我移除最後管理權
- 驗收 L26：UI 不顯示 root 編輯刪除；服務端對 PUT/DELETE root 回 403。

非root管理員新增、修改、軟刪除後登入失效；固定admin完整合法PUT／DELETE皆403，保留帳號不可另建。Browser固定root列無編輯／刪除，root權限不可保存。最後管理權、root資料庫保護沿用既有真DB基線。

本輪案例：`ADMIN-EDIT`、`ROOT-SERVER-IMMUTABLE`、`ADMIN-RESERVED-ACCOUNT`、`ADMIN-SOFT-DELETE-REVOKES`、`AUDIT-RECORDED`、`ROOT-PERMISSIONS-IMMUTABLE`。

### 27. 權限矩陣（P0）—本機通過

來源：功能規格 E27／G27／L27；頁面：`admin/permissions.html`。

- 功能 E27：逐管理員、逐模組設定 view/create/edit/delete；角色範本可複製起始值。
- 限制 G27：root 權限不可降權；不能授出操作者自身沒有的權限（可選策略）；寫入稽核
- 驗收 L27：刷新後權限持續；所有後端端點同步驗權。

細部矩陣保存後重新讀回相同；僅景點view可讀景點、不可新增或看電影。逐請求撤權後會員／設定／電影403，dashboard不洩漏未授權數量。原始權限矩陣30個非root角色／模組列逐列與API範本相同。

本輪案例：`PERMISSION-SERVER-ENFORCED`、`PERMISSION-PERSISTED`、`AUDIT-RECORDED`、`ROLE-TEMPLATES-AVAILABLE`、`ROLE-MATRIX-6`、`ROLE-MATRIX-7`、`ROLE-MATRIX-8`、`ROLE-MATRIX-10`、`ROLE-MATRIX-11`、`ROLE-MATRIX-12`、`ROLE-MATRIX-14`、`ROLE-MATRIX-15`、`ROLE-MATRIX-16`、`ROLE-MATRIX-18`、`ROLE-MATRIX-19`、`ROLE-MATRIX-20`、`ROLE-MATRIX-22`、`ROLE-MATRIX-23`、`ROLE-MATRIX-24`、`ROLE-MATRIX-26`、`ROLE-MATRIX-27`、`ROLE-MATRIX-28`、`ROLE-MATRIX-30`、`ROLE-MATRIX-31`、`ROLE-MATRIX-32`、`ROLE-MATRIX-34`、`ROLE-MATRIX-35`、`ROLE-MATRIX-36`、`ROLE-MATRIX-38`、`ROLE-MATRIX-39`、`ROLE-MATRIX-40`、`ROLE-MATRIX-42`、`ROLE-MATRIX-43`、`ROLE-MATRIX-44`、`EACH-REQUEST-REVOKED-RIGHTS`、`DASHBOARD-NO-UNAUTHORIZED-COUNTS`、`ROOT-PERMISSIONS-IMMUTABLE`。

### 28. 一般設定（P2）—本機通過

來源：功能規格 E28／G28／L28；頁面：`admin/settings.html`。

- 功能 E28：管理網站名稱、SEO 描述、聯絡信箱、條款與隱私政策版本。
- 限制 G28：key 白名單、URL/email 格式、變更稽核
- 驗收 L28：前台 SEO 與聯絡資訊讀到已發布設定。

Email格式及未知key422、缺CSRF403；一般設定／條款／隱私版本公開同步，惡意政策文字按文字編碼，頁尾聯絡信箱更新。舊版409、重登保存仍在，稽核存在；驗後完整還原設定。

本輪案例：`AUDIT-RECORDED`、`SETTINGS-EMAIL-KEY-WHITELIST`、`SETTINGS-CSRF`、`SETTINGS-PUBLIC-POLICIES`、`SETTINGS-STALE-VERSION`、`SETTINGS-RELOGIN-PERSISTENCE`、`SETTINGS-AUDIT`、`EACH-REQUEST-REVOKED-RIGHTS`。

### 29. 詳情共用導覽（P1）—本機＋有效基線通過

來源：功能規格 E29／G29／L29；頁面：`所有詳情頁`。

- 功能 E29：內容圖片有替代文字；前後篇由同主題／分類已發布資料生成。
- 限制 G29：連結可用且名稱可辨；無相鄰時不產生空連結
- 驗收 L29：不使用燈箱；點擊以頁面導覽開啟。

四類詳情HTML及獨立neighbors API均可讀，空鄰居不產生空anchor。Browser檢查活動下一篇具img／標題／命名連結，Enter導航，無燈箱；完整四類原型版型及排序沿用P1有效基線。

本輪案例：`MEDIA-ALT-MIME`、`FILMS-NEIGHBOR-STRUCTURE`、`NEWS-NEIGHBOR-STRUCTURE`、`OFFERS-NEIGHBOR-STRUCTURE`、`ATTRACTIONS-NEIGHBOR-STRUCTURE`。

### 30. SEO標籤（P0）—本機通過；正式條件待交接

來源：功能規格 E30／G30／L30；頁面：`所有公開 HTML`。

- 功能 E30：每個公開頁輸出唯一 title、description、keywords、robots、canonical、OG、Twitter Card、favicon；詳情依資料動態帶入。
- 限制 G30：title／description 長度建議；canonical 絕對 HTTPS URL；私人頁 noindex
- 驗收 L30：抽測所有獨立 HTML 的標題與摘要唯一；分享預覽圖正確；詳情頁 canonical 指向自身。

原始HTML不執行JS可讀title／description／keywords／robots／canonical／OG／Twitter／favicon；目標美食覆寫及去追蹤參數驗證，私人頁noindex。正式origin／匿名HTTPS OG圖片／FB LINE X平台預覽仍依U-21待正式網域，不列通過。

本輪案例：`SEO-REJECT-CANONICALORIGIN`、`SEO-REJECT-ROBOTS`、`SEO-REJECT-OGIMAGEURL`、`SEO-NOJS-HEAD-OVERRIDE`、`SEO-OG-TWITTER-FAVICON`、`PRIVATE-NOINDEX`。

### 31. JSON-LD（P1）—本機＋有效基線通過

來源：功能規格 E31／G31／L31；頁面：`公開內容頁與詳情頁`。

- 功能 E31：網站輸出 WebSite／Organization；電影、文章、優惠、地方景點可用對應 Schema.org 類型。
- 限制 G31：只輸出可見且真實內容；JSON 必須合法；不可標記隱藏或未發布內容
- 驗收 L31：Google Rich Results／Schema validator 無結構錯誤，結構化欄位與頁面可見資料一致。

首頁、館舍、電影、消息、優惠、美食六頁JSON-LD可解析且不含注入字串，來源為同版公開資料。Schema.org官方七型別無錯誤的既有證據沿用（結構化資料程式未改），沒有宣稱本輪再次提交官方Validator。

本輪案例：`JSONLD-/index.html`、`JSONLD-/museum.html`、`JSONLD-/films/p8uid20b924f-film-a.html`、`JSONLD-/news/p8uid20b924f-event-a.html`、`JSONLD-/offers/p8uid20b924f-offer-a.html`、`JSONLD-/places/p8uid20b924f-food.html`。

### 32. SEO後台（P0）—本機通過

來源：功能規格 E32／G32／L32；頁面：`admin/settings.html`。

- 功能 E32：設定網站名稱、預設摘要、標題後綴、robots、關鍵字、正式網址、預設分享圖、Twitter、Google/Bing 驗證碼；逐主題覆寫 meta。
- 限制 G32：URL 限 HTTPS；robots 白名單；保護頁強制 noindex；變更稽核及快取清除
- 驗收 L32：後台儲存後前台 title/meta/分享標籤變更；重新登入仍持續；未授權者 403。

HTTPS canonical、OG URL及robots白名單失敗測試；全站設定及food單頁覆寫保存，重登讀回一致，HTML立即更新，稽核存在。撤銷settings權限下一請求403；政策及其他SEO欄位保持。

本輪案例：`SEO-REJECT-CANONICALORIGIN`、`SEO-REJECT-ROBOTS`、`SEO-REJECT-OGIMAGEURL`、`SETTINGS-RELOGIN-PERSISTENCE`、`SEO-NOJS-HEAD-OVERRIDE`、`PRIVATE-NOINDEX`、`SETTINGS-AUDIT`、`EACH-REQUEST-REVOKED-RIGHTS`。

### 33. robots／sitemap（P1）—本機通過；正式條件待交接

來源：功能規格 E33／G33／L33；頁面：`robots.txt／sitemap.xml`。

- 功能 E33：robots.txt 排除後台；sitemap 只列已發布可索引公開 URL，內容異動自動更新。
- 限制 G33：不得收入登入、註冊、個人行程、搜尋、管理端與草稿；robots 不代替存取控制
- 驗收 L33：Search Console 可抓 sitemap；每個 sitemap URL 回 200 且 canonical 自指。

實際抓robots、sitemap及分片，具HTTPS基底與lastmod，電影已發布URL在內，草稿／2099排程／私人行程不在。發布／下架同步沿用索引369項有效基線；Search Console正式抓取未做。

本輪案例：`ROBOTS-SITEMAP-PUBLISHED-ONLY`。

### 34. 共用UI／安全／AA（P0）—本機＋有效基線通過

來源：功能規格 E34／G34／L34；頁面：`所有頁面`。

- 功能 E34：表單標籤、鍵盤焦點、狀態訊息、錯誤關聯；API 資料依 context escaping 顯示。
- 限制 G34：WCAG 2.1 AA 對比／鍵盤／縮放；伺服端輸入驗證；CSP 與輸出編碼
- 驗收 L34：鍵盤可完成主要流程；表單錯誤讀屏可辨；未授權 API 回 401/403。

真Browser驗證表單標籤、命名連結、Enter、刪除確認取消與五種列表刪除後焦點；錯誤頁三尺寸及可見outline。XSS／CSP／CSRF／401／403及server驗證有本輪證據。完整AA採既有50準則矩陣加本輪增量，不宣稱重做所有真200%／400%或第三方認證；未啟動NVDA。

本輪案例：`NEWS-XSS-SANITIZED`、`SEARCH-LITERAL-XSS`、`SETTINGS-CSRF`、`MAP-UNSAFE-0`、`MAP-UNSAFE-1`、`MAP-UNSAFE-2`、`MAP-UNSAFE-3`、`MAP-UNSAFE-4`、`ANONYMOUS-MANAGEMENT-member`、`ANONYMOUS-MANAGEMENT-admin`、`LOGIN-CSRF-ORIGIN`。

## 其餘工作表與資料庫核對

- 串接總覽 B7:B16：同版公開投影／API、server驗證碼、登入鎖定／CSRF／session輪替、地圖SSRF、圖片解碼重編碼、發布狀態與逐請求授權均有對應測試；SMTP／Secure Cookie的正式條件保持交接。U-14檔案掃描是圖片內容檢查，不冒稱病毒掃描。
- 權限矩陣 A5:F44：30列非root範本與本輪API逐列比對；10列root依固定完整權限及不可變更政策／既有真DB保護，本輪root資料與權限API403、UI無操作。不是只隱藏前端。
- SEO規格 B5:F17：無JS head、單頁覆寫、私頁noindex、JSON-LD及索引證據如上述30–33列；官方分享／Search Console／Bing實際平台驗證依U-21待正式網址。字數及1200×630保持建議，不改成新儲存門檻。
- API草案與資料表建議：逐項功能有API／Repository／實際關聯對照，但不要求一模一樣的示意路徑或表名。實際115 HTTP契約、68固定頁、34表338欄、20 migration；本輪無新PHP API、DDL、env或依賴。[資料表對照](DATABASE-SPECIFICATION-MAP.md)、[OpenAPI](../backend/resources/openapi.json)。首頁內部503回應資產不是新增可索引頁或PHP View。
- 頁面清單：仍以原始原型為版型基準，新增業務互動有功能證據；同日上一輪168筆Browser三尺寸／原型比對為有效基線，本輪未宣稱再逐頁重拍全部。正常版型不因故障畫面而重設。

## 基線、正式條件與清理

有效基線：[本日P0/P1/P2完整重驗](archive/P0-P1-P2-REVERIFICATION-20261102.md)、[P1逐輪驗收](archive/P1-ACCEPTANCE-20261031.md)、[AA完整50項](archive/P0-AA-FINAL-20261029.md)。本輪焦點與錯誤畫面變更已加測，不以曾通過排除新回歸。未重新量所有真200%／400%／全部輔具；本輪沒有啟動作業系統讀屏。

正式未定的HTTPS origin、Secure Cookie、外部SMTP、FB／LINE／X、Search Console／Bing、正式Google配額／來源限制，以及原映像掃描差項仍見[server交接](PRODUCTION-HANDOFF.md)與[安全審查](archive/PHASE-15-SECURITY-REVIEW.md)。這些是正式交付條件，不用Docker是否啟動來代替網站功能驗證，也不表示沒有安全風險。

只清理自有前綴p8uid20b924f：六類內容剩餘均0、媒體刪除、暫時帳號停用／軟刪除及session撤銷；正常audit與同意紀錄保留。館舍指定、一般設定、SEO及原覆寫已按快照還原，故障開關為false。未更動主庫業務資料／原帳號密碼／其他測試人的資料。

## 本次程式與操作檔案

- `frontend/scripts/home-error-view.mjs`：用既有共用頁殼產生四模組失敗回應；`build.mjs`輸出`home-unavailable.response`。
- `docker/nginx/default.conf`：首頁同版gate失敗映射內部503資產；不變更JSON API錯誤。既有server須配置相同映射，見交接指南。
- `frontend/assets/js/pages/attraction-list.js`／`film-list.js`／`content-list.js`／`admin-list.js`：刪除後聚焦現存結果訊息；路線列表原已正確。
- `frontend/assets/js/core/home-view.js`：首頁消息另開分頁與可辨識名稱。
- `frontend/tests/admin-delete-focus.test.mjs`／`home-error-view.test.mjs`／`home-publication.test.mjs`：上述行為與編碼回歸。

本輪修正及報告歸入階段十五原整合驗收提交，不新增虛假階段；合併前保留完整bundle，核對完整tree與原始69檔，不推送。
