# 階段十四：會員查詢與營運設定

2026-11-01；分支 `phase/14-operations-settings`，從 main d02e045 開始。

來源以原始 Excel「功能規格」E25–L25、E28–L28 為準；原型為 website/admin/members.html、settings.html、index.html 與 website/js/app.js。欄位清單不解讀為全部必填。儀表板與 root 稽核入口是實作計畫14.3的工程補充 E-36，不冒稱 Excel 新功能。

1. 會員：GET /api/v1/admin/members?q=&page=；members.view；固定20筆分頁、字面搜尋、名稱與信箱遮罩、必要識別／狀態／加入日期，不提供 CRUD 或匯出。原型搜尋、表格、分頁與唯讀互動保留。
2. 一般設定：GET/PUT /api/v1/admin/settings；settings.view/edit；固定 general key，網站名稱／描述沿用 seo 單一來源；聯絡信箱與條款／隱私文字及版本沿用 site_settings。兩份文件版本防止覆寫；變更稽核，公開頁與註冊同意版本連動。技術版本與長度／安全檢查標 E-36，不增加業務必填。
3. 儀表板：GET /api/v1/admin/dashboard；dashboard.view 並按各模組 view 權限裁切真實統計與最近更新。GET /api/v1/admin/audit-logs 只限重新驗證的 root，僅回必要稽核識別，不回密碼、IP或 before/after 原始JSON。維持原型卡片、更新表格與快捷入口。

三個管理頁為 frontend/pages/admin/{members,settings,index}.html；政策與共用頁尾亦需增量驗收。無新增資料表，預期沿用 members、site_settings、audit_logs 與各內容表；是否需索引以 MariaDB12.3 EXPLAIN 決定，無證據不新增DDL。

按會員、一般設定、儀表板功能分提交，同步提供中文模組指南／契約／驗證與PROJECT-GUIDE。驗收包含真MariaDB分頁與越權、遮罩、未知key、版本衝突、發布一致性、同意版本、原型桌面／390／320、鍵盤及失敗重試。PHPStan／PHPat、PHPUnit、JS及相關既有回歸在Docker跑。正式網域與第三方平台依U-21仍交接；階段十五未開始。通過後備份歷史、核對原始69檔與完整tree再合併main，保留分支。


## U-22追加改善與目前界線

使用者於本輪明確要求字級、換行、CSS遺漏、帳號／字型載入跳動與行程直覺性改善。併入階段分支，分成可獨立說明的載入穩定／行程操作功能；不提前開始階段十五。操作改為同頁選地點、保留草稿、主要儲存放編排區，原有所有權與明確確認仍由API強制。詳細檔案見[介面指南](modules/interface-stability.md)。

內建Browser連線於追加改善時不可用。先前桌面／390／320與原型18筆只代表舊版；最新字型冷載入、版型及鍵盤必須補實測，階段十四不因此提前完成或合併。期間可按功能提交與保留證據，但不冒稱本機完整驗收已通過。
