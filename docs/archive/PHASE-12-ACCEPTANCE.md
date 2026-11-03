# 階段十二：結構化資料、索引與P1驗收

2026-10-31；`phase/12-seo-indexing`由階段十一合併後main建立。計畫見[PHASE-12-PLAN.md](PHASE-12-PLAN.md)，逐檔職責與操作見[索引指南](../modules/search-index.md)。U-21：使用者指定正式網域未定，先完成本機，外部正式驗證留交接，不冒稱已上線。

## 本機結果

| 驗證 | 實際結果 |
| --- | --- |
| 全前端Node／真renderer | **224通過**；JSON-LD白名單、XSS、真實內容、私頁／noindex／佔位、XML、失敗不啟用、50,000筆邊界及分片。 |
| PHPUnit／PHPStan10／PHPat | **107 tests／356 assertions通過**，型別及分層無錯誤。 |
| 限定PER-CS／Rector | 7個PHP檔dry-run零差異；Rector僅dry-run，3檔可選語法未套用。主機introspection不識容器工具不當通過，上述工具均在Docker實跑。 |
| P1階段九／十／十一真HTTP／MariaDB | **116＋58＋81＝255項通過；181筆契約回應零錯誤**，覆蓋消息優惠／前後篇、推薦路線、完整會員行程。 |
| 本階段索引／發布整合 | **369項、70 HTTP、46份graph官方詞彙檢查通過**；每個列入的URL 200、canonical自指、XML／robots MIME正確。改名301、noindex排除、下架404、lastmod、發布失敗503及復原通過；原SEO主檔／覆寫逐列完整復原。 |
| P0收藏回歸 | **69項／29筆契約回應通過**；會員、CSRF、下架快照、隔離與失敗行為。P0目前行程亦由階段十一跨模組回歸覆蓋。 |
| 官方Schema.org Validator | 內建Browser程式碼模式：Article、Movie、Event、Offer、TouristAttraction、Museum、LocalBusiness **7種真實頁面graph均0錯誤／0警告**，包含WebPage／WebSite／Organization引用。 |
| Browser | 首頁、電影、文章、活動、優惠、路線、planner各1440／390／320，共**21筆**，尺寸符合設定、無整頁溢出；head、noindex、前後篇鍵盤、手機導覽、登入返回、確認套用、再編輯保存／重載完成。 |
| 介面保留 | 同一快照由階段十一baseline `55a2030`與本輪renderer產生，**40個公開HTML body逐字相同**；本輪頁面模板／CSS未改。 |

原104個PHP API與components、68固定HTML、33表327欄19migration保持；新增Nginx文字／XML GET三個，OpenAPI共107操作，無新env／依賴／服務。兩入口nginx語法檢查／reload及publisher指紋重新載入通過。

主站8080與測試站8081最終各68固定頁／19個直接引用資產／87筆HTTP核對零異常；所有JS相對模組引用亦核對存在。清理後sitemap主站31頁、測試站35頁，逐頁200／canonical／OG／Twitter／JSON-LD官方詞彙檢查零異常，不執行JavaScript仍成立。

## P1逐列驗收

| 功能規格列 | 功能 | 本機證據 |
| --- | --- | --- |
| 8 | 電影詳情／前後篇 | 真API共用neighbors、Browser電影及可辨連結；Movie與片名／介紹／海報相符。 |
| 10 | 消息詳情／同類前後篇 | 階段九回歸；Browser文章／活動、同分類下一篇與keyboard；Article／Event官方驗證。 |
| 12 | 優惠詳情／前後篇 | 期間／條款／店家、過期狀態及獨立列表回歸；Browser相鄰卡片與Offer驗證。 |
| 14 | 推薦路線列表 | 階段十58項、公開順序／有效站點，Browser列表／套用入口。 |
| 15 | 完整會員旅程 | 階段十一81項，跨session、本人所有權、去重、交易／版本；Browser登入返回指定路線、套用、再編輯保存／重載。 |
| 22 | 活動／消息CRUD | 草稿／發布、排程／分類／安全正文、權限／CSRF、並行／刪除回歸；本輪改名／下架索引同步。 |
| 23 | 優惠CRUD | 店家、條款／期間、權限及獨立列表詳情回歸；真HTML／graph／lastmod取相同公開資料。 |
| 24 | 路線CRUD／套用同步 | 階段十／十一回歸及本輪Browser套用後再編輯。 |
| 29 | 圖片替代文字／前後篇 | 四主題同版已發布neighbors、無相鄰不產空連結；原body保持，Browser可辨名稱／縮圖及鍵盤焦點。 |
| 31 | JSON-LD | 白名單、可見內容、XSS／公私隔離；46份官方詞彙domain／range與7種真頁官方Validator通過。 |
| 33 | robots／sitemap | 同generation、canonical／200、改名／下架、失敗／復原、容量／XML；隔離測試正式origin設定後完整還原。 |

SEO規格10／11的Twitter Card及Google／Bing token沿用P0能力，無JS head／正式canonical設定回歸有效。**本機P1工程界線通過；Search Console／Bing驗證、Google Rich Results實際抓取／呈現與Facebook／LINE／X分享，依U-21待正式網域及平台帳號。** 沒刪除原要求，也不以Schema.org通過保證特定Rich Result或收錄。

Event只有場地名稱不捏造Google要求的地址；無價格不造Product／price，無評分／作者不填，不新增業務發布必填。正式驗證仍列上線交接及階段十五，P2階段十三未開始。

## 介面、AA與交付界線

無新可見控制／樣式；共用AA沿用[P0有效基線](P0-AA-FINAL-20261029.md)，增量驗重排、head／私頁、可見焦點、前後篇、確認框反向Tab、手機導覽Escape返回。沒有啟動主機NVDA或控制主機視窗，也沒有聲稱重跑50準則、真200%／400%或第三方認證。

40頁body相同（清理後另驗35頁仍相同）是對階段十一比較，不把動態內容說成與原型示範文字逐像素相同。只用自有yinghua_test帳號／內容，未改root密碼或主庫權限。忽略的artifacts/phase12保留整合、契約、官方詞彙、七型別Validator截圖／graph、21筆Browser、40頁比對及品質日誌。復原沿用DB／媒體／release，content:publish一起重建HTML、JSON-LD、XML及robots。

## 最終複核與清理

分類選「城市消息」→第二頁→瀏覽器返回，內建Browser實際確認三次canonical、title與JSON-LD WebPage.url／name同步；Node回歸新增分類／分頁／popstate與noindex移除測試，全前端最終224項通過。伺服器及瀏覽器共用同一schema factory，不重複建立graph。

21筆命名隔離分頁消息已清除；原驗收會員／管理員停用並撤銷session，行程、收藏、路線、景點、電影與觸發器殘留均為0。只清自有fixture，保留原有示範資料。原始69檔逐位元相同、原104個PHP操作與全部OpenAPI components保持，文件連結核對無缺漏。清理後同快照再比對35個公開HTML body，仍與階段十一完全相同。

Git依12A結構化資料、12B同版索引、12C整合驗收分功能提交；合併前備份並verify，核對完整tree及原始交付，以merge commit回main並保留階段分支，不推送遠端。既有防毒隔離參考檔刪除保持工作樹差異，不納入功能提交。

接續時Docker引擎未啟動，恢復後上述兩站canonical入口完整核對通過。另一組以127.0.0.1補跑時，索引工具刻意不跟隨重新導向，因網站設定canonical為localhost而收到預期302，這不是索引內容失敗；同輪主站site-map／terms曾503，先前publisher日誌亦有啟動期間DB連線錯誤，但不據此斷定503根因。主／測試各重新執行content:publish後，兩頁、兩站robots及新增共用JS實際皆200，服務健康；紀錄保留於忽略的post-merge-http.json，不把這次恢復寫成全程無異常。

## 使用者另行要求 P1 驗收

完整11列與本輪重新執行結果見[P1整合驗收](P1-ACCEPTANCE-20261031.md)。本輪無應用程式變更，108筆版型核對及會員重新登入保存通過；正式平台仍依U-21交接，未開始P2。
