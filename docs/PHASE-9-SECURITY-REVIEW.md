# 階段九安全審查

2026-10-29；範圍：新增管理API、消息HTML／媒體、共享店家及公開前後篇。依security-audit skill執行，正式環境／上游映像差項沿用P0交接，不以本文件取代。

- 授權：所有管理操作經既有admin身分與session；寫入路由標module/action，Kernel維持CSRF、唯讀模擬阻擋及新鮮權限。Service在交易內重新驗證寫入權限。get單筆允許view或edit；列表只允許view；店家選擇允許offers view/create/edit，沒有擴權到其他模組。HTTP未偽造匯入角色。
- 完整性：更新／刪除要求正整數version，讀鎖及相符檢查避免覆蓋；新ID由伺服器產生。交易提交後發布，同步失敗明確202，前端鎖定已完成新增避免重送。軟刪除與發布投影一併撤下公開入口。
- SQL：動態條件只拼接固定片段；q／status／type／merchantId與分頁綁定參數，LIKE對!／%／_轉義；無任意排序或表名輸入。
- XSS：正文沿用伺服器HtmlPurifier與BodyImages；前端預覽只重建白名單DOM，不把原始HTML直接插入預覽。其他文字與卡片屬性編碼；neighbors只呈現合法同站詳情路徑。新聞另開頁加入noopener／noreferrer與文字提示。
- 媒體：沿用解碼／重編碼及大小／像素限制，有內容圖時要求alt；掃描仍依U-14為圖片內容檢查，不宣稱病毒掃描。上傳module/action依實際新增或編輯權限。
- 資料邊界：管理輸出採DTO選定業務欄位，公開白名單不新增作者、媒體內部ID、刪除狀態或店家啟用旗標；merchantAvailable只在管理輸出。四類前後篇從成功快照讀，不用DB猜測或外部URL。
- 真實MariaDB首輪拒絕測試：匿名401、CSRF403、撤權403、獨立新增／編輯、分類與條款422、版本409及並行200/409已通過；最終116檢查／70回應及全部17新增操作契約0錯誤。此輪未宣稱重跑Semgrep／Gitleaks／映像掃描。

目前程式審查無已確認未處理的高風險問題；最終實測與清理結果見 [驗收紀錄](PHASE-9-ACCEPTANCE.md)，最終本機結果已完成；正式環境界線不變。
