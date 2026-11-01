# 全站30項驗收矩陣

2026-11-01。唯讀核對原始Excel「功能規格」5–34列；下表的規則／驗收來源為G列／L列，頁面為B列，資料表I列保持建議性質。實作API詳見[OpenAPI](../backend/resources/openapi.json)，實際資料表見[資料庫對照](DATABASE-SPECIFICATION-MAP.md)。不以本表或測試反推新增必填規則。

「本輪／有效基線」清楚區分；列入套件不等於通過，最新執行結果見[階段十五驗收](PHASE-15-ACCEPTANCE.md)。正式HTTPS／SMTP／Google配額及外部平台仍列交接。

| Excel來源 | 功能／優先級 | 原型頁面 | 模組與有效證據 | 本輪驗證及界線 |
| --- | --- | --- | --- | --- |
| 功能規格 E5:L5（規則G5／驗收L5） | 首頁內容與精選區塊／P0 | website/index.html | [階段5](PHASE-5-ACCEPTANCE.md) | HTTP公開頁／資產 |
| 功能規格 E6:L6（規則G6／驗收L6） | 影像博物館介紹／P0 | website/museum.html | [階段3](PHASE-3-ACCEPTANCE.md) | HTTP／原型基線 |
| 功能規格 E7:L7（規則G7／驗收L7） | 本週片單與海報／P0 | website/cinema.html | [階段4](PHASE-4-ACCEPTANCE.md) | HTTP／電影基線 |
| 功能規格 E8:L8（規則G8／驗收L8） | 電影詳情／上一篇下一篇／P1 | website/films/{slug}.html、film-detail.html | [階段9](PHASE-9-ACCEPTANCE.md) | P1契約 |
| 功能規格 E9:L9（規則G9／驗收L9） | 活動與城市消息列表／P0 | website/events.html／activities.html／city-news.html | [階段5](PHASE-5-ACCEPTANCE.md) | P1內容契約 |
| 功能規格 E10:L10（規則G10／驗收L10） | 消息詳情與上一篇下一篇／P1 | website/news/{slug}.html、news-detail.html | [階段9](PHASE-9-ACCEPTANCE.md) | P1契約 |
| 功能規格 E11:L11（規則G11／驗收L11） | 店家優惠列表（獨立主題）／P0 | website/offers.html | [階段5](PHASE-5-ACCEPTANCE.md) | P1優惠契約 |
| 功能規格 E12:L12（規則G12／驗收L12） | 優惠詳情與上一篇下一篇／P1 | website/offers/{slug}.html、offer-detail.html | [階段9](PHASE-9-ACCEPTANCE.md) | P1契約 |
| 功能規格 E13:L13（規則G13／驗收L13） | 景點／美食列表及詳情／P0 | website/explore.html、food.html、places/{slug}.html | [階段3](PHASE-3-ACCEPTANCE.md) | 公開搜尋／原型基線 |
| 功能規格 E14:L14（規則G14／驗收L14） | 推薦行程列表／P1 | website/routes.html | [階段10](PHASE-10-ACCEPTANCE.md) | P1路線契約 |
| 功能規格 E15:L15（規則G15／驗收L15） | 會員旅行計畫／P1 | website/planner.html | [階段11](PHASE-11-ACCEPTANCE.md) | P1本人隔離／保存重載 |
| 功能規格 E16:L16（規則G16／驗收L16） | 會員註冊與隨機驗證碼／P0 | website/register.html | [階段2](PHASE-2-ACCEPTANCE.md) | 政策註冊＋既有認證基線 |
| 功能規格 E17:L17（規則G17／驗收L17） | 會員登入／登出／P0 | website/login.html | [階段2](PHASE-2-ACCEPTANCE.md) | 本輪登入／登出與匿名拒絕 |
| 功能規格 E18:L18（規則G18／驗收L18） | 全站搜尋／P2 | website/search.html | [階段13](PHASE-13-ACCEPTANCE.md) | 搜尋113項／28契約 |
| 功能規格 E19:L19（規則G19／驗收L19） | 景點清單／搜尋／刪除／P0 | website/admin/places.html | [階段3](PHASE-3-ACCEPTANCE.md) | 匿名拒絕＋景點基線 |
| 功能規格 E20:L20（規則G20／驗收L20） | 新增／編輯景點（含短網址匯入）／P0 | website/admin/place-add.html、place-edit.html | [階段3](PHASE-3-ACCEPTANCE.md) | 匿名拒絕＋Google既有基線 |
| 功能規格 E21:L21（規則G21／驗收L21） | 電影／場次 CRUD 與海報／P0 | website/admin/films.html、film-add.html、film-edit.html | [階段4](PHASE-4-ACCEPTANCE.md) | 匿名拒絕＋電影既有基線 |
| 功能規格 E22:L22（規則G22／驗收L22） | 活動／消息 CRUD／P1 | website/admin/news.html、news-add.html、news-edit.html | [階段9](PHASE-9-ACCEPTANCE.md) | P1契約 |
| 功能規格 E23:L23（規則G23／驗收L23） | 優惠 CRUD（獨立）／P1 | website/admin/offers.html、offer-add.html、offer-edit.html | [階段9](PHASE-9-ACCEPTANCE.md) | P1契約 |
| 功能規格 E24:L24（規則G24／驗收L24） | 推薦路線 CRUD／P1 | website/admin/routes.html、route-add.html、route-edit.html | [階段10](PHASE-10-ACCEPTANCE.md) | P1契約 |
| 功能規格 E25:L25（規則G25／驗收L25） | 會員查詢／P2 | website/admin/members.html | [階段14](PHASE-14-ACCEPTANCE.md) | 73項整合＋摘要鍵盤 |
| 功能規格 E26:L26（規則G26／驗收L26） | 管理員新增／編輯／刪除／P0 | website/admin/admins.html、admin-add.html、admin-edit.html | [階段2](PHASE-2-ACCEPTANCE.md) | 匿名拒絕＋root既有基線 |
| 功能規格 E27:L27（規則G27／驗收L27） | 管理員權限矩陣／P0 | website/admin/permissions.html | [階段2](PHASE-2-ACCEPTANCE.md) | 匿名拒絕＋權限既有基線 |
| 功能規格 E28:L28（規則G28／驗收L28） | 網站設定／P2 | website/admin/settings.html | [階段14](PHASE-14-ACCEPTANCE.md) | 73項整合＋政策5項 |
| 功能規格 E29:L29（規則G29／驗收L29） | 圖片與上一篇／下一篇／P1 | website/所有詳情頁 | [階段9](PHASE-9-ACCEPTANCE.md) | P1契約／原型基線 |
| 功能規格 E30:L30（規則G30／驗收L30） | 完整頁面 SEO 標籤／P0 | website/所有公開 HTML | [階段7](PHASE-7-ACCEPTANCE.md) | 索引／head整合 |
| 功能規格 E31:L31（規則G31／驗收L31） | 結構化資料 JSON-LD／P1 | website/公開內容頁與詳情頁 | [階段12](PHASE-12-ACCEPTANCE.md) | 官方字彙本機驗證 |
| 功能規格 E32:L32（規則G32／驗收L32） | 全站 SEO 與頁面覆寫／P0 | website/admin/settings.html | [階段7](PHASE-7-ACCEPTANCE.md) | 索引整合／權限基線 |
| 功能規格 E33:L33（規則G33／驗收L33） | 爬蟲索引管理／P1 | website/robots.txt／sitemap.xml | [階段12](PHASE-12-ACCEPTANCE.md) | sitemap本機整合 |
| 功能規格 E34:L34（規則G34／驗收L34） | 無障礙／安全回應／P0 | website/所有頁面 | [階段8](PHASE-8-ACCEPTANCE.md) | 全管理API匿名拒絕＋有效AA基線／增量UI |

三十列皆已有對應實作；這不代表所有正式服務或所有場景本輪重新執行。依U-16放映僅比較日期／開始時間，結束時間延後；依U-14圖片內容檢查不冒稱病毒掃描；依U-15確認密碼必填且一致。Google簡介只原文參考，摘要／介紹自動補位依使用者指示仍延後。
