# 景點摘要與介紹自動補位計畫／交付狀態

2026-11-01。U-23：使用者明確要求啟動原先延後的補位。**自家摘錄、後台預覽及 Google 即時原文備援已實作**，本機結果見[階段十五驗收](PHASE-15-ACCEPTANCE.md)，逐檔與操作見[補位指南](../modules/attraction-content-fallback.md)。2026-10-18 的 U-11／E-22 討論當時僅是計畫，不回寫成階段三早已完成。

## 本輪範圍與來源

Excel 為欄位限制基準；U-11／U-23 決定自填優先、空白補位。E-40 決定120字摘錄、API、限速及即時顯示技術。這些不是 Excel 新增必填，也不阻擋草稿或發布。

| 位置 | 已實作順序 |
| --- | --- |
| 列表摘要 | 自填摘要 → 自填介紹的最多120字 Unicode 摘錄 → 原型看介紹連結 |
| 景點介紹／首頁博物館介紹 | 自填介紹 → 已關聯 Google 地點的即時原文 → 尚未提供介紹 |
| 後台預覽 | 收起的小區塊；明確點擊才呼叫後端相同政策，顯示來源；不儲存、不在打字時請求 Google |

原欄位照實保存，清空後恢復補位。Google 原文不截短／改寫、不寫回摘要或介紹，不進 DB、HTML 發布檔、SEO、JSON-LD、搜尋、日誌或本機儲存。公開卡片不逐筆請求 Google。預覽只顯示 Google 備援資格，不保證該地點實際有簡介；真原文由已發布詳情即時載入。

## 分層與資料

共用 AttractionContentPolicy／DTO 負責自家文字；Controller 管 HTTP／權限，AttractionSummary Service 核對已發布版本與 DB，再經 PlaceSummaryProvider／GoogleSummaryProvider 呼叫固定供應商；Repository 管真 DB 及並行鎖。前端只負責畫面及安全原文呈現。

新增 POST /api/v1/admin/attractions/content-preview 與 GET /api/v1/attractions/{slug}/google-summary，契約同步更新。沿用 Google key、auth_rate_limits 與既有欄位，沒有新表／migration／env／服務／runtime 依賴。115 HTTP 契約、68頁、34表338欄、20 migration。

## 驗收與交接

涵蓋自填優先、空白／Unicode／長文、介紹更新後摘錄更新、原值不回寫、未發布／下架、越權／CSRF、真 MariaDB 限速／並行、Google 429／錯誤／缺 key、原文與來源標示。桌面1440／手機390及320已實際看新增／詳情區塊。真 Google 測試只保留48字長度等元資料，沒有保存原文。完整結果以驗收文件最後一節為準。

正式 Google 專案仍需設定計費配額／key 來源限制及正式政策條款，不把本機 available 當整個平台正式驗證通過。參考 [Place resource](https://developers.google.com/maps/documentation/places/web-service/reference/rest/v1/places) 的 editorialSummary 原文要求及 [Places 政策](https://developers.google.com/maps/documentation/places/web-service/policies) 的保存／來源規範。
