# P1 整合驗收

日期：2026-10-31。使用者另行要求驗收 P1；受驗程式為階段十二合併版 `76fce26`，本輪未修改應用程式、資料表、API 或環境設定。原始 Excel 為基準，website 為介面與操作參考。

**本機 P1 工程範圍通過，本輪未發現新的功能失敗。** 正式網域尚未決定，依 U-21 保留 Search Console／Bing、正式 Google 抓取與分享平台驗證為上線交接；不宣稱正式環境、第三方 AA 認證或搜尋收錄已通過。

## Excel 逐列結果

來源為原始「功能規格」各列 E／G／L 的功能、限制與驗收條件；J 欄共 11 列 P1。資料欄位清單不自行推定為必填，資料表建議仍按草案性質使用。

| 列／頁面 | 本輪驗證 | 結果 |
| --- | --- | --- |
| 8，電影詳情 | 真實共用 neighbors、直接獨立頁、已發布排序與邊界；電影詳情三尺寸與原型比對。 | 本機通過 |
| 10，消息詳情 | 同分類前後篇、安全正文、發布檢查；消息編輯後公開摘要同步，Tab／Enter 開啟同分類下一篇，無燈箱。 | 本機通過 |
| 12，優惠詳情 | 店家、期間、條款與過期狀態；真 API 驗過期排除與明確詳情狀態，Browser 保存後直接讀到新條款。 | 本機通過 |
| 14，推薦列表 | 真 API／HTML 相同順序、下架站點剔除、全無有效站點隱藏；Browser 新建已發布路線後出現在前台。 | 本機通過 |
| 15，會員旅行計畫 | 多份本人 CRUD、時間／備註／排序、重複與不存在景點、外人讀寫、並行版本及 P0 相容；Browser 套用後編輯、保存、登出重新登入，日期／時間／備註完整載回。 | 本機通過 |
| 22，活動／消息管理 | 新增／修改／刪除、草稿／發布／未到排程、分類、CSRF／權限／409；Browser 無效結束時間有 inline 錯誤、aria 關聯與欄位焦點，修正可保存。 | 本機通過 |
| 23，優惠管理 | 獨立集合、期間、條款、有效店家、共享店家同步、權限與並行；Browser 空白條款觸發原生必填並聚焦，補齊可保存。 | 本機通過 |
| 24，推薦路線管理 | 真 CRUD／排序、至少一個有效站點、刪除關聯釋放、發布快照與最新路線確認套用；Browser 後台建立、公開列表與會員套用串通。 | 本機通過 |
| 29，圖片與前後篇 | 四主題已發布 neighbors、無相鄰不造空連結、安全可辨名稱；詳情圖片替代文字與頁面導覽比對，Browser 下一篇焦點有 solid outline。 | 本機通過 |
| 31，JSON-LD | 真 renderer／XSS／私頁／草稿／noindex／可見欄位，46 份 graph 以官方詞彙驗證。會員 planner 實測 noindex 且無 JSON-LD。 | 本機通過，正式抓取依 U-21 交接 |
| 33，robots／sitemap | 同 generation、canonical／200、改名301／下架404、可信 lastmod、失敗503與復原、50,001 URL 分片測試；清理後兩入口逐頁覆核。 | 本機通過，正式平台依 U-21 交接 |

SEO 工作表 B13:F14 與功能規格 31／33 一起核對；原正式 canonical／OG／Twitter／驗證 token 能力沿既有設定流程。七種內容 graph 在階段十二已由官方 Schema.org Validator 程式碼模式驗證零錯誤／警告，本輪程式未變更，沿用該有效證據，沒有冒稱本輪重新向官方平台提交。

## 本輪實際執行

| 檢查 | 結果 |
| --- | --- |
| 完整前端 Node／renderer | 224 項通過，零失敗／跳過。 |
| PHPUnit | 107 tests／356 assertions 通過。日誌中的 health／CSRF 異常為刻意的失敗案例，測試結果無失敗。 |
| PHPStan 10／PHPat | 無錯誤。 |
| P1 真 HTTP／MariaDB／OpenAPI | 階段九116＋十58＋十一81＝255項；70＋45＋66＝181筆契約回應，零契約錯誤。 |
| 索引／同版發布 | 369項、70筆HTTP、46份官方詞彙 graph 通過；原 SEO 設定與覆寫逐列還原。 |
| 路由與 DB 實際盤點 | 104 PHP API＋3 Nginx爬蟲GET＝107 HTTP契約，實作與契約差集皆空；33表327欄／19 migration。 |
| 清理後兩站完整性 | 各68固定頁／19直接引用資產／87筆HTTP，零異常；sitemap主站31頁、測試站35頁，無JS逐頁200／canonical／OG／Twitter／JSON-LD官方詞彙檢查均通過。 |
| Browser 重排／原型 | 18個頁面，各1440／390／320；目前網站54筆＋原型54筆＝108筆。實測 innerWidth 符合目標，無整頁水平溢出，main 均為一個。 |
| Browser 實際操作 | 消息時間422／焦點、修正保存與公開同步、優惠條款必填及保存、路線建立與公開套用、登入返回意圖、確認框反向Tab、行程重新登入持久保存、手機選單Escape、前後篇Tab／Enter。 |
| 原始交付／既有契約 | 原始69檔逐位元相同；階段十一原104個PHP操作與全部components不變。 |

另重讀「資料表建議」C14:D20／C24:D25，從真 MariaDB 匯出相關9表118欄核對：news_posts、merchants、offers、routes、route_attractions、itineraries、itinerary_items、site_settings、content_seo。路線的 suggested_time／note／sort_order、行程的 planned_time／note／snapshot_name、發布與版本欄位均有對應；優惠 publish_at 是既有 published_at 建議的命名對應。唯一性、本人限制、日期及交易限制由上述真整合案例覆蓋，沒有依建議額外新增必填。逐欄及工程欄位理由仍見[資料表對照](../DATABASE-SPECIFICATION-MAP.md)與各模組指南。

## website 比對與差異判定

公開九頁：電影 `films/shape-of-light.html`、消息 `news/park-concert.html`、優惠 `offers/autumn-coffee.html`、景點 `places/dongmen-market.html`、routes、planner、events、offers、cinema。後台九頁為 news／offers／routes 各列表、新增、編輯。版型重排以實際頁面核對；會員 planner 的初次未登入導向不列為行程版型通過，登入並載回資料後重新採證替換。

- 保留原型的色系、側欄／頂列、面板、主視覺、詳情雙欄與前後篇卡片；手機以原有重排方向呈現。不是以截圖像素相同作判斷。
- 內容來自 DB／發布快照，原型則使用示範資料／localStorage。路線為空時顯示空狀態，不自動補原型的三條示範路線；建立並發布後真路線可見。
- 原型 `offers/autumn-coffee.html` 實際顯示「或者工藝櫥窗｜散步小禮」，目前依 slug 顯示「秋日咖啡散步地圖限定」。這是示範檔案內容不一致，不將錯誤內容複製回目前網站；優惠圖片及標題自然不同。
- 消息正文／圖片／活動日期、優惠店家／條款、路線站點／順序、會員多份行程與錯誤提示，是 Excel 要求及既有 E-31～E-34 實作所需的增量，表單高度與操作數量因此不與簡化原型完全相同。新增工程欄位維持來源標記，不升格為 Excel 業務必填。
- 會員登入、本人伺服器保存取代原型 localStorage；真正權限與唯讀模擬取代原型任意切換角色。這些是既有規格與使用者指示，不算介面回歸。

本輪量測覆蓋上述頁面的版型與控制項重排；編輯頁亦經真資料操作驗證消息／優惠保存，不把每張載入中截圖說成完整填值驗收。全站圖文來自不同內容，不能宣告與原型每個示範字句／圖片完全一致。

## 安全與 AA 範圍

使用 security-audit 檢查本次 P1 的 Service 授權、MemberAccess／findOwned 本人限制、寫入交易與版本；真 MariaDB 案例驗證匿名、錯誤模組權限、另一會員、CSRF、409、並行與下架。應用沒有新增認證或權限變更；測試僅暫切自有fixture權限，套件結束還原並最後停用清理。未重跑全量 Semgrep／Gitleaks／映像掃描，不把沿用基線說成本輪全站滲透測試。

介面依 [Web Interface Guidelines](https://raw.githubusercontent.com/vercel-labs/web-interface-guidelines/main/command.md) 與既有 [P0 AA 基線](P0-AA-FINAL-20261029.md) 檢查標籤、語意、狀態、可見焦點與重排。實測錯誤欄位 aria-invalid／aria-describedby、優惠原生必填、確認框反向Tab到「確認」、手機Escape回到選單按鈕、前後篇鍵盤導覽。沒有啟動主機 NVDA，也沒有宣稱本輪重跑完整50準則、真200%／400%或取得第三方AA證明。圖片選擇器沿用階段九已驗證且本輪未變動的共用媒體能力；不列為本輪重新選檔。

## 清理、證據與交接

只使用 `yinghua_test` 命名自有 fixture；本輪額外路線、消息／活動、優惠與店家已清除，行程／收藏／路線／景點／電影／有效帳號／有效session／測試trigger殘留均0；帳號停用並軟刪，正常稽核保留。原型只讀服務及自建Browser分頁關閉、尺寸覆寫重設。沒有修改主庫權限或正式帳號。

忽略的 `artifacts/p1-acceptance-20261031/` 保留原 Excel 讀取、Node／PHP／PHPStan、255項與181契約、369項索引、108筆Browser版型、9筆採證流程、截圖與清理後盤點；私有fixture憑證不提交。逐檔架構見 [PROJECT-GUIDE](PROJECT-GUIDE-20261103.md) 與 [search-index](../modules/search-index.md)。本輪驗收紀錄歸入階段十二整合驗收交付，不提前開發 P2。
