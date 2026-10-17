# 地圖候選帶入改善

2026-10-18，U-10／E-21。本提交完成型別化候選、地址結構行政區、HTTP官網及Google原文簡介參考；使用者明確選擇網站介紹由管理員撰寫，沒有自動覆蓋或保存Google簡介。API仍44操作，無新增表或migration。

GooglePlaceMapper／MapCandidate集中外部驗證；前端place-candidate套用，未知行政區要求選擇，place-reference顯示原文與來源。PHP不下載官網，地圖及Google安全Adapter仍HTTPS。詳見 [程式指南](modules/google-place-fields.md)。

候選及官網的PHP／前端／契約與真實HTTP回歸已驗證；最終數量以本提交快照實際檢查為準。本輪Google瀏覽器實連一次成功，確認帶入東區且原自寫介紹保持不變，未保存實連候選。後續功能提交另交付可見Place ID、人工確認／解除及後端Policy；本提交不將它列為完成。
