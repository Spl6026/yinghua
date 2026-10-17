# 地圖候選帶入與地點關聯改善

2026-10-18，U-10：使用者要求景點介紹可帶入，並授權改善行政區、官網網址及地點關聯。沿用階段三，不新增階段四業務。技術細節 E-21，PHP API-only／MariaDB／Docker／原生 JS 架構不變。

## 範圍與交付單位

1. 行政區：請求 addressComponents，只有明確的新竹市且可識別東區／北區／香山區才帶入；無法判定則清空，要求人工選擇。人工新增也不預設北區。
2. 官網：接受不含帳密的 HTTP／HTTPS 外部官網連結，保留原協定，不冒改成 HTTPS；地圖網址及伺服器 Google HTTP Adapter 仍要求 HTTPS。官網只作外連，PHP 不抓取該網址。HTTP 連結在表單有說明。
3. 關聯：顯示 Google Place ID 與關聯狀態；改名稱／地址／座標後必須重新確認或解除。伺服器比較既有資料，新增／換地點及位置改動也須明確 googleLinkConfirmed，不能只靠前端隱藏欄位。確認代表管理員核對，不冒稱 Google 已驗證人工修改。
4. 介紹：使用者已選「顯示 Google 簡介供參考，網站介紹由管理員撰寫」。editorialSummary 原文顯示在來源清楚的參考區，沒有介紹時明示未提供；選中地點後仍可查看，重新載入頁面會清除，不進保存請求／DB／公開投影。不自動改網站摘要與介紹，不能用評價、名稱拼接或自行生成冒充 Google 官方介紹。

## 分層與契約

GooglePlaceMapper Adapter 驗證外部欄位與營業時間、地址結構、官網協定，輸出 typed MapCandidate DTO；MapResolver 負責既有授權／限速／安全請求，Controller 不堆映射邏輯。網站輸入仍在 AttractionInput，關聯確認的業務比較由 AttractionManagement 執行。前端新增可重用關聯元件與候選套用函式，保留原本版型及手動編輯。

不新增 API 端點或資料表；候選 schema 補行政區、editorialSummary 及提供者 attribution，既有管理保存請求新增 googleLinkConfirmed（命令參數，不落 DB）。簡介顯示使用 Google Maps 文字 attribution 與提供者名稱／連結，Google 原文有獨立區塊；人工網站內容保持獨立。

## 驗收

針對新竹市／外縣市同名區／未知地區、HTTP 官網／惡意協定／帳密網址、候選缺值／錯誤型別、地點更換／修改後確認／解除／唯讀／409／422不改資料做單元與真實 HTTP／MariaDB 測試。共用隔離管理員的測試依序執行；不重複消耗 Google 實連配額。前端測試必須真正執行候選與關聯元件，並以瀏覽器驗證桌面／手機、焦點及保存後重載。

## 實際狀態

程式與契約已完成；API仍44操作、業務表20、migration五支。沒有新增資料表／端點。PHPStan level10／PHPat通過，PHPUnit35 tests／166 assertions，前端63項／64頁建置，PER-CS dry-run無差異；Rector僅dry-run、14檔風格建議已檢視未套用。

依序重跑既有景點65項、契約9項／44操作、前次修正21項、週表21項；新增官網／行政區10項、關聯15項真實HTTP／MariaDB皆通過。輸入白名單／型別／協定／HTML文字編碼、來源資料僅參考、參數化SQL與既有CSRF／授權／唯讀／版本邊界已人工審查；沒有宣稱獨立掃描器通過。

本輪透過瀏覽器額外實連一次Text Search New，成功回傳一筆博物館、東區及官方簡介；確認帶入後行政區為東區，網站自寫摘要與介紹不變，選中後可繼續看原文。此實連候選沒有保存進DB，也未下載照片。原文及提供者僅顯示在DOM，關閉／重載即清除。

桌面已重驗改名後阻擋未確認保存、聚焦關聯區、重新確認後保存及重開讀回；解除後保存亦由真實API確認ID為null。桌面原文參考截圖artifacts/map-reference-desktop.png，網站自寫介紹可見且保持原值。瀏覽器短暫逾時後已補驗手機關聯區：重新登入／開啟後ID為空，顯示未關聯；實際355 CSS px、scrollWidth341，無水平溢出，錯誤記錄空，尺寸已還原，截圖artifacts/map-link-mobile.png。原文參考的舊分頁失去偵錯連線，因此本輪手機簡介參考區未另驗，不以桌面或先前手機紀錄代替，也未為截圖重複呼叫Google。

交付分為兩筆完整功能：一、型別化候選、行政區／官網與原文參考；二、可見關聯與後端確認政策。每筆帶前後端、契約、測試及閱讀指南。備份後--no-ff合併並保留階段分支，先前提交及原始交付不改。

參考：[Google Place resource](https://developers.google.com/maps/documentation/places/web-service/reference/rest/v1/places)、[Places 政策](https://developers.google.com/maps/documentation/places/web-service/policies)。這些來源的欄位與限制不同於本案 Excel 指定；具體採用設計標 E-21。


## 2026-10-18：縮小 Google 關聯提示（U-10）

使用者指出人工景點的獨立「Google 地點關聯」區塊過大。本輪只修改新增／編輯表單與共用元件：移除標題／常駐說明，未關聯完全隱藏，已關聯在地圖匯入欄下顯示短提示，Place ID／解除操作收進原生 details；修改地點才顯示確認提醒。解除後移焦景點名稱，避免焦點留在隱藏區塊。

Docker 前端 64 項測試及 64 頁建置通過。實際瀏覽器：人工新增隱藏、既有關聯預設收合（桌面提示約25px高）、修改名稱出現確認、對話框確認後恢復提示、手機詳細資訊展開與解除／焦點正常。手機實際 innerWidth355、scrollWidth341，無水平溢出；臨時尺寸已還原。截圖：artifacts/compact-link-manual-desktop.png、compact-link-linked-desktop.png、compact-link-mobile-open.png。僅使用隔離庫自有草稿並已刪除，沒有呼叫 Google API、修改 PHP／API／資料表；本輪沒有重跑 PHP 或完整 HTTP 測試。介面依 web-design-guidelines 檢視本次修改範圍。原始 website／Excel 保持原狀。
