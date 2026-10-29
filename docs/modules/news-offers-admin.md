# 消息管理閱讀指南

2026-10-30，9A消息管理完成，9B優惠及9C前後篇尚待後續提交。

前端news三頁沿用原型面板。共用content-list／content-form串接news五操作；content-body-editor提供安全預覽與圖片工具。AdminNewsController解析HTTP，AdminNewsManagement協調授權、版本、交易、稽核與發布，NewsRepository只處理SQL。共用DTO與CLI關係見[內容準備](content-editing.md)。

來源：功能規格E22/G22/L22、G10/L10；工程E-31。標題依原型必填，摘要／正文／活動時間選填；event／city限定，圖片有內容時需alt。更新需version、409保留輸入、202鎖定成功新增避免重送。

已驗消息CLI公開40項、型別／架構、實際新增／編輯／上傳／日期／兩分頁衝突與桌手機；新增表單行為及時間回歸。77 API／68固定頁，資料表與環境不變。OpenAPI逐項定義五個新增操作。

## 優惠及合作店家（9B）

獨立offers五操作與merchant輔助三操作、三頁完成；總85 API。OfferMerchantController／OfferMerchants及AdminOfferController／AdminOfferManagement協調共用驗證、交易、權限與發布。兩Repository管理店家選擇、有效期篩選、參數化搜尋與軟刪除。offer-merchants.js提供收合式共享profile，修正前明示影響全部引用優惠。

功能規格G23條款必填；標題／店家／優惠依原型必填，期間／介紹選填，雙日期存在才檢查先後。merchantAvailable只在管理記錄回傳，公開白名單保持；店家停用與過期分開呈現。0新表／migration／環境變數。優惠公開40項、實際條款擋送／共享店家／日期422／讀回保存／手機與表單失敗回歸已驗。9C前後篇與9D整合驗收待後續提交。

店家分頁狀態由offer-merchants.refresh重套；共用content-form更新控制項後委派，並在click防止越界。新增offer-merchants.test.mjs三項回歸驗首末頁、空／單頁及表單鎖定；不改API、權限或欄位要求。

## 同版詳情前後篇（9C）

四個匿名neighbors操作使總數89。NeighborSelection按景點分類／消息type／電影／優惠選擇同版公開內容，發布時間降冪及ID升冪；ContentPublication把detailNeighbors納入generation。PublicNeighbors只讀成功快照，publish.mjs與content-neighbors.js生成相同真實路徑。無相鄰不造空連結，博物館有真實固定頁，電影不侷限當週。

來源G8／L10／L12／G29；新聞入口依L10另開新頁並提示，前後篇仍一般導覽。三個PHP及三個前端新增案例、API／HTML同版及四類桌手機詳情／Tab操作已驗。完整整合結果、清理與全站導讀待9D提交。
