# 消息管理閱讀指南

2026-10-30，9A消息管理完成，9B優惠及9C前後篇尚待後續提交。

前端news三頁沿用原型面板。共用content-list／content-form串接news五操作；content-body-editor提供安全預覽與圖片工具。AdminNewsController解析HTTP，AdminNewsManagement協調授權、版本、交易、稽核與發布，NewsRepository只處理SQL。共用DTO與CLI關係見[內容準備](content-editing.md)。

來源：功能規格E22/G22/L22、G10/L10；工程E-31。標題依原型必填，摘要／正文／活動時間選填；event／city限定，圖片有內容時需alt。更新需version、409保留輸入、202鎖定成功新增避免重送。

已驗消息CLI公開40項、型別／架構、實際新增／編輯／上傳／日期／兩分頁衝突與桌手機；新增表單行為及時間回歸。77 API／68固定頁，資料表與環境不變。OpenAPI逐項定義五個新增操作。
