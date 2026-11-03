# 階段十三：全站搜尋驗收

2026-11-01。依使用者授權，在P1驗收後main `34e40c5`建立`phase/13-site-search`；[計畫](PHASE-13-PLAN.md)、[架構與逐檔指南](../modules/site-search.md)、[安全審查](PHASE-13-SECURITY-REVIEW.md)一併交付。原始website與Excel未修改。

## 規格與實際範圍

| 原始來源 | 本輪結果 |
| --- | --- |
| 功能規格E18 | 查景點、美食、電影、消息、優惠，依類型分組；結果具有直接詳情URL，非原型陣列。 |
| F18／H18 | q、type、title、summary、slug、thumbnail對應一個GET /api/v1/search及白名單回應。 |
| G18 | 255字、20筆預設／最多100筆與排序為E-35；允許中文單字及字面%／_，參數化SQL、安全文字、穩定分頁。 |
| L18 | 五種類型與URL一致；館舍維持museum.html；Browser鍵盤直接開啟美食詳情，HTML字串不執行。 |
| 原型search.html | 保留主視覺、文字卡片、搜尋列、空白／無結果提示，增量為類型、分頁、載入／錯誤／重試與URL返回。 |
| 既有發布／SEO邊界 | 草稿、排程未到、下架、無效優惠與私人資料不進搜尋；公開generation與SQL投影一致。搜尋頁noindex、無JSON-LD、不進sitemap。 |

新增search_documents是E-35可重建投影，不是Excel額外要求一張編輯主表；沒有增加業務必填、管理API或外部搜尋服務。沿Controller／Service／Repository與DTO／enum，PHP只提供API。

## 最終測試

| 項目 | 實際結果 |
| --- | --- |
| 前端Node | **230項通過**；URL編碼、過時成功／錯誤、清空及重試；獨立純卡片驗文字XSS、外部／javascript／錯誤主題URL與館舍路徑。 |
| PHP | **112 tests／370 assertions通過**；公開白名單、文字投影、型別、空／短中文字、字數與類型限制。 |
| PHPStan10／PHPat | 無錯誤，分層與型別檢查通過。 |
| PER-CS／Rector | 限定本次10個PHP檔dry-run零差異；Rector僅dry-run，無套用。 |
| 真MariaDB／HTTP／OpenAPI | **113項、28筆搜尋契約回應，零契約錯誤**。最終固定版本完整重跑，並驗證預設finally清理。 |
| P1受影響回歸 | 階段九116＋十58＋十一81＝**255項**，**181筆契約回應零錯誤**；既有內容發布、路線及行程仍可操作。 |
| Browser | 原型／本輪空白頁各三尺寸、結果與分類各三尺寸，12筆；抽出純卡片後主站另做1440／390／320共3筆，**共15筆無整頁溢出**。另13筆流程採證。 |
| 實際盤點 | **105 PHP JSON API＋3爬蟲GET＝108 HTTP契約；68固定HTML／34表338欄／20 migration**，契約與實作差集皆空。 |

真整合覆蓋五類分組、類型篩選、不同頁不重複／同版順序、空查詢／查無資料／超出頁數、中文一字、255／256字、字面%／_、SQL注入字串、HTML標題與正文關鍵字；草稿／未到排程排除、下架移除、優惠過期移除、slug改名更新結果。每個測試結果詳情直接200，未回傳帳號或管理欄位。

以自有測試內容的錯誤slug驗發布失敗與503，還原後重建成功，舊索引版本清除。Browser另驗不可用時清空舊卡片、聚焦狀態、顯示重試；恢復後重試成功。沒有把早期執行器的空字串SQL／漏列共用requestId／錯誤假設或模板改動造成的版本中斷算成通過。

## 介面與可及性

實際操作包括：輸入查詢、類型切換、第二頁與瀏覽器返回、鍵盤直接詳情、無結果、空白提示、網址超長與非法類型錯誤、修正恢復、過界分頁回第一頁、503與重試。q錯誤明確顯示「最多255字」，aria-invalid與aria-describedby連到可見錯誤，焦點到欄位；其他失敗聚焦status。結果以textContent輸出，可辨連結有對應標題，裝飾箭頭隱藏於輔具。

沿用[Web Interface Guidelines](https://raw.githubusercontent.com/vercel-labs/web-interface-guidelines/main/command.md)及[P0 AA有效基線](P0-AA-FINAL-20261029.md)作本輪增量檢查。無主機NVDA、無重新宣稱完整50準則／真200%或400%／第三方AA認證；320重排不當成真縮放。原型的簡化本機搜尋改為API，必要增量造成表單高度／結果排序不同，不宣告每個像素或示範結果完全相同。

## 查詢、資料與部署

MariaDB12.3.3真EXPLAIN／ANALYZE使用最終WHERE、排序、LIMIT／OFFSET與count。49筆測試投影的all與news採PRIMARY、type=ref，估計候選49／28筆，仍有Using filesort；未使用的額外排序索引在新migration定稿前移除，只留generation／type／ID主鍵。Performance Schema原為OFF，未擴大環境設定，不冒稱大規模壓測或取得完整等待事件剖析。

新增一份019_public_search.sql及一張11欄投影表；舊migration保持，新建環境與本機主／測試均可db:migrate，校驗journal與最終DDL。只讀公開快照白名單，不存搜尋歷史、會員或後台資料。投影完整交易完成後才啟用HTML，查詢與發布／清理共用鎖；復原DB／媒體／release後content:publish重建。無新env／依賴／Docker服務，publisher重啟已載入新投影規則。

正式網域與外部平台仍依U-21交接，搜尋本身不依賴外部平台；本輪沒有啟動階段十四／十五，也不宣稱P2整體或全案交付完成。

## 交付完整性

主／測試清理後各68固定頁、21個直接引用資產、89筆HTTP，零異常；sitemap主站31頁、測試站35頁，逐頁200／canonical／OG／Twitter／JSON-LD官方詞彙檢查通過，無JS仍可讀。Search頁不在索引。

自有news_posts／offers／merchants／attractions／search_documents殘留均0，索引僅PRIMARY、單一generation；20份migration checksum均與檔案相符。基礎會員／管理員停用軟刪、有效session、行程、收藏、路線、景點、電影與測試trigger殘留均0；正常稽核保留。原始69檔逐位元相同、既有104個PHP操作與components保持，.env／dist／artifacts繼續忽略。

依13A投影準備、13B完整搜尋、13C整合驗收三筆交付；合併前備份並verify、核對最終tree／原始交付後合併main並保留分支，不推送。既有防毒隔離參考檔刪除保持原工作樹差異，不納入功能。

忽略的artifacts/phase13保存113項／28契約、P1回歸、品質日誌、15筆版型量測、13筆Browser流程、主站最終卡片、EXPLAIN／ANALYZE、migration修訂與清理證據。fixture密碼不提交；只清自有yinghua_test資料，原型只讀服務及自建Browser分頁關閉、尺寸重設，未動主庫權限或正式帳號。
