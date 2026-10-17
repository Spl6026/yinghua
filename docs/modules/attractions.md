# 公開景點與 HTML 發布閱讀指南

更新：2026-10-18。本提交完成公開 API 與 CLI 發布；後台管理／媒體／地圖表單仍待後續功能提交。來源 S：功能規格 5／6／13，SEO規格 17；具體發布方式 E-12／18。

PublicAttractionController → PublicAttractions → ContentPublication 只讀成功版本。CLI content:seed-demo 明確匯入原始六筆展示資料，不覆寫已有內容，素材仍須確認授權；content:publish 產生空或既有內容。以 www-data 執行，publisher 每 10 秒檢查 content:tick。

ContentPublication 從 AttractionRepository 建立已發布且到期的公開投影與版本；NodeContentRenderer 以無 shell 程序呼叫 frontend/scripts/publish.mjs，HTML／模板／head 在前端組合，寫完後原子切換 current。Nginx 使用 publication/access 檢查版本，失敗或不一致時 503，防止舊內容外洩。舊 slug 與原型 legacy_id 只導向已發布網址。媒體存取尚未交付，本提交拒絕 /media/。

公開 explore／food／museum、景點詳情及首頁景點精選使用相同資料。保留原型版型；其他首頁區塊及收藏保存待後續。真實 Node 產物測試涵蓋 XSS、canonical、台北跨日日期、失敗不啟用與下架。

完整 API、表與頁面目標見 [階段三計畫](../PHASE-3-PLAN.md)；實作遵守 [架構](../ARCHITECTURE.md)，沒有 PHP HTML View。
