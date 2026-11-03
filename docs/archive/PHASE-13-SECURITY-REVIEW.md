# 階段十三安全審查

2026-11-01，依security-audit審查本次搜尋API、投影及frontend增量；不將沿用基線或未執行掃描冒充本輪全站安全認證。

| 邊界 | 審查與真實驗證 |
| --- | --- |
| 私人／未發布資料 | SearchDocument只取成功公開快照明列欄位；搜尋來源無members／admin_users／sessions。五類之外type拒絕；草稿、未到排程、下架、無效優惠及管理欄位排除通過。 |
| SQL注入／資源 | q／generation／type／LIMIT／OFFSET綁定PDO參數；LOCATE字面匹配，%／_不成萬用字。q與分頁有明確上限。單字中文、注入字串與超長輸入已驗。 |
| DOM／URL | 新純卡片與錯誤狀態以textContent，不用innerHTML；API及卡片各有主題URL白名單，外部／javascript／內部_content_pages不成連結。Node與Browser驗HTML字串不執行。 |
| 同版／故障 | 索引交易與generation隔離，read()驗發布狀態；查詢與發布共鎖。真失敗503、錯誤URL、還原、舊版清理與Browser重試通過，無草稿回填。 |
| 授權／既有流程 | 只新增匿名公開GET，沒有管理寫入權限放寬；P1 255項／181契約含CSRF、越權、本人、409及並行回歸。 |
| 部署／秘密 | 無新依賴、服務或env，.env保持忽略；索引不是編輯來源或會員搜尋歷史。測試僅命名自有yinghua_test，帳號／session最後清理，原型與規格不改。 |

最終113整合／28契約、230 JS、112 PHP／370斷言與PHPStan／PHPat通過，新增範圍無待處理安全失敗。容量與全站鎖是在本機資料量下的工程取捨，不保證未知正式負載；未重跑全量Semgrep／Gitleaks／映像掃描，正式server及既有交接項不以本輪取代。實際範圍見[驗收](PHASE-13-ACCEPTANCE.md)。
