# Google 候選、簡介參考與關聯閱讀指南

2026-10-18。U-10：使用者要求改善行政區／官網／地點關聯，並明確選擇「Google簡介只供參考，網站介紹由管理員撰寫」。E-21 是本次欄位、DTO、政策、元件與限制的工程設計；既有地圖匯入來自功能規格20。沒有新增業務表或API端點。本提交交付候選／行政區／官網與簡介參考；關聯確認依下一功能提交處理。

## 候選資料流

MapController → MapResolver（授權、限速、安全URL／HTTP）→ GooglePlaceMapper（外部資料校驗）→ MapCandidate DTO → 候選卡 → 確認帶入 → applyPlaceCandidate／OpeningHours編輯器。金鑰只在PHP，前端與日誌沒有金鑰；JSON不是可信domain物件。

| 新檔 | 責任 |
| --- | --- |
| [GooglePlaceMapper.php](../../backend/src/Adapters/GooglePlaceMapper.php) | 有界文字、完整座標配對、地址結構、外連協定、簡介原文與提供者映射 |
| [MapCandidate.php](../../backend/src/DTO/MapCandidate.php) | 明確型別的可輸出候選，不讓外部陣列／錯誤型別流入表單 |
| [place-candidate.js](../../frontend/assets/js/core/place-candidate.js) | 單一候選套用路徑，清空未知行政區、保留官網協定，引用共用週表 |
| [place-reference.js](../../frontend/assets/js/components/place-reference.js) | Google原文參考區、來源／提供者／外連；不建立保存欄位 |
| [GooglePlaceMapperTest.php](../../backend/tests/Unit/GooglePlaceMapperTest.php) | 新竹市／同名外縣市、異常型別、URL與原文保留 |
| [map_fields_integration.py](../../backend/tests/map_fields_integration.py) | 真實HTTP／DB保存、HTTP官網、錯誤不改資料、參考欄位不能保存 |
| [place-candidate.test.mjs](../../frontend/tests/place-candidate.test.mjs) | 候選帶入、未知行政區清空、網站介紹不被覆蓋 |

只確認addressComponents有新竹市（或Hsinchu City）及可識別東區／北區／香山區時帶入district。其他縣市的同名東區不能套用。無法確認時回空字串，表單required要求管理員選取；人工建立同樣沒有預設北區。

websiteUri只接受無帳密的http／https，保留原協定，PHP不下載或探測該網站。AttractionInput對websiteUrl採相同驗證；mapUrl與伺服器Google請求仍HTTPS，javascript／ftp／帳密網址仍拒絕。公開外連保持noopener noreferrer。

editorialSummary原文只放回應與目前DOM，不trim／截斷／改寫，過長或不合法時明示未提供；最多20000字的上限是防護，不把截斷內容冒充完整簡介。不送summary／description、不進DB／public projection／localStorage；重新載入或解除關聯清除參考區。選擇候選後仍可在網站介紹上方查看原文。Google未提供簡介時保留網站既有內容。

來源標示依[Google Maps歸屬政策](https://developers.google.com/maps/documentation/places/web-service/policies)：候選／參考區使用獨立底色與Google Maps文字、translate=no、不換行及12–16px正常字重；提供者名稱及安全連結一併顯示。沒有照片、評價或生成式摘要。FieldMask新增addressComponents、editorialSummary及attributions；editorialSummary屬Enterprise + Atmosphere，實際費率由客戶帳戶及[官方欄位表](https://developers.google.com/maps/documentation/places/web-service/data-fields)為準，不硬編價格。
