# 程式註解來源對照（2026-11-02 快照）

依使用者指示，程式內註解改為一般說明文字，不再夾帶 `[S：…]`、`E-xx`、`U-xx` 等內部代碼。本檔保留改寫前每則帶代碼註解的位置與原文，作為規格／使用者指定／工程補充的追溯紀錄；代碼定義見 [DESIGN-TRACEABILITY.md](DESIGN-TRACEABILITY.md)。行號對應改寫前的提交 `e28cf35`，之後程式格式化會改變行號，請以檔案與原文對照。

共 411 則，272 個檔案。

## backend/bin/readiness.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 5 | [E-29] | * [E-29] 容器內 readiness CLI；PHP 不提供 HTML，與 FPM liveness 分開。 */ |

## backend/bin/recovery.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 5 | [E-29] | * [E-29] 維護窗口專用 CLI，沒有 HTTP 路由；主庫復原不在演練授權內。 */ |

## backend/src/Adapters/AttractionSeeder.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 16 | [E-18] | * [E-18] 僅由明確 content:seed-demo 命令匯入原始交付，不覆蓋已有內容。 */ |
| 49 | [E-26] | [E-26] 只有本次新建的原型店家跟著受控發布；不重新啟用已有停用 profile。 |
| 55 | [U-05／R3-02] | * [U-05／R3-02] 明確維護命令；只補仍屬原始展示內容且尚無標籤的筆數。 |

## backend/src/Adapters/CurlGoogleHttp.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 12 | [E-19] | * [E-19] 不自動 redirect；每次驗 DNS 並釘選 IP，避免驗證與連線之間 DNS 改變。 */ |

## backend/src/Adapters/FilmSeeder.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 16 | [E-25] | * [E-25] 受控示意資料沒有放映年份，不猜年份或結束時間。 */ |

## backend/src/Adapters/GoogleOpeningHours.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 10 | [U-09／E-20] | * [U-09／E-20] Google 的常態 periods 轉為本站週表；文字僅作無法映射時的人工參考。 */ |

## backend/src/Adapters/GooglePlaceMapper.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 9 | [U-10／E-21] | * [U-10／E-21] 外部欄位轉為本站候選。行政區需確認新竹市，官網不改寫協定、不抓取。 */ |
| 33 | [U-10] | [U-10] 僅回傳原文供參考，不截斷、改寫或保存進網站介紹。 |

## backend/src/Adapters/GoogleSummaryProvider.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 12 | [U-23／E-40] | * [U-23／E-40] 只取editorialSummary原文，不抓評論／AI摘要／照片，不保存外部內容。 */ |

## backend/src/Adapters/LocalImageStorage.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 15 | [S：功能規格 G20/G29；E-18] | * [S：功能規格 G20/G29；E-18] 本地 volume adapter；驗證真實圖片並重新編碼。 */ |

## backend/src/Adapters/NodeContentRenderer.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 12 | [E-12／E-18] | * [E-12／E-18] PHP 只交付資料給前端 Node 工具，畫面模板留在 frontend。 */ |
| 38 | [E-35] | [E-35] 搜尋投影規則更新亦重建同版HTML與MariaDB索引。 |

## backend/src/Adapters/PdoDatabaseHealthProbe.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 12 | [E-29] | * [E-29] 獨立短連線、無 DDL／seed；不能先組裝依賴 DB 的認證 Kernel。 */ |

## backend/src/Adapters/PdoTransactions.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 18 | [E-09] | [E-09] 交易內只有資料庫異動；死鎖重試不會重送郵件。 |

## backend/src/Adapters/PhpMailerAdapter.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 11 | [E-11] | * [E-11] SMTP 套件只出現在 Adapter；本機一律送 Mailpit。 */ |

## backend/src/Adapters/PublicContentCommands.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 25 | [E-17／26] | * [E-17／26] 受信 Docker 維運 CLI；不建立可公開呼叫的 HTTP 匯入入口。 */ |

## backend/src/Adapters/PublicContentSeeder.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 20 | [E-26] | * [E-26] 明確原型示範匯入；只建立缺少內容，不更新已有正式／示範資料。 */ |

## backend/src/Adapters/RecoveryMediaInspector.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 11 | [E-29] | * [E-29] 只處理既有平面 UUID 圖檔儲存；拒絕遺失、連結、未知檔案及暫存殘留。 */ |

## backend/src/Adapters/SeoCatalog.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 10 | [E-28] | * [E-28] PHP 與 Node 共用的來源文件；只讀已交付的明確頁面，不讀使用者檔案。 */ |

## backend/src/Composition.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 56 | [E-01] | * [E-01] 唯一組裝位置；業務類別不查容器，也不自行 new 資料庫／SMTP。 */ |
| 59 | [E-29] | * [E-29] 入口與操作清單共用唯一的 eager-DB 前置路徑。 */ |
| 64 | [E-29] | * [E-29] health 在任何強制 DB 連線之前組裝，普通 API 的權限管線保持完整。 */ |
| 108 | [U-23／E-40] | [U-23／E-40] 補位預覽與即時Google原文分開；不增加資料表。 |
| 136 | [S G22；E-31] | [S G22；E-31] HTTP與CLI共用明確editor，不組裝匯入封裝。 |
| 145 | [S G23；E-31] | [S G23；E-31] 優惠與共享店家分開動作；靜態merchants路由先註冊。 |

## backend/src/Config.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 9 | [U-01／E-08] | * [U-01／E-08] 設定只有一個來源，秘密由 Docker 的 .env 提供。 */ |
| 12 | [E-29] | * [E-29] 公開 release 標籤；不能回傳內部映像 digest 或任意環境內容。 */ |

## backend/src/Contracts/ContentImportService.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 12 | [E-26] | * [E-26] CLI 與後續管理入口共用業務操作；批次交易由外層使用案例協調。 */ |

## backend/src/Contracts/DatabaseHealthProbe.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 7 | [E-29] | * [E-29] 核心 DB／migration 就緒探測，正式 PDO 與受控故障測試共用邊界。 */ |

## backend/src/Contracts/RichTextSanitizer.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 7 | [S：功能規格 G10／G22；E-26] | * [S：功能規格 G10／G22；E-26] 只由 Adapter 知道第三方淨化工具。 */ |

## backend/src/Controllers/PermissionController.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 14 | [E-01／S-027] | * [E-01／S-027] 權限與角色 API 的 HTTP 邊界；業務與交易留在各 Service。 */ |

## backend/src/DTO/AccountSummary.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 10 | [E-01／E-13] | * [E-01／E-13] 對外欄位白名單；不持有內部 Account，避免帶出密碼雜湊或刪除資訊。 */ |

## backend/src/DTO/AdminContentQuery.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 11 | [S G22／G23；E-31] | * [S G22／G23；E-31] 已有欄位的管理查詢，不新增業務必填。 */ |

## backend/src/DTO/AdminPage.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 7 | [E-01／E-13] | * [E-01／E-13] 管理員列表的資料與分頁資訊；不依賴 HTTP。 */ |

## backend/src/DTO/AdminWriteInput.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 30 | [U-12／原型] | [U-12／原型] 一般登入帳號僅要求非空；不擅加英文字首及三字限制。 |

## backend/src/DTO/Attraction.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 7 | [E-18] | * [E-18] Repository 已驗證的景點快照；明確公開與管理輸出邊界。 */ |
| 19 | [S：功能規格 E20] | * [S：功能規格 E20] 僅提供試填基本資料，不複製識別、發布、圖片授權或 Google 關聯。 |

## backend/src/DTO/AttractionInput.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 13 | [S：功能規格 F13/G20；E-18] | * [S：功能規格 F13/G20；E-18] 表單與受控匯入共用同一組驗證。 */ |
| 75 | [U-10／E-21] | [U-10／E-21] 官網可保留 HTTP 外連；地圖及伺服器外部請求仍為 HTTPS。 |
| 84 | [S：功能規格 G20／U-12] | [S：功能規格 G20／U-12] 手填與匯入皆採相同 Google 地圖網址規則。 |
| 91 | [U-11／E-22] | [U-11／E-22] 網站摘要與介紹選填；留空保存空字串，仍檢查型別及字數上限。 |
| 105 | [U-09／E-20] | [U-09／E-20] 手動與匯入共用週表驗證；hours 僅作相容摘要。 |
| 112 | [E-18／R3-01] | [E-18／R3-01] 未提供欄位保留既有值；明確空白／null 表示回到資產原始說明。 |
| 127 | [S：唯一 slug／U-12] | * [S：唯一 slug／U-12] 留空新增時由系統產生；編輯留空則沿用原網址。 */ |

## backend/src/DTO/AuthenticatedIdentity.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 9 | [U-06] | * [U-06] 保留登入者與驗收對象；停用／刪除對象不能回退取得 root 權限。 */ |

## backend/src/DTO/CaptchaResponse.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 7 | [E-01／E-06／S-SEC-10] | * [E-01／E-06／S-SEC-10] 不保存驗證答案；圖片與 Email 模式保留原有的可選欄位。 */ |

## backend/src/DTO/ContentFields.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 12 | [E-26] | * [E-26] 消息／優惠共用格式邊界；不增加業務必填。 */ |

## backend/src/DTO/ContentRecordView.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 7 | [E-31] | * [E-31] 只對已授權管理頁輸出選定業務欄位；公開投影保持原白名單。 */ |

## backend/src/DTO/ContentUrl.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 9 | [E-25] | * [E-25] 只驗證外連，不在伺服器下載任意網址。 */ |

## backend/src/DTO/Film.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 7 | [E-25] | * [E-25] 管理與公開輸出使用明確白名單。 */ |

## backend/src/DTO/FilmInput.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 12 | [S：功能規格 7/21；U-12／16] | * [S：功能規格 7/21；U-12／16] 必填依原型；F 欄不是必填清單。 */ |

## backend/src/DTO/GeneralSettings.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 9 | [S：F28/G28；E-36] | * [S：F28/G28；E-36] 純文字政策；上限是資源界線，空白沿用開發說明。 */ |

## backend/src/DTO/HomeContent.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 7 | [E-26] | * [E-26] 同一公開投影中的首頁欄位；沒有額外首頁資料表或設定管理。 */ |

## backend/src/DTO/IdentityResponse.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 9 | [E-01／E-02／U-06] | * [E-01／E-02／U-06] 認證輸出與內部 AuthGrant 分離；Cookie、CSRF 仍由 HTTP 層處理。 */ |

## backend/src/DTO/ImportItem.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 7 | [E-26] | * [E-26] 匯入識別獨立於內容表單；ID 不由標題或目前陣列位置推算。 */ |

## backend/src/DTO/Input.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 9 | [E-01] | * [E-01] JSON 的型別、白名單與長度在邊界集中驗證。 */ |
| 49 | [U-12] | * [U-12] 文件未指定非空的文字，省略或空白均保存空字串。 */ |
| 55 | [U-13／原型] | * [U-13／原型] 共用八字密碼邊界；單一初始密碼不要求不存在的確認欄。 */ |
| 61 | [U-15] | * [U-15] 註冊、重設及更改密碼必須再次輸入，且逐字一致；不修剪密碼。 */ |

## backend/src/DTO/ItineraryItemInput.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 9 | [E-27] | * [E-27] version=0 只用於尚無目前行程；此階段不接受名稱／日期等完整編排。 */ |

## backend/src/DTO/ItineraryStop.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 7 | [D：C20；E-27] | * [D：C20；E-27] snapshotName 只取加入當時公開名稱，不隨草稿修改。 */ |

## backend/src/DTO/MapCandidate.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 7 | [U-10／E-21] | * [U-10／E-21] 已校驗的 Google 候選；不直接將提供者 JSON 當成本站輸入。 */ |

## backend/src/DTO/MemberItinerary.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 7 | [D：C19；E-27] | * [D：C19；E-27] 內部本人行程摘要；不把 member_id 暴露給前端作授權依據。 */ |

## backend/src/DTO/MemberPageInput.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 9 | [E-27] | * [E-27] 游標只是位置，不是授權；Repository 必須另加會員條件。 */ |

## backend/src/DTO/MemberSummary.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 7 | [S：功能規格 F25/G25；E-36] | * [S：功能規格 F25/G25；E-36] 最小投影；不接觸密碼或驗證欄位。 */ |

## backend/src/DTO/Merchant.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 7 | [D：資料表建議 C15；E-26] | * [D：資料表建議 C15；E-26] 店家 profile 與景點內容有明確各自的用途。 */ |

## backend/src/DTO/MerchantInput.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 9 | [D：資料表建議 C15；原型 merchant；E-26] | * [D：資料表建議 C15；原型 merchant；E-26] 店家可獨立於景點存在。 */ |

## backend/src/DTO/NewsInput.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 10 | [S：功能規格 G9／G22；原型 news-add；E-26] | * [S：功能規格 G9／G22；原型 news-add；E-26] 只有標題／分類必填，活動區間可空。 */ |

## backend/src/DTO/NewsPost.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 7 | [D：資料表建議 14；E-26] | * [D：資料表建議 14；E-26] 明確主題資料，不把優惠混入消息。 */ |

## backend/src/DTO/Offer.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 7 | [D：資料表建議 16；E-26] | * [D：資料表建議 16；E-26] 明確主題資料，不把優惠混入消息。 */ |

## backend/src/DTO/OfferInput.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 9 | [S：功能規格 G11／G23；原型 offer-add；E-26] | * [S：功能規格 G11／G23；原型 offer-add；E-26] 條款必填、日期不擅加必填。 */ |

## backend/src/DTO/OpeningHours.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 10 | [U-09／E-20] | * [U-09／E-20] 人工與 Google Adapter 共用的常態週表；不保存即時 openNow。 */ |

## backend/src/DTO/OpeningPeriod.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 9 | [U-09／E-20] | * [U-09／E-20] 一個開始日的本地時段，最長 24 小時。 */ |

## backend/src/DTO/PermissionCatalog.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 7 | [E-01／E-13] | * [E-01／E-13] 矩陣欄位與最小選單摘要；不包含 Email 或登入憑證。 */ |

## backend/src/DTO/PermissionMatrix.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 7 | [E-01／S-027] | * [E-01／S-027] 明確保存對象與權限版本，維持既有 JSON 契約。 */ |

## backend/src/DTO/PhoneNumber.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 9 | [S：功能規格 G6；E-24] | * [S：功能規格 G6；E-24] 共用電話格式／tel URI；格式範圍是工程實作，非必填政策。 */ |

## backend/src/DTO/PreparedContentChange.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 7 | [S G22／G23；E-31] | * [S G22／G23；E-31] HTTP與CLI共用的已驗證命令，不包含匯入檔格式。 */ |

## backend/src/DTO/PreviewStatus.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 7 | [U-06／E-01] | * [U-06／E-01] 對外只提供操作者摘要；不持有 session、token 或完整帳號。 */ |

## backend/src/DTO/PublicCollection.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 7 | [E-26] | * [E-26] 確認成功快照中的主題清單型別，再交給公開使用案例。 */ |

## backend/src/DTO/PublishedRecord.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 7 | [E-26] | * [E-26] Repository 映射後的內容；公開輸出不可帶作者、媒體內部識別或狀態。 */ |
| 10 | [E-34] | * [E-34] updatedAt是索引內部中繼資料，publicArray仍只回既有白名單。 |

## backend/src/DTO/RecommendedRoute.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 7 | [E-32] | * [E-32] Repository 已驗證的安全輸出；公開站點投影另由成功快照產生。 */ |

## backend/src/DTO/RecommendedRouteInput.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 10 | [S G14/G24；U-12；E-32] | * [S G14/G24；U-12；E-32] 僅名稱、至少一站必填；時間、主題、說明選填。 */ |

## backend/src/DTO/RoleTemplate.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 7 | [E-01／E-10] | * [E-01／E-10] 角色起始模板，不代表帳號即時的個人權限。 */ |

## backend/src/DTO/ScreeningInput.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 11 | [U-16／E-25] | * [U-16／E-25] 開始時間採分鐘精度；不接收結束時間或場地。 */ |

## backend/src/DTO/ScreeningWindow.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 11 | [E-25] | * [E-25] 台北週一至下週一，不包含結束邊界；UTC 用於比較及查詢。 */ |

## backend/src/DTO/SearchDocument.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 9 | [S F18；E-35] | * [S F18；E-35] 公開白名單投影；不複製整份DTO或帳號資料。 */ |

## backend/src/DTO/SearchQuery.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 12 | [S G18；E-35] | * [S G18；E-35] 容量沿現有公開清單，不限制中文最少字數。 */ |

## backend/src/DTO/SeoDefaults.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 11 | [S：E32；E-28] | * [S：E32；E-28] 上限為資源邊界，不是 Excel 建議字數的強制限制。 */ |

## backend/src/DTO/SeoSettingsInput.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 12 | [E-28] | * [E-28] 完整文件／version 為技術更新契約，不要求管理員填滿選填 SEO。 */ |

## backend/src/DTO/SessionIdentity.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 7 | [U-06] | * [U-06] Session 的資料邊界；不含明文憑證。 */ |

## backend/src/DTO/SessionTiming.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 7 | [S G34／B15；E-29] | * [S G34／B15；E-29] 已驗證登入的期限；不含 token，也不改變伺服器期限。 */ |

## backend/src/DTO/TripInput.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 9 | [S E15/G15；原型planner欄位無required；E-33] | * [S E15/G15；原型planner欄位無required；E-33] 空名稱日期與空行程皆可保存。 */ |

## backend/src/DTO/TripStopInput.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 9 | [S F15/G15；E-33] | * [S F15/G15；E-33] startTime映射既有planned_time；順序取陣列位置。 */ |

## backend/src/Exceptions/AccountConflict.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 10 | [E-01] | * [E-01] 資料存取層轉譯帳號唯一值衝突；Service 不需要知道 SQLSTATE 或資料庫驅動。 */ |

## backend/src/Exceptions/ContentConflict.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 7 | [E-18] | * [E-18] 儲存層唯一約束轉為可由 Service 處理的內容衝突。 */ |

## backend/src/Exceptions/PublicationPending.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 7 | [S：B7/B13；E-29] | * [S：B7/B13；E-29] 只保存固定分類與 hash，不將公開內容、路徑或設定值寫入診斷。 */ |

## backend/src/Http/Csrf.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 13 | [S-SEC-09／E-02] | * [S-SEC-09／E-02] 匿名簽章 Cookie 與登入 token 分離，且每種身分各自綁定。 */ |
| 16 | [E-29] | [E-29] 虛擬主機校時的有限回撥預算；不延長到期時間、不接受未簽章 token。 |

## backend/src/Http/Kernel.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 18 | [E-01] | * [E-01] 共用管線：錯誤 → 路由 → CSRF → 身分 → 權限 → Controller。 */ |
| 50 | [U-06] | [U-06] 唯讀限制由 API 統一強制；退出／登出不依賴被模擬者權限。 |
| 82 | [S G34／B15；E-29] | * [S G34／B15；E-29] 依真正驗證的 session 回傳期限，不能由瀏覽器宣告延長。 */ |
| 132 | [U-06] | [U-06] 模擬中連匿名的管理員改密碼／登入端點也不能寫入；失效 Cookie 不阻擋正常登入。 |

## backend/src/Http/Request.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 126 | [E-01] | [E-01] PHP 會把數字字串 key 轉成 int；不能默默丟棄而繞過 DTO 欄位白名單。 |

## backend/src/Http/Route.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 28 | [E-27] | [E-27] 本人資源的 attractionId 與既有 id 採相同路由邊界，完整 UUID 由 DTO 驗證。 |

## backend/src/Models/AttractionAvailability.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 7 | [E-27] | * [E-27] 公開生成故障不是下架；私人清單需明確區分未知。 */ |

## backend/src/Models/ContentStatus.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 7 | [E-18] | * [E-18] 共用內容狀態；排程由 published + publishAt 表達。 */ |

## backend/src/Models/ImageOrigin.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 7 | [E-26] | * [E-26] 來源只由伺服器入口建立，不能由 HTTP 欄位選擇。 */ |

## backend/src/Models/MediaModule.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 9 | [E-25] | * [E-25] 共用媒體端點依所屬業務模組授權；不產生新的權限種類。 */ |

## backend/src/Models/NewsType.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 7 | [S：功能規格 G9／G22] | * [S：功能規格 G9／G22] 優惠不屬於消息分類。 */ |

## backend/src/Models/OpeningDayStatus.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 7 | [U-09／E-20] | * [U-09／E-20] 未提供時間不等於公休。 */ |

## backend/src/Models/SearchType.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 7 | [S E18；E-35] | * [S E18；E-35] 固定分組順序；美食由景點分類獨立篩選。 */ |

## backend/src/Models/SeoResource.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 7 | [D：C25；E-28] | * [D：C25；E-28] 只管理已交付內容，不提前建立路線 SEO 寫入。 */ |

## backend/src/Models/SeoRobots.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 7 | [S：功能規格 G32；原型 SEO 三選項] | * [S：功能規格 G32；原型 SEO 三選項] */ |

## backend/src/Policies/AttractionContentPolicy.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 9 | [U-23／E-40] | * [U-23／E-40] 顯示補位，不改寫儲存值；Google文字不進此純自家文案政策。 */ |

## backend/src/Policies/BodyImages.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 11 | [S：功能規格 G10／G29；E-26] | * [S：功能規格 G10／G29；E-26] 對淨化後正文檢查圖片用途，不下載任意 URL。 */ |

## backend/src/Policies/FilmPosterPolicy.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 12 | [S：功能規格 G7／G21] | * [S：功能規格 G7／G21] 無海報可留空；有圖時須有有效替代文字。 */ |

## backend/src/Policies/GooglePlaceLinkPolicy.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 11 | [U-10／E-21] | * [U-10／E-21] 名稱／地址／座標改動後必須核對或解除關聯；確認是管理員行為。 */ |

## backend/src/Policies/GoogleUrlPolicy.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 9 | [S：功能規格 G20；E-19] | * [S：功能規格 G20；E-19] 精確網域／協定白名單；不以字串包含 google 判斷。 */ |

## backend/src/Policies/HomeSelection.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 11 | [S：功能規格 5；E-26] | * [S：功能規格 5；E-26] 沿用原型的精選、三部電影、三則消息與排序。 */ |

## backend/src/Policies/ImportVersion.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 26 | [E-26] | [E-26] 完全相同的重跑可不寫入；不同內容不能以過期版本覆蓋。 |

## backend/src/Policies/NeighborSelection.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 9 | [S G8／L10／G29；E-31] | * [S G8／L10／G29；E-31] 只對成功投影的白名單選鄰居；發布與公開API共用結果。 */ |

## backend/src/Policies/OfferValidity.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 10 | [S：功能規格 G11；E-26] | * [S：功能規格 G11；E-26] 台北當地日期，迄日整天有效；空值不限制該端。 */ |

## backend/src/Policies/PermissionPolicy.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 29 | [S-026] | [S-026] UI 隱藏按鈕之外，業務規則也拒絕所有 root CRUD。 |
| 38 | [S：功能規格 G26「不可自我移除最後管理權」；U-24] | [S：功能規格 G26「不可自我移除最後管理權」；U-24] 介面已隱藏本人刪除，服務端同樣拒絕，避免自行刪號失去管理權。 |

## backend/src/Policies/RecommendedRouteSelection.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 9 | [S G14；D API草案 G14；E-32] | * [S G14；D API草案 G14；E-32] 跳過下架站點；全數不可用時不公開路線。 */ |

## backend/src/Policies/ScreeningStartPolicy.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 10 | [U-16] | * [U-16] 僅比較開始分鐘，不推算片長／結束時間。 */ |

## backend/src/Policies/SeoTargetPolicy.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 12 | [D：D25；E-28] | * [D：D25；E-28] 不執行任意 wildcard；只允許共用 catalog 的明確目標。 */ |

## backend/src/Policies/SeoUrlPolicy.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 9 | [S：G32／SEO E8–E9；E-28] | * [S：G32／SEO E8–E9；E-28] 純驗證，不抓取外部網址。 */ |

## backend/src/Policies/WeeklyFilmSelection.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 9 | [S：功能規格 5／7；E-26] | * [S：功能規格 5／7；E-26] 首頁與電影 API 共用週篩選及第一場排序。 */ |

## backend/src/Repositories/AccountRepository.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 114 | [U-06] | * [U-06] 只給 root 的選單，排除停用／刪除及固定 root；不回傳 Email 等不必要資料。 |
| 126 | [E-01] | * [E-01] 只轉譯帳號寫入的 duplicate key；外鍵、CHECK、連線及稽核錯誤仍是非預期失敗。 |
| 165 | [U-13] | [U-13] 舊回應欄位保留相容，已取消首登強制改密碼。 |

## backend/src/Repositories/AttractionRepository.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 15 | [S：功能規格 13/19/20；E-18] | * [S：功能規格 13/19/20；E-18] SQL 僅在此層，公開投影與管理查詢共用映射。 */ |
| 52 | [S：功能規格 E20；E-24] | * [S：功能規格 E20；E-24] 僅原始六筆、已發布且未刪除的 DB 內容；不重建本機模擬資料。 |
| 78 | [U-09] | [U-09] 舊客戶端省略週表時，不可用舊摘要蓋掉已存在的結構化摘要。 |
| 85 | [E-17／26] | [E-17／26] 分類可修改；保留已被優惠引用的店家 profile，不以硬刪破壞歷史。 |
| 137 | [U-05／R3-02] | [U-05／R3-02] 發布時間優先；同時發布的受控 seed ID 按原型順序，其他 ID 亦有穩定次排序。 |

## backend/src/Repositories/ContentMapping.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 7 | [E-26] | * [E-26] 只共用 DB 型別及公開欄位映射；SQL 與主題規則仍在各 Repository。 */ |

## backend/src/Repositories/ContentSeoRepository.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 11 | [D：C25；E-28] | * [D：C25；E-28] 文件的所有覆寫一次讀／寫，參與外層交易。 */ |

## backend/src/Repositories/Database.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 36 | [E-29] | [E-29] DB 暫停／斷線不讓公開請求等待預設30秒，也不洩漏連線內容。 |

## backend/src/Repositories/EventRepository.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 33 | [S-026／S-027] | [S-026／S-027] 僅接受 Service 組合的安全欄位，不傳入帳號模型或憑證。 |
| 58 | [S：功能規格 G19/G20] | * [S：功能規格 G19/G20] 內容稽核只保存識別、版本與狀態，不記錄上傳內容或金鑰。 */ |

## backend/src/Repositories/ExternalRequestSlot.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 7 | [E-40] | * [E-40] 每個資料庫一個即時Google備援槽，不持鎖寫入Google資料。 */ |

## backend/src/Repositories/FavoriteRepository.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 9 | [D：C21／D21；E-27] | * [D：C21／D21；E-27] 每個查詢包含本人條件；不回傳其他會員的收藏。 */ |

## backend/src/Repositories/FilmRepository.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 13 | [D：資料表建議 12；E-25] | * [D：資料表建議 12；E-25] 電影查詢共用映射，場次批次載入避免 N+1。 */ |

## backend/src/Repositories/FilmScreeningRepository.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 12 | [D：資料表建議 13；U-16] | * [D：資料表建議 13；U-16] 同一 PDO 參與電影 aggregate 的交易。 */ |

## backend/src/Repositories/GeneralSettingsRepository.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 11 | [S：G28/I28；E-36] | * [S：G28/I28；E-36] 固定key白名單，不提供任意設定寫入。 */ |

## backend/src/Repositories/ItineraryRepository.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 10 | [S：G15；D：C19–D20；E-27] | * [S：G15；D：C19–D20；E-27] 即使內部知道行程 ID，SQL 仍須套會員與有效行程。 */ |
| 68 | [E-33] | * [E-33] 寫入呼叫端先鎖會員；同會員版本單調遞增，保護只送version的P0請求。 */ |

## backend/src/Repositories/MemberDirectoryRepository.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 9 | [S：H25；E-36] | * [S：H25；E-36] 固定20筆與穩定排序，LIKE萬用字元視為字面文字。 */ |

## backend/src/Repositories/NewsRepository.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 12 | [D：資料表建議 14；E-26] | * [D：資料表建議 14；E-26] 主題查詢／型別映射／歷史網址集中管理。 */ |
| 19 | [S G22／G23；E-31] | * [S G22／G23；E-31] 管理查詢保留非公開內容；不與公開投影共用篩選。 */ |
| 114 | [E-29] | [E-29] Alias 是映射，不讓 SQL 掃描順序改變發布 generation。 |

## backend/src/Repositories/OfferRepository.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 12 | [D：資料表建議 16；E-26] | * [D：資料表建議 16；E-26] 主題查詢／型別映射／歷史網址集中管理。 */ |
| 19 | [S G22／G23；E-31] | * [S G22／G23；E-31] 管理查詢保留非公開內容；不與公開投影共用篩選。 */ |
| 119 | [E-29] | [E-29] Alias 是映射，不讓 SQL 掃描順序改變發布 generation。 |

## backend/src/Repositories/OperationsRepository.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 7 | [E-36] | * [E-36] SQL識別符只取固定映射；每個模組由Service授權後才查詢。 */ |

## backend/src/Repositories/RateLimitRepository.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 13 | [E-04] | [E-04] INSERT/UPDATE 在 MariaDB 原子累加，不使用單一 FPM process 的計數器。 |

## backend/src/Repositories/RecommendedRouteRepository.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 10 | [S G24；D C17:D18；E-32] | * [S G24；D C17:D18；E-32] 有序站點與主檔參與同一交易；所有輸入參數化。 */ |

## backend/src/Repositories/RecoveryRepository.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 7 | [E-29] | * [E-29] 備份完整性與復原後憑證撤銷；不刪帳號、業務資料或 audit。 */ |

## backend/src/Repositories/SearchRepository.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 10 | [S G18；E-35] | * [S G18；E-35] 只搜尋成功公開投影，使用綁定參數，不拼接關鍵字。 */ |

## backend/src/Repositories/SeoContentRepository.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 11 | [E-28] | * [E-28] 僅取 SEO 選擇器識別，不查正文；SQL 識別只來自 enum。 */ |

## backend/src/Repositories/SessionRepository.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 62 | [U-06／E-16] | * [U-06／E-16] 在鎖定交易內更新同一 session，輪替 token，不延長最長登入期限。 */ |

## backend/src/Repositories/SiteSettingsRepository.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 11 | [D：C24；E-28] | * [D：C24；E-28] 本階段只允許 seo，Repository 不接受外部任意設定 key。 */ |

## backend/src/Services/AdminManagement.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 24 | [E-01／S-026] | * [E-01／S-026] 管理員帳號生命週期；個人矩陣編輯交由 PermissionManagement。 */ |
| 61 | [S：功能規格 G26 admin 保留字；U-24] | [S：功能規格 G26 admin 保留字；U-24] 以欄位錯誤說明保留帳號，不再只回籠統的權限不足。 |

## backend/src/Services/AdminNewsManagement.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 15 | [S G22／G23；E-31] | * [S G22／G23；E-31] HTTP應用服務；與CLI共用Editor，不在Controller組裝匯入檔。 */ |

## backend/src/Services/AdminOfferManagement.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 15 | [S G22／G23；E-31] | * [S G22／G23；E-31] HTTP應用服務；與CLI共用Editor，不在Controller組裝匯入檔。 */ |

## backend/src/Services/AdminPreview.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 18 | [U-06／E-16] | * [U-06／E-16] 僅固定 root 可進入唯讀模擬；身分仍由資料庫與 session 決定。 */ |

## backend/src/Services/ApplicationHealth.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 12 | [D API草案 C43:H43／E-29] | * [D API草案 C43:H43／E-29] 核心 readiness，不代表郵件、Google 或公開發布已完成。 */ |

## backend/src/Services/AttractionManagement.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 22 | [S：功能規格 19/20；E-01] | * [S：功能規格 19/20；E-01] 景點生命週期與授權／交易，Controller 不處理 SQL。 */ |
| 44 | [S：功能規格 E20] | * [S：功能規格 E20] 新增權限足以試填；不因此開放一般管理列表或草稿。 |

## backend/src/Services/AttractionSummary.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 35 | [E-40] | [E-40] 防匿名配額耗用：來源10／分鐘、地點6／分鐘、全站30／分鐘及100／日。 |

## backend/src/Services/Authorization.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 36 | [E-31] | [E-31] 表單單筆／選擇器不強迫具新增／編輯權者另有列表檢視權。 |

## backend/src/Services/Captcha.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 89 | [S-016／S-SEC-10] | [S-016／S-SEC-10] 答案只畫入 PNG，不回傳文字、SVG 或 DOM 屬性。 |

## backend/src/Services/ContentImporter.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 15 | [E-17／26] | * [E-17／26] 先整批驗證、同交易寫入，再同步公開版；已提交不假裝回滾。 */ |

## backend/src/Services/ContentPublication.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 14 | [E-12／E-18] | * [E-12／E-18] DB 是編輯來源；成功產生的不可變快照是公開讀取版本。 */ |
| 41 | [E-18] | * [E-18] 呼叫端已提交內容；發布任何步驟失敗都須保留已保存的回應，不讓使用者重複新增。 */ |
| 48 | [S G18；E-35] | [S G18；E-35] 完整交易寫入同版公開索引，HTML成功後才清舊版。 |
| 91 | [S-SEO-17] | [S-SEO-17] 產生失敗／下架／排程切換期間不繼續公開舊版本。 |
| 112 | [E-25] | [E-25] 景點與電影共享 generation；本週邊界使無編輯的換週也會發布。 |
| 167 | [S F33；E-34] | * [S F33；E-34] 只取已發布記錄的真修改時間；不增加公開JSON API欄位或查詢。 |

## backend/src/Services/CurrentItinerary.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 17 | [S：G15／L15；U-08／E-27] | * [S：G15／L15；U-08／E-27] 目前行程為同一 aggregate；父表鎖與版本防止跨頁覆寫。 */ |

## backend/src/Services/FilmManagement.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 28 | [S：功能規格 21；U-02；E-25] | * [S：功能規格 21；U-02；E-25] 電影寫入的授權、版本與發布交易邊界。 */ |
| 74 | [U-16／E-25] | [U-16／E-25] 草稿占用開始時間；下架釋放。恢復發布即使省略場次也須重新比較。 |

## backend/src/Services/MapResolver.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 14 | [S：功能規格 20；E-19] | * [S：功能規格 20；E-19] 只回傳候選資料，不寫景點或下載 Google 照片。 */ |

## backend/src/Services/MediaAccess.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 11 | [E-18] | * [E-18] 草稿媒體僅授權管理員可預覽；公開媒體須被目前發布版本使用。 */ |

## backend/src/Services/MediaManagement.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 25 | [S：串接總覽 B12；E-26] | [S：串接總覽 B12；E-26] HTTP 與 CLI 共用同一 alt／來源邊界。 |

## backend/src/Services/MemberAccess.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 12 | [S：G15；E-27] | * [S：G15；E-27] 會員所有權不使用管理員 RBAC。寫入交易內重查停權／刪除狀態。 */ |

## backend/src/Services/MemberAttractions.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 12 | [E-27] | * [E-27] 會員私人資料只疊上成功公開投影；不從草稿補名稱、介紹或圖片。 */ |
| 55 | [E-33] | * [E-33] 整份行程只讀一次公開投影；固定ID次序鎖景點。 |

## backend/src/Services/MemberFavorites.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 15 | [S：E13／G15；E-27] | * [S：E13／G15；E-27] 明確收藏／取消，不用重送會反轉狀態的 toggle。 */ |

## backend/src/Services/MemberItineraries.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 15 | [S E15/G15/L15；E-33] | * [S E15/G15/L15；E-33] 本人aggregate：會員鎖、交易、版本與全量保存。 */ |
| 92 | [S L24；原型套用替換名稱／站點；E-33] | * [S L24；原型套用替換名稱／站點；E-33] 路線快照與即時資料相符才整份寫入。 */ |

## backend/src/Services/MerchantManagement.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 19 | [D：資料表建議 15；E-26] | * [D：資料表建議 15；E-26] 店家前置能力沿 offers 權限，不擴大既有權限矩陣。 */ |

## backend/src/Services/NewsManagement.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 25 | [S：功能規格 9／G22；E-17／26] | * [S：功能規格 9／G22；E-17／26] 對 CLI 與未來管理 HTTP 共用的消息寫入流程。 */ |

## backend/src/Services/OfferManagement.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 24 | [S：功能規格 G11／G23；E-17／26] | * [S：功能規格 G11／G23；E-17／26] 優惠的店家／條款／期間共用寫入規則。 */ |

## backend/src/Services/OfferMerchants.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 14 | [S G22／G23；E-31] | * [S G22／G23；E-31] HTTP應用服務；與CLI共用Editor，不在Controller組裝匯入檔。 */ |

## backend/src/Services/OperationsOverview.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 11 | [E-36] | * [E-36] 儀表板權限不等於內容權限；隱藏模組連統計也不查。 */ |

## backend/src/Services/PasswordHasher.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 9 | [S-SEC-09／E-08] | * [S-SEC-09／E-08] 不修剪密碼；雜湊使用 Argon2id。 */ |

## backend/src/Services/PasswordReset.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 64 | [S-SEC-09／E-03] | [S-SEC-09／E-03] HTTP 已送出統一回應，SMTP 時間與錯誤不洩漏帳號存在。 |

## backend/src/Services/PermissionManagement.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 20 | [E-01／S-027] | * [E-01／S-027] 個人矩陣的查閱、授權、版本與稽核；不承擔管理員 CRUD。 */ |

## backend/src/Services/PublicAttractions.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 9 | [S：功能規格 6/13；E-18] | * [S：功能規格 6/13；E-18] 公開 API 與 HTML 共用成功發布的資料版本。 */ |

## backend/src/Services/PublicFilms.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 10 | [S：功能規格 7/21；E-25] | * [S：功能規格 7/21；E-25] API 與 HTML 都讀同一成功發布快照。 */ |

## backend/src/Services/PublicHome.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 14 | [E-26] | [E-26] 只讀一次，與 SSR 相同選取結果；不逐一呼叫多個主題 API。 |

## backend/src/Services/PublicNeighbors.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 10 | [S H8／H10／H12／H29；E-31] | * [S H8／H10／H12／H29；E-31] 不即時查DB，避免鄰居與HTML跨版。 */ |

## backend/src/Services/PublicNews.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 41 | [E-26] | [E-26] 唯一跨主題相容入口；不把舊優惠頁偽裝成 city 文章。 |

## backend/src/Services/PublicRecommendedRoutes.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 24 | [S E15/L24；E-33] | * [S E15/L24；E-33] 待套用資訊只讀成功公開投影。 |

## backend/src/Services/PublicSearch.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 14 | [E-35] | * [E-35] 與發布／索引清理共用鎖，count與page不跨generation。 |

## backend/src/Services/PublishedPolicies.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 12 | [S：G16/G28；E-36] | * [S：G16/G28；E-36] 註冊只接受已實際發布的政策摘要，既有登入不受發布失敗阻斷。 */ |

## backend/src/Services/RecommendedRoutes.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 14 | [S E24/G24；E-32] | * [S E24/G24；E-32] 授權、交易、版本與發布流程集中；Controller 不操作資料庫。 */ |

## backend/src/Services/RoleTemplates.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 12 | [E-01／E-10] | * [E-01／E-10] 共用角色範本查閱與指派規則；建立／換角色仍由管理員流程控制交易。 */ |

## backend/src/Services/SeoPublication.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 12 | [S：G30/G32；E-28] | * [S：G30/G32；E-28] 快照只含頁面與目前可公開內容的 SEO，草稿覆寫不出界。 */ |

## backend/src/Services/SeoSettings.php

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 23 | [S：E32/G32/L32；E-28] | * [S：E32/G32/L32；E-28] 文件、覆寫、audit 同交易，提交後才產 HTML。 */ |

## docker/nginx/15-production-tls.sh

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 3 | [E-29] | [E-29] 重用共用路由；只在正式 image 轉為 TLS。容器內8082僅為 liveness。 |

## docker/nginx/default.conf

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 3 | [E-02] | [E-02] 網頁先使用 APP_URL 的同一個來源，避免 127.0.0.1 與 localhost |
| 9 | [E-26] | [E-26] query 僅映射到已發布分頁，不能把任意路徑拼進檔案來源。 |
| 39 | [E-29] | [E-29] FPM 不可達時仍非健康；不攔截 PHP 自己產生的 JSON 錯誤。 |
| 41 | [U-24／E-43] | [U-24／E-43] 公開網址不存在時回站內 404 頁；/api/ 不攔截，仍保留 JSON 錯誤契約。 |
| 43 | [E-08] | [E-08] 不將 query、Cookie、Referer 或原始 IP 寫入存取紀錄。 |
| 48 | [S：功能規格 G34 CSP；U-24] | [S：功能規格 G34 CSP；U-24] 全站腳本皆為同源外部檔，限制 script-src 為 self；JSON-LD／JSON 資料區塊不屬可執行腳本。 |
| 58 | [S E33/G33；E-34] | [S E33/G33；E-34] 文字／XML由前端publisher同版產生，PHP只負責既有gate。 |
| 67 | [E-18] | [E-18] 只有前端成功產生的版本可讀；下架／排程／失敗時不供應舊 HTML。 |
| 94 | [E-25] | [E-25] 電影與景點使用同一發布版本，歷史電影網址使用獨立對照。 |

## docker/php/www.conf

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 5 | [E-29] | ; [E-29] 不使用預設的 IP／request URI（可能含重設 token）；由 Nginx 記安全 path。 |

## frontend/assets/css/films.css

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [U-05／E-25] | [U-05／E-25] 公開電影沿用原型卡片／片單，處理任意海報比例與長介紹。 */ |

## frontend/assets/css/fonts.css

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | E-37 | E-37: self-hosted OFL fonts; common Unicode subset and complete fallback, no late font swap. */ |
| 6 | [E-39] | [E-39] Rare characters stay in the system fallback rather than loading a late face. */ |

## frontend/assets/css/phase10.css

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [S E24；U-05；E-32] | [S E24；U-05；E-32] 沿用原型表單、卡片；補足有序站點的鍵盤控制。 */ |

## frontend/assets/css/phase11.css

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [S E15；U-05；E-33] | [S E15；U-05；E-33] 沿用原型planner殼，補上多行程與站點編輯。 */ |
| 21 | [U：行程編排改善；E-37] | [U：行程編排改善；E-37] 統一表單字級與換行，操作留在同頁。 */ |
| 52 | [U：無簡介仍保持層級；E-37] | [U：無簡介仍保持層級；E-37] 類別與介紹連結各佔一列。 */ |

## frontend/assets/css/phase13.css

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [S E18/G18；E-35] | [S E18/G18；E-35] 原型搜尋列與卡片版型；增量控制項沿既有button樣式。 */ |

## frontend/assets/css/phase2.css

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [E-14] | [E-14] 只補充真實 API 的狀態與新欄位；原始版型由 style.css 提供。 */ |
| 24 | [U-05／E-14] | [U-05／E-14] 真實登入操作加入頂列後，保留原型「查看網站」連結的完整文字與箭頭。 */ |
| 50 | [U-05／S G34；E-29] | [U-05／S G34；E-29] 返回列表與真實登入操作並存時，窄螢幕頂列分行，頁名不擠成直排。 */ |
| 59 | [S：功能規格 E34/G34；E-14] | [S：功能規格 E34/G34；E-14] 保留原型色系，提高已串接表單的提示文字對比。 */ |
| 62 | [S：G34／串接總覽 B15；E-29] | [S：G34／串接總覽 B15；E-29] 原型小字在紙色背景仍須達一般文字對比。 */ |
| 65 | [S G34／E-29] | [S G34／E-29] 標題換行或瀏覽器放大時，封面由內容撐高，避免按鈕與底部註記重疊。 */ |
| 71 | [S G34／E-29] | [S G34／E-29] 已發布館舍圖片使用 inline background-image；遮罩獨立成層，避免圖片覆蓋遮罩。 */ |
| 73 | [S G34／串接總覽 B15；E-29] | [S G34／串接總覽 B15；E-29] 空白欄位仍須有可辨識的邊界；不改原型尺寸與紙色。 */ |
| 75 | [S G34／E-29] | [S G34／E-29] 裝飾不持續自動移動，避免閱讀行程時受干擾。 */ |
| 77 | [S G34／B15；E-29] | [S G34／B15；E-29] 到期提醒可重排、捲動；不改動或重建原表單。 */ |
| 96 | [E-36] | [E-36] 沿原型面板／快捷格線；長政策標題在窄螢幕換整行。 */ |
| 107 | [U：保留website頁首樣式；E-37] | [U：保留website頁首樣式；E-37] 僅保留中性載入／重試，不新增下拉選單。 */ |

## frontend/assets/css/phase3.css

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [U-05／E-18] | [U-05／E-18] 補齊正式表單狀態；沿用原型 admin-grid／panel／table 版型。 */ |
| 9 | [U-10／E-21] | [U-10／E-21] 狀態與展開操作共用一列；詳細內容獨立向下展開，避免 flex 對齊跳動。 */ |
| 44 | [S E20／E-24] | [S E20／E-24] 保留原型配色，讓範例選單與操作於窄畫面換行。 */ |
| 48 | [U-23／E-40] | [U-23／E-40] 原文來源與自家內容分開，窄版不截短Google原文。 */ |

## frontend/assets/css/phase4.css

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [U-05／E-25] | [U-05／E-25] 沿用原型表單與預覽；場次區塊依窄螢幕換行。 */ |
| 5 | [U-05／E-25] | [U-05／E-25] 手機標題旁的重新載入維持完整按鈕文字，避免被表單標題擠成逐字直排。 */ |

## frontend/assets/css/phase6.css

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [U-05／E-27] | [U-05／E-27] 沿用原型卡片；僅補充本人資源的操作／錯誤狀態。 */ |
| 7 | [S：G34／E-27] | [S：G34／E-27] 真實長名稱不能撐大 grid 的最小內容寬度或被外框裁切。 */ |

## frontend/assets/css/phase7.css

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [U-05／E-28] | [U-05／E-28] 保留原型 admin-panel／details，僅補必要狀態與長值換行。 */ |

## frontend/assets/css/phase9.css

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [S E22/E23/G34；E-31] | [S E22/E23/G34；E-31] 延續原型面板，只補必要的新控制項。 */ |

## frontend/assets/css/public-content.css

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [E-26] | [E-26] 保留原型卡片與詳情；只補分頁、狀態及安全正文需要的樣式。 */ |

## frontend/assets/css/style.css

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [S G34／E-29] | 映畫竹塹：全站色彩、排版與共用元件；[S G34／E-29] muted 與封面遮罩提供足夠對比，保留原型版型。 */ |
| 2 | [U：載入穩定性；E-37] | [U：載入穩定性；E-37] 本機字型 optional：未及首屏時保留系統字型，不晚換造成跳動。 */ |
| 5 | [U-21] | {box-sizing:border-box}html{scrollbar-gutter:stable;-webkit-text-size-adjust:100%;text-size-adjust:100%;scroll-behavior:smooth;scroll-padding-top:88px}body{margin:0;background:var(--paper);color:var(--ink);font-family:va |
| 222 | [U：卡片下緣對齊；E-37] | [U：卡片下緣對齊；E-37] 無摘要或摘要多寡不改變底部資訊／操作列的對齊。 */ |

## frontend/assets/css/ui-consistency.css

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [U-24／E-43] | [U-24／E-43] 介面一致性：最後載入，只統一字級、控制項高度與區塊標題，不改變版面結構或業務流程。 |
| 57 | [U-24] | [U-24] 圖形驗證碼原圖 230×64，原先縮成 115×32 數字僅約 4px；改與輸入框同高並保持比例可辨識。 */ |
| 60 | [U-24] | [U-24] 會員選單：桌面維持頁首連結；手機頁首只留名稱，收藏／更改密碼／登出移入漢堡選單。 */ |
| 70 | [U-24] | [U-24] 行程尚無站點時不保留 180px 空白，空狀態緊接儲存列顯示。 */ |

## frontend/assets/js/admin.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 22 | [U-24] | [U-24] 瀏覽器原生驗證攔下下一次送出時，清掉上一輪伺服器錯誤，避免畫面同時顯示已過時的錯誤。 |

## frontend/assets/js/api/attractions-api.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [S：功能規格 13/19/20；D：API草案；E-18] | [S：功能規格 13/19/20；D：API草案；E-18] 正式景點／媒體／解析共用介面。 |

## frontend/assets/js/api/content-admin-api.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 3 | [S H22/H23；E-31] | [S H22/H23；E-31] 只有兩個明確主題，不能以任意名稱組裝管理端點。 |

## frontend/assets/js/api/films-api.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [S：功能規格 21；E-25] | [S：功能規格 21；E-25] 電影 API；媒體沿用同一受控上傳端點。 |

## frontend/assets/js/api/itineraries-api.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 3 | [S H15；E-33] | [S H15；E-33] 本人資源只由session辨識，沒有memberId輸入。 |

## frontend/assets/js/api/member-resources-api.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [S：E13／G15；E-27] | [S：E13／G15；E-27] 本人資源薄封裝；沿用既有 Cookie、CSRF 與錯誤邊界。 |

## frontend/assets/js/api/recommended-routes-api.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 4 | [S H14/H24；E-32] | [S H14/H24；E-32] 路線與候選景點使用 routes 權限，無須景點管理權。 |

## frontend/assets/js/auth.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 7 | [E-27] | [E-27] 登入／註冊切換仍保留經白名單檢查的收藏或行程待辦；不自動執行。 |
| 35 | [U-13／E-14] | [U-13／E-14] 更改密碼頁可登出換帳號，與提交共用互斥狀態。 |
| 53 | [E-07] | [E-07] 一次性憑證只在記憶體；先清掉網址 fragment，再發任何網路請求。 |
| 57 | [U-24] | [U-24] 使用者手動換驗證碼時清除舊答案的欄位錯誤；提交失敗後自動換新圖但保留錯誤說明。 |
| 96 | [E-14] | [E-14] 包含 Enter 的提交入口共用此鎖；成功後離頁／重新登入，不重送。 |
| 133 | [U-24] | [U-24] 驗證碼一次性；註冊失敗後舊 token 不可再用，自動換新圖片避免使用者重送必定失敗。 |
| 151 | [U-13] | [U-13] 更改前可返回；成功撤銷 session 後顯示重新登入。 |

## frontend/assets/js/components/admin-preview.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 5 | [U-06／E-16] | [U-06／E-16] 原版左下選單，資料與切換皆由 API 提供；不儲存假身分。 |

## frontend/assets/js/components/attraction-content-preview.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 4 | [U-23／E-40] | [U-23／E-40] 明確按預覽才請求後端共用政策，不隨每次打字送出Google請求。 |

## frontend/assets/js/components/attraction-picker.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 4 | [U：行程操作改善；E-37] | [U：行程操作改善；E-37] 原頁選地點，不導航離開或丟棄尚未儲存的編排。 |

## frontend/assets/js/components/attraction-samples.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 7 | [S：功能規格 E20／E-24] | [S：功能規格 E20／E-24] 讀取 DB 範例、確認後試填；任何情況都不自動保存。 |

## frontend/assets/js/components/content-body-editor.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 4 | [S G10/G22；E-31] | [S G10/G22；E-31] 原生工具列只協助編輯；伺服器Sanitizer仍是唯一安全邊界。 |

## frontend/assets/js/components/content-cards.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [E-12] | [E-12] 重現原始 website/js/app.js 的唯讀卡片結構；資料以 textContent 安全輸出。 |
| 22 | [S：G34／E-27] | [S：G34／E-27] 圖片區含收藏按鈕，使用 group 避免 img 語意隱藏互動子元素。 |
| 32 | [U-11／E-18] | [U-11／E-18] 選填內容留空時省略空段落及分隔符號，不自動補入介紹。 |

## frontend/assets/js/components/dialog.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 6 | [S G34／串接總覽 B15；E-29] | [S G34／串接總覽 B15；E-29] 明確循環兩端，避免原生 Tab 導航移到瀏覽器工具列。 |

## frontend/assets/js/components/google-place-link.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 4 | [U-10／E-21] | [U-10／E-21] 本次確認只屬表單命令；已保存內容的比較仍由後端政策執行。 |
| 24 | [U-10] | [U-10] 人工建立不顯示關聯區；技術識別碼及解除操作按需展開。 |

## frontend/assets/js/components/google-summary.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 8 | [U-23／E-40] | [U-23／E-40] 原文只留在目前DOM；不覆寫網站介紹、SEO或任何儲存欄位。 |

## frontend/assets/js/components/member-itinerary.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 3 | [D：C20；E-27] | [D：C20；E-27] 私有快照以文字輸出；下架後不從草稿重建地點資訊。 |

## frontend/assets/js/components/offer-merchants.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 38 | [U-24] | [U-24] 店家面板不是獨立 form；伺服器欄位錯誤逐欄顯示於對應輸入下方，不只顯示「請檢查輸入內容」。 |

## frontend/assets/js/components/opening-hours-editor.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 4 | [U-09／E-20] | [U-09／E-20] 單一編輯器處理手動建檔、讀回及 Google 候選；最後規則由 API 驗證。 |

## frontend/assets/js/components/place-reference.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 3 | [U-24] | [U-24] 候選卡先顯示來源；確認帶入後由共用匯入流程補齊空白欄位。 |

## frontend/assets/js/components/screening-editor.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 5 | [S：功能規格 21；U-16] | * [S：功能規格 21；U-16] 編輯表單集合，按儲存才寫入；ID 與輸入不因重新編號遺失。 */ |

## frontend/assets/js/components/search-card.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 3 | [S E18/L18；E-35] | [S E18/L18；E-35] 沿原型search-hit結構的純卡片，不載入示範陣列。 |

## frontend/assets/js/components/seo-fields.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 5 | [S：E32／G34；E-28] | [S：E32／G34；E-28] 所有值以 DOM property 寫入，不把名稱／驗證 token 當 HTML。 |

## frontend/assets/js/components/session-prompt.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 3 | [S G34／B15；E-29] | [S G34／B15；E-29] 專用模態框不取代表單，也不把未儲存內容寫入瀏覽器儲存空間。 |
| 7 | [S G34／B15；E-29] | [S G34／B15；E-29] 初始焦點讀出提示與說明；Tab 才進入操作，不只先讀按鈕。 |
| 15 | [S G34／L34；E-29] | [S G34／L34；E-29] 錯誤與處理進度分開，錯誤可獨立播報及由密碼欄關聯取得。 |

## frontend/assets/js/components/trip-stop-editor.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 3 | [S E15；原型站點卡片；E-33] | [S E15；原型站點卡片；E-33] 值只經textContent／input.value，不插入使用者HTML。 |

## frontend/assets/js/core/api-client.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [E-02／E-13] | [E-02／E-13] Cookie、CSRF、錯誤與逾時處理只有一份；狀態不存 localStorage。 |

## frontend/assets/js/core/attraction-sample.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [S：功能規格 E20／E-24] | [S：功能規格 E20／E-24] 試填只改基本資料；不複製 id、slug、圖片、發布或 Google 關聯。 |

## frontend/assets/js/core/content-neighbors.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 3 | [S E29/G29；P renderArticleNeighbors；E-31] | [S E29/G29；P renderArticleNeighbors；E-31] 只呈現同版後端結果，不自行重排或混入草稿。 |

## frontend/assets/js/core/content-view.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [E-26] | [E-26] Node 發布與瀏覽器共用字元編碼、日期及圖片外觀。 |

## frontend/assets/js/core/current-trip-state.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [E-27] | [E-27] 目前行程只管理已保存版本；不在 localStorage 建立另一份資料。 |

## frontend/assets/js/core/film-view.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [S：功能規格 7/21；U-05／16；E-25] | [S：功能規格 7/21；U-05／16；E-25] Node 發布與瀏覽器共用原型電影渲染。 |
| 13 | [U-05] | [U-05] 原型首頁與片單的海報各有不同遮色層，詳情保持原圖。 |
| 43 | [U-05／S：功能規格 7/21] | [U-05／S：功能規格 7/21] 沿用原型的公告段落與類型／場次資訊列；多場次逐列呈現真實資料。 |

## frontend/assets/js/core/home-view.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 3 | [S：功能規格 5；E-26] | [S：功能規格 5；E-26] 原型首頁三列，活動時間缺少時展示發布日期，不捏造活動時間。 |

## frontend/assets/js/core/list-seo.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 4 | [S：E30/E32/L32；E-29] | [S：E30/E32/L32；E-29] 初載、分類、分頁共用發布時的 SEO 規則及同版公開設定。 |
| 28 | [S G31／L33；E-34] | [S G31／L33；E-34] 更新伺服器已有graph，不另產一套或保留舊分類／頁碼。 |

## frontend/assets/js/core/member-actions.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 9 | [E-27] | [E-27] 只處理受管景點 UUID；原型陣列索引不直接當正式資料識別。 |
| 137 | [S E15；E-33] | [S E15；E-33] 到planner確認選定行程後才保存；匿名由共用登入安全返回。 |

## frontend/assets/js/core/member-resource-state.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [E-27] | [E-27] 收藏狀態協調器：API／身分為明確依賴，可直接測試逾時、競態與換帳號。 |

## frontend/assets/js/core/news-view.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 5 | [U-05] | [U-05] 保留原型卡片 YYYY / MM.DD 的日期排版，日期仍依台北時區。 |
| 21 | [S：E9/E30；E-29] | [S：E9/E30；E-29] 發布 HTML 與瀏覽器分類／分頁共用主題文字。 |

## frontend/assets/js/core/opening-hours.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [U-09／E-20] | [U-09／E-20] 人工輸入與 Google 候選共用七天模型；不解析語系文字。 |

## frontend/assets/js/core/password-policy.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [E-08] | [E-08] 使用 Unicode 字元數，與 PHP mb_strlen 一致；不修剪密碼。 |
| 7 | [U-15] | [U-15] 確認密碼必填，保留空白及 Unicode 的原始內容比較。 |

## frontend/assets/js/core/pending-writes.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [E-41] | [E-41] 等待已明確操作的寫入，不排入離線重送、不保存私人資料。 |

## frontend/assets/js/core/place-candidate.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [U-10／E-21] | [U-10／E-21] 候選套用只有一條路徑；不保留上個地點的行政區，不猜測缺值。 |
| 8 | [U-24] | [U-24] 匯入時逐欄補空白；已有人工內容不覆寫，沒有 Google 簡介不填值。 |

## frontend/assets/js/core/public-list-controller.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 4 | [E-26] | [E-26] 消息／優惠只共用換頁、URL、競態、錯誤重試與焦點；主題呈現由呼叫端傳入。 |

## frontend/assets/js/core/route-view.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 3 | [S E14/G14；U-05] | [S E14/G14；U-05] 保留原型 route-card，補上實際有序站點及時間。 |

## frontend/assets/js/core/screening-time.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [U-16／E-25] | [U-16／E-25] 表單日期時間屬台北；不依操作電腦時區猜測日期。 |

## frontend/assets/js/core/search-state.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [S G18；E-35] | [S G18；E-35] URL與非同步結果只有一份狀態；過時成功／錯誤皆不能覆寫新搜尋。 |

## frontend/assets/js/core/seo-resolver.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [S：SEO D5–D9／E16；E-28] | [S：SEO D5–D9／E16；E-28] 純資料解析，供建置、發布與管理預覽共用。 |
| 15 | [S SEO E7／G33；E-34] | [S SEO E7／G33；E-34] 發布與爬蟲索引共用保護邊界，不靠robots授權。 |

## frontend/assets/js/core/session-deadline.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [E-29] | [E-29] 使用相對的單調時鐘；不受使用者電腦時鐘與伺服器時區差異影響。 |

## frontend/assets/js/core/session-events.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [S G34／B15；E-29] | [S G34／B15；E-29] 僅在記憶體保存期限與本人識別；不保存密碼、Cookie 或 token。 |

## frontend/assets/js/core/session.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 6 | [E-04] | [E-04] 僅允許已建置的站內頁面；不把 query 直接當 redirect URL。 |
| 22 | [U-13／E-04] | [U-13／E-04] 已登入者離開登入／註冊頁，不增加首次登入改密碼門檻。 |
| 38 | [S E27／E-14] | [S E27／E-14] 檢視與新增互相獨立；共用登入、側欄與表單離開位置，不擴大 API 權限。 |
| 110 | [U-24] | [U-24] 手機寬度把會員連結放進漢堡選單，避免與品牌標誌擠在 65px 頁首而折行。 |

## frontend/assets/js/core/site-information.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [S：L28；E-36] | [S：L28；E-36] 靜態認證／會員頁只更新共用識別；不覆寫各頁 SEO。 |

## frontend/assets/js/core/structured-data.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 5 | [S E31/G31／SEO E13；E-34] | [S E31/G31／SEO E13；E-34] 只取可見內容白名單；不傳整份DB或SEO覆寫作正文。 |
| 34 | [U-01／E-34] | [U-01／E-34] 日期欄為台北日曆日，validTo包含當日，不造價格／庫存。 |

## frontend/assets/js/core/trip-editor-state.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [S E15/G15；E-33] | [S E15/G15；E-33] 草稿與已保存版本分開；所有權改變會清除私人草稿。 |

## frontend/assets/js/core/ui.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 31 | [U-09／E-20] | [U-09／E-20] 複合表單（例如整週營業時間）也須顯示具體錯誤並可取得焦點。 |
| 32 | [S：G34；E-29] | [S：G34；E-29] 地圖解析等獨立欄位群組亦可共用，不清除其他表單區塊的錯誤。 |
| 94 | [E-14] | [E-14] 認證狀態刷新可重複呼叫，但相同導覽只綁定一次。 |
| 97 | [S 功能規格 G34／E-29] | [S 功能規格 G34／E-29] 被裁切／移出畫面的手機選單不可留下隱形 Tab 停駐點。 |
| 129 | [U-24] | [U-24] 新增／編輯成功後會導回列表；以本分頁 sessionStorage 暫存一次性成功訊息，列表載入後顯示。 |

## frontend/assets/js/core/utc-display.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [E-41] | [E-41] DB的無offset時間是UTC；顯示一律轉台北，不依訪客裝置時區猜測。 |

## frontend/assets/js/data/prototype.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [E-12] | [E-12] 原始交付的唯讀展示資料；正式內容 API 於後續階段實作。 |

## frontend/assets/js/font-boot.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [U-22／E-39] | [U-22／E-39] Only choose a ready font before the first paint; never swap later. |

## frontend/assets/js/pages/admin-form.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 24 | [E-14] | [E-14] 固定管理員會以保護說明取代表單；先保留控制項參照，避免讀取已移除節點。 |
| 27 | [E-14] | [E-14] 讀取失敗、重試與保存共用狀態；不能靠 finally 無條件開啟保存。 |
| 37 | [U-25] | [U-25] 缺少編號時直接說明，不呈現底層 API 錯誤。 |
| 40 | [E-14] | [E-14] 只採用最後一次讀取，避免較慢的舊回應覆蓋新版本。 |
| 55 | [E-14] | [E-14] 新資料已取代草稿，舊欄位錯誤也須清除。 |

## frontend/assets/js/pages/attraction-form.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 72 | [E-18] | [E-18] 確認放棄修改後，待上傳檔案也必須與伺服器資料一起重置。 |
| 137 | [S：E34/G34；E-29] | [S：E34/G34；E-29] 還原可操作狀態後再聚焦，API 原因以純文字關聯解析欄。 |

## frontend/assets/js/pages/attractions.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [S：功能規格 13；E-18] | [S：功能規格 13；E-18] 初次內容由 HTML 提供，篩選與分頁使用公開 API。 |
| 42 | [E-18] | [E-18] 美食原型的分類沒有數字 span；共用載入不能因此中斷。 |

## frontend/assets/js/pages/favorites.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 7 | [E-27] | [E-27] 私人收藏與公開卡片分開；不可用時不從草稿補出名稱或圖片。 |

## frontend/assets/js/pages/films.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 5 | [E-25] | [E-25] 初始內容由相同 generation 的 HTML 提供；換頁失敗不回填原型資料。 |

## frontend/assets/js/pages/general-settings.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 7 | [S：E28/G28；E-36] | [S：E28/G28；E-36] 與 SEO 共用名稱／摘要；不同文件有獨立衝突保護。 |
| 31 | [U-24] | [U-24] 兩區共用網站名稱／摘要與版本；SEO 區儲存後，一般區若無未存修改即自動重讀，避免下次儲存被誤判為他人更新。 |

## frontend/assets/js/pages/member-directory.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 6 | [S：E25/G25；E-36] | [S：E25/G25；E-36] 唯讀查詢；回應已遮罩，DOM 不保存完整個資。 |

## frontend/assets/js/pages/permissions.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 79 | [E-14] | [E-14] 保存使用提交時的對象與版本；切換後不覆蓋另一人的矩陣。 |

## frontend/assets/js/pages/planner.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 10 | [S E15/L24；E-33] | [S E15/L24；E-33] 完整編輯器：server版本與未保存草稿分離，不用localStorage當正式資料。 |

## frontend/assets/js/pages/public-details.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [E-12] | [E-12] 原始詳情頁與上一篇／下一篇版型，使用交付的唯讀內容。 |

## frontend/assets/js/pages/public-index.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [S G34／B15；E-29] | [S G34／B15；E-29] 分類瀏覽與名稱尋找是兩種入口；只搜尋同一公開 HTML 的標題，不取私人或草稿資料。 |

## frontend/assets/js/pages/route-form.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 6 | [S E24/G24；E-32] | [S E24/G24；E-32] 陣列次序是站點順序；按鈕同時支援滑鼠與鍵盤，無需拖曳。 |

## frontend/assets/js/pages/seo-settings.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 8 | ['titleSuffix','SEO 標題後綴',160] | const defaultFields=[['siteName','網站名稱',160],['description','全站預設摘要',500,'textarea'],['titleSuffix','SEO 標題後綴',160], |
| 12 | [['title','SEO 標題（建議約 30 中文字）',160] | const overrideFields=[['title','SEO 標題（建議約 30 中文字）',160],['description','搜尋摘要（建議 70–100 中文字）',500,'textarea'], |
| 50 | [U-24] | [U-24] 保護頁為固定靜態 shell，發布不重新套用覆寫；全部欄位鎖定，避免預覽顯示實際不會生效的標題。 |
| 105 | [U-24] | [U-24] 一般設定儲存後版本前進；SEO 區無未存修改時自動重讀，有修改則提示重新讀取以免覆蓋共用欄位。 |
| 122 | [E-28] | [E-28] 請求已結束，先恢復控制項，再將焦點交給第一個錯誤欄位。 |

## frontend/assets/js/public.js

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [E-12／E-27] | [E-12／E-27] 共用導覽與未交付頁的展示；受管內容與會員資料交由各模組的真實 API。 |
| 10 | [S：G29；U-24] | [S：G29；U-24] 上一篇／下一篇縮圖為靜態發布 HTML，圖片失效時與主圖同樣改用替代圖，不留破圖。 |

## frontend/pages/404.html

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 14 | [U-24／E-43] | [U-24／E-43] 不存在、已下架或尚未發布的公開網址回 404 時顯示站內頁，避免訪客看到 API JSON 或伺服器預設頁而無法返回。 --> |

## frontend/pages/admin/place-add.html

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 23 | [S：功能規格 E20] | [S：功能規格 E20] 原型既有景點試填；讀取 DB，不使用 localStorage。 --> |
| 37 | [U-09／E-20] | [U-09／E-20] 手動與 Google 候選共用每週編輯器；舊 hours 只保留相容摘要。 --> |
| 40 | [S：功能規格 G6／E-24] | [S：功能規格 G6／E-24] 電話選填，格式由 API 驗證；不推測國碼。 --> |

## frontend/pages/admin/place-edit.html

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 34 | [U-09／E-20] | [U-09／E-20] 手動與 Google 候選共用每週編輯器；舊 hours 只保留相容摘要。 --> |
| 37 | [S：功能規格 G6／E-24] | [S：功能規格 G6／E-24] 電話選填，格式由 API 驗證；不推測國碼。 --> |

## frontend/scripts/build.mjs

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 3 | [E-12] | [E-12] 固定 HTML 在前端建置時組合；PHP 不輸出任何畫面。 |
| 29 | [U-05／E-29] | [U-05／E-29] 內容新增／編輯頁保留原型的上方返回列表；其他後台仍查看網站。 |
| 42 | [U-03 / E-12] | [U-03 / E-12] 保留原型各頁導覽的 active 樣式；food/search/auth 原版沒有選中項目。 |
| 56 | [U-24／E-43] | [U-24／E-43] 一致性樣式最後載入，統一各階段樣式表遺留的字級與欄位高度差異。 |
| 83 | [S：SEO E7/E17；E-28] | [S：SEO E7/E17；E-28] 固定／保護頁與發布頁共用 head，任何 query 均不含私人資料。 |
| 89 | [S G34／B15；E-29] | [S G34／B15；E-29] 各頁共用期限監看；API 事件可在入口載入前暫存於記憶體。 |
| 100 | [S E33；E-34] | [S E33；E-34] 真robots由成功publication供應；build不放過時sitemap網址。 |

## frontend/scripts/crawler-index.mjs

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 18 | [S SEO E14；E-34] | [S SEO E14；E-34] 集合／固定頁沒有可靠的內容修改時間，不用build時間冒充。 |

## frontend/scripts/font-head.mjs

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [U-22／E-37] | [U-22／E-37] 在主樣式前發現兩套字型，不經CSS @import再發起字型樣式請求。 |

## frontend/scripts/home-error-view.mjs

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [S 功能規格 G5/L5；E] | [S 功能規格 G5/L5；E] 同版發布不可用時，保留各模組錯誤狀態；不展示原型或舊內容。 |

## frontend/scripts/language-passages.mjs

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [S：功能規格 G34／串接總覽 B15；E-29] | [S：功能規格 G34／串接總覽 B15；E-29] 原型英文裝飾文案保留，只補讀屏語言。 |

## frontend/scripts/policy-pages.mjs

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 3 | U-25 [S：G16/G28；E-36] | [S：G16/G28；E-36] 純文字政策永不當作 HTML 執行；後台未填時保留頁面預設條款（U-25 改為面向大眾的預設文案）。 |

## frontend/scripts/public-content-index.mjs

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [S G34／B15；E-29] | [S G34／B15；E-29] 只使用已通過公開白名單的同一發布快照；不建立另一份內容資料。 |

## frontend/scripts/public-news.mjs

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [S：功能規格 9；E-17／26] | [S：功能規格 9；E-17／26] 各分類所有頁均由成功快照產生，query 重整不退回第一頁。 |

## frontend/scripts/public-offers.mjs

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 6 | [S：功能規格 11；E-26] | [S：功能規格 11；E-26] 預設有效列表與仍發布的歷史詳情分開處理。 |

## frontend/scripts/publish.mjs

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 3 | [E-12／E-18] | [E-12／E-18] 由 PHP CLI／API 交付已驗證資料；只在前端產生 HTML。 |
| 36 | [U-01／E-18] | [U-01／E-18] 資料庫保存 UTC，網站日期依台北時區顯示。 |
| 38 | [S：G34／E-27] | [S：G34／E-27] 含收藏控制的圖片區需保留互動子元素於無障礙樹。 |
| 40 | [U-11／E-18] | [U-11／E-18] 選填文字不補位；與瀏覽器卡片一致，省略空段落與多餘分隔符號。 |
| 131 | [S E7／L7；E-29] | [S E7／L7；E-29] 依穩定欄位更新週期，保留語言標示，不依賴原型純文字。 |
| 134 | [S：串接總覽 B7／E6/L6；E-29] | [S：串接總覽 B7／E6/L6；E-29] 與館舍詳情共用同一快照和週表，不另存開放時間。 |
| 140 | [U-05] | [U-05] 採原型電影專用頁殼，保留「本週映演」導覽，不沿用城市散策頁。 |
| 160 | [U-24] | [U-24] 與原型及後台 SEO 目錄同一標題；舊字串以品牌開頭又被加後綴，造成「映畫竹塹」重複。 |
| 165 | [E-28] | [E-28] 新增的非內容固定公開頁也進同一 generation；保護頁使用安全靜態 shell。 |
| 171 | [S E33/G33；E-34] | [S E33/G33；E-34] 索引與HTML全部成功才啟用；不掃dist或舊generation。 |

## frontend/scripts/seo-head.mjs

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 5 | [S：E30／SEO E17；E-28] | [S：E30／SEO E17；E-28] HTML 只有一個標籤擁有者；所有動態值均編碼。 |
| 11 | [S G31；E-34] | [S G31；E-34] build原型與舊graph不能疊加到真實已發布資料。 |

## frontend/scripts/structured-data.mjs

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [E-34] | [E-34] Node與Browser共用相同factory及安全JSON，保留發布工具入口。 |

## frontend/templates/dialog.html

| 行 | 代碼 | 原註解 |
| --- | --- | --- |
| 1 | [S：功能規格 G34／串接總覽 B15；E-29] | [S：功能規格 G34／串接總覽 B15；E-29] 重要確認依 WAI alertdialog 關聯標題與說明；仍由原生 showModal 管理模態及取消焦點。 --> |

