# Google Maps 候選匯入閱讀指南

2026-11-01 最新補充：U-23已啟動摘要／介紹補位。管理自填欄位仍照實保存；摘要可摘錄自家介紹，空介紹的公開詳情可即時顯示獨立 Google 原文，無內容安全降級。不是把Google原文存入網站介紹。新增API、檔案、配額與操作見[補位指南](attraction-content-fallback.md)；下方日期內容保留原模組歷史範圍。

U-10／E-21新增地址結構行政區、HTTP官網及原文簡介參考，由GooglePlaceMapper／MapCandidate負責映射；網站介紹不被自動覆蓋。關聯已可見，名稱／地址／座標變更須管理員確認或解除，後端GooglePlaceLinkPolicy另行校驗。完整檔案與API語意見 [地圖欄位與關聯指南](google-place-fields.md)，本輪結果見 [改善計畫](../archive/MAP-IMPORT-IMPROVEMENTS.md)。

U-09／E-20：營業時間 FieldMask 增 regularOpeningHours.periods，GoogleOpeningHours Adapter 依 day／hour／minute 轉成本站週表，人工與匯入共用同一編輯器；weekdayDescriptions 只在無法安全映射時提供人工參考。缺資料為未知，24/7 獨立處理，常態週表不使用 currentOpeningHours。完整規則見 [營業時間指南](opening-hours.md)。

更新：2026-10-18。功能規格列 20 要求地圖短網址匯入；具體 Places API New、網域清單與安全限制為 E-19。

MapController 驗證輸入及 create／edit 情境 → MapResolver 檢查管理員權限與每分鐘 10 次限速 → GoogleUrlPolicy 校驗網址 → CurlGoogleHttp 取得必要回應。金鑰只從 Docker 注入的 GOOGLE_PLACES_API_KEY 讀取，前端、回應與日誌都不含金鑰。

只接受指定 Google Maps HTTPS 網域及路徑，拒絕憑證、非 443 port、控制字元與混淆網址。短網址最多三跳，每跳重新驗證網域，偵測循環。HTTP adapter 驗證所有 A 記錄為公開 IPv4，固定連線到已驗證 IP，不使用代理、不自動重導向；有連線／總逾時及 256 KiB 回應上限。

取得 Place ID 時呼叫 Place Details New；其他可辨認名稱呼叫 Text Search New。使用明確 FieldMask，回傳最多五筆名稱／地址／行政區／座標／營業時間／官網／Google 地圖連結及簡介原文參考與提供者。不請求或下載照片。無法解析、配額不足、金鑰缺少或服務失效都有可理解錯誤，仍可人工建檔。

前端將結果列為候選，顯示 Google Maps 來源；按帶入欄位後還需確認，使用者可修正，最後手動儲存。解析 API 不寫 attractions。正式採用及保存 Google 資料的權利／期限仍須依客戶帳戶與 [Places 政策](https://developers.google.com/maps/documentation/places/web-service/policies) 確認；不能將可技術預填理解為已取得所有保存及素材授權。

參考 [Place Details New](https://developers.google.com/maps/documentation/places/web-service/place-details)、[FieldMask](https://developers.google.com/maps/documentation/places/web-service/choose-fields) 與 [OWASP SSRF 防護](https://cheatsheetseries.owasp.org/cheatsheets/Server_Side_Request_Forgery_Prevention_Cheat_Sheet.html)。這些提供設計依據，並未指定本案類別名稱。

使用者已於 .env 提供金鑰，完整 Maps 網址及實際 maps.app.goo.gl 短網址均以真實 API 取得候選；測試未保存 Google 候選資料。惡意重導向、循環、錯誤 JSON、配額錯誤與缺金鑰另有不耗配額的單元測試。可用 `python backend/tests/attractions_integration.py --live-map` 明確重驗實連；一般回歸不重複消耗配額。
