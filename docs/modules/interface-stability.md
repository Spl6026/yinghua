# 介面載入與行程操作改善（U-22／E-37）

2026-11-01。使用者指出字級不一致、換行／CSS遺漏、先訪客再會員與字型切換，以及行程操作不直觀。本次為使用者要求的既有功能體驗修正，並非Excel新必填；後續字型修正於階段十五完成。

## 載入與字型

site-header.html初始顯示「確認帳號中」，不預判訪客。session.js只在401才顯示登入；其他錯誤給重試。依使用者最新指定保留原頁首樣式：訪客沿原本登入按鈕；會員沿原有名稱／收藏／更改密碼／登出橫列（中等寬度在登入按鈕寬度內換兩行，手機只留名稱、其餘放漢堡選單），不新增下拉選單；右側以看不見的訪客版內容撐住寬度，主選單不因帳號狀態移動。私人身分不寫入localStorage，也不以顯示狀態代替API授權。

font-head.mjs讓固定頁與公開產頁都在主樣式之前直接載入本機fonts.css，移除style.css的@import載入鏈；Google Fonts官方Noto Sans TC／Noto Serif TC可變字型沿SIL OFL提供，原始授權放assets/fonts。5,728個原網站／目前頁面常見Unicode先供小型子集（約547KB／740KB）；其餘有效字元保留完整字型檔，unicode-range避免為不支援的emoji下載大檔。Sans與Serif兩份共用子集由build／publish於head前段預載；font-display:optional的設計是字型未及首屏時保持系統fallback，但不能僅憑此屬性推定使用者回報的收縮已消失。首次／未快取可能使用系統字型，並不保證每個網路條件首屏都顯示自訂字型。沒有隱藏整頁等待字型。

官方來源：google/fonts的ofl/notosanstc/NotoSansTC[wght].ttf及ofl/notoseriftc/NotoSerifTC[wght].ttf；轉為WOFF2，來源TTF只在忽略artifacts/font-source保留。一次性FontTools／Brotli打包工具置artifacts/font-tools，不新增PHP／網站runtime依賴。檔案大小、工具版本與SHA256見[字型清單](../FONT-ASSETS.json)。完整字型檔約5.4／7.7MB，常用頁面以小型子集供字；不宣稱字型完全不需下載或已做真網路節流量測。

## 行程流程

保留原型主視覺、側欄與站點卡，使用者現在可以「新增地點→原頁搜尋→加入草稿→調整順序／時間／備註→儲存」。不再因新增地點跳至城市散策並要求捨棄草稿。沒有行程時，第一次確認加入才建立空行程；站點維持明確儲存，不把預覽GET寫入帳號。推薦路線可明確確認後建立新行程並套用，已有行程時仍提示替換範圍。

儲存按鈕移到行程編排區，與未儲存提示相鄰。只有一頁時隱藏行程清單分頁。上下移與移除改成文字按鈕；時間不自動重排站點，頁上明確說明。欄位、文字區與控制項統一字級、字型、間距及換行；不新增必填。

| 檔案 | 用途 |
| --- | --- |
| frontend/assets/js/components/attraction-picker.js | 共用公開地點搜尋／分頁、同頁選擇、去重與錯誤重試；選取由呼叫者保存草稿 |
| frontend/assets/js/pages/planner.js | 新增／推薦路線原頁流程、建立時機、身分中性狀態、頁界控制 |
| frontend/pages/planner.html | 編排步驟、搜尋容器與主要儲存位置 |
| frontend/assets/js/components/trip-stop-editor.js | 有文字的上下移及移除操作 |
| frontend/assets/css/phase11.css | 站點／表單／搜尋與手機編排版型 |
| frontend/assets/js/core/session.js／templates/site-header.html | 中性帳號載入、會員選單、過期／失敗／過時回應處理 |
| frontend/assets/css/style.css／fonts.css／phase2.css | 字型策略、textarea繼承、標題層級、換行及穩定帳號區 |
| frontend/scripts/font-head.mjs／build.mjs／publish.mjs | 固定／公開頁共用早期字型樣式及雙字型預載，重複執行不重複插入 |
| frontend/tests/attraction-picker.test.mjs／member-navigation.test.mjs／member-pages.test.mjs | 原頁加入、去重、重試、保存時機、身分過期與過時回應 |

後端／資料表與現有API契約不因本項改變。實際Browser差項、靜態檢查及自動測試分別記錄於[階段十四驗收](../archive/PHASE-14-ACCEPTANCE.md)，不能用測試替身取代最新畫面／載入跳動實測。


## 本輪版型與重新聚焦修正

沒有簡介的站點仍將地區／類型與「看介紹」分開成行，避免小字後直接連上較大連結。城市散策的place-card／place-body使用縱向flex，底部place-details以margin-top:auto推至卡片下緣；營業資訊可換行，操作組仍對齊下方。這同時套用伺服器產頁與JS載入卡片，並不以空白簡介墊高度。

行程首次載入、focus／visibilitychange／pageshow背景刷新均不主動將焦點交給頁尾狀態訊息；背景錯誤亦不搶焦點。使用者明確按保存等操作仍保留既有結果焦點，跨帳號仍清除原私人草稿。新增回歸驗證初次／重新聚焦／背景錯誤不聚焦頁尾；最新實際版型仍受Browser連線限制，不能冒稱已完成視覺量測。


使用者再回報字型收縮後，新增共用font-head.mjs，讓font-face CSS與兩套字型在主樣式之前被發現；NodeContentRenderer將此helper納入版本雜湊，避免修改後重用舊產頁。根元素另以scrollbar-gutter:stable保留捲軸寬度，text-size-adjust:100%避免手機自動文字膨脹；不限制使用者縮放。這些調整已驗產頁／資產／單元契約，實際字型收縮仍待Browser恢復後定位並量測。


2026-11-01複驗差項：雖font-display:optional與雙預載已部署，真5秒延遲字型的隔離fixture仍出現字形／粗細晚變。font-size與DOM矩形無變化，不能因此認定視覺穩定；本項重新開啟，見[最新證據](../archive/PHASE-15-ACCEPTANCE.md#不通過u-22字型視覺穩定)。本輪未改正式字型策略。

## 階段十五：首幀字型選擇（U-22／E-39）

前述 optional 失敗紀錄保留。現行 style.css 的 --sans／--serif 預設為系統字型；font-head.mjs 在主樣式前同步載入 [font-boot.js](../../frontend/assets/js/font-boot.js)，用 Font Loading API 載入兩個常用子集。只有第一個 requestAnimationFrame 前兩者已就緒，才加入 fonts-ready 選用原有 Noto；來不及就保持當頁系統字型，字型晚到不再換字。不隱藏整頁、不延後互動，也不保存跨頁身分或字型偏好。無 JS／不支援 API 同樣使用系統字型。

fonts.css 的完整字型改為 Extended 獨立 family，不放在現行顯示 font stack，罕用字由系統補字，避免完整字型晚到又替換字形；四個原有 font 檔、OFL 與 SHA256 沒有改動。快取熱載入仍可使用 Noto；冷首訪可能使用系統字型，此取捨是避免晚換字造成使用者所見收縮。NodeContentRenderer 將 boot.js／fonts.css 納入發布版本，固定與動態頁共用相同 head。

[font-boot.test.mjs](../../frontend/tests/font-boot.test.mjs) 驗早就緒／晚到／失敗。內建 Browser 同樣5秒真延遲、純色背景且關閉動畫，標題截圖前後像素差0，第一張早於字型回應；正常快取頁亦確認 fonts-ready 與 Noto。這關閉可重現的晚變字形缺陷，不宣稱所有網路與整頁所有內容的 CLS 為0。證據見階段十五驗收末節。

## 2026-11-02：重驗修正（E-41）

實際重現收藏後立即導覽造成請求中斷。[pending-writes.js](../../frontend/assets/js/core/pending-writes.js)提供可重用的進行中寫入協調器；member-actions.js只在收藏真正寫入時追蹤，站內連結及安排進旅行等待確認成功才導覽，失敗留頁；關分頁／reload警示未完成寫入。不以keepalive跳過CSRF／session、不離線重送、不寫localStorage。新分頁／下載／修飾鍵及同頁hash維持原行為。相同快速離頁Browser複測已從0筆改為1筆，加入成功／失敗及等待回歸。

[utc-display.js](../../frontend/assets/js/core/utc-display.js)共用UTC→Asia/Taipei格式，供儀表板／稽核與會員列表／摘要，表頭標示台北；不改API或DB。前端最終267項通過，完整本輪範圍見[三優先級重驗](../archive/P0-P1-P2-REVERIFICATION-20261102.md)。
