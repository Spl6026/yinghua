# Google 候選、簡介參考與關聯閱讀指南

2026-11-01 最新補充：U-23已啟動摘要／介紹補位。管理自填欄位仍照實保存；摘要可摘錄自家介紹，空介紹的公開詳情可即時顯示獨立 Google 原文，無內容安全降級。不是把Google原文存入網站介紹。新增API、檔案、配額與操作見[補位指南](attraction-content-fallback.md)；下方日期內容保留原模組歷史範圍。

2026-10-18。U-10：使用者要求改善行政區／官網／地點關聯，並明確選擇「Google簡介只供參考，網站介紹由管理員撰寫」。E-21 是本次欄位、DTO、政策、元件與限制的工程設計；既有地圖匯入來自功能規格20。沒有新增業務表或API端點。

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

editorialSummary原文只放回應與目前DOM，不trim／截斷／改寫，過長或不合法時明示未提供；最多20000字的上限是防護，不把截斷內容冒充完整簡介。不送summary／description、不進DB／public projection／localStorage；重新載入或解除關聯清除參考區。依使用者後續排版要求，選擇候選後在列表摘要上方查看原文，順序為地址 → Google原文參考 → 列表摘要 → 景點介紹，新增／編輯頁一致。Google未提供簡介時保留網站既有內容。

來源標示依[Google Maps歸屬政策](https://developers.google.com/maps/documentation/places/web-service/policies)：候選／參考區使用獨立底色與Google Maps文字、translate=no、不換行及12–16px正常字重；提供者名稱及安全連結一併顯示。沒有照片、評價或生成式摘要。FieldMask新增addressComponents、editorialSummary及attributions；editorialSummary屬Enterprise + Atmosphere，實際費率由客戶帳戶及[官方欄位表](https://developers.google.com/maps/documentation/places/web-service/data-fields)為準，不硬編價格。

## 地點關聯資料流

U-10 介面補充（2026-10-18）：依使用者指出人工景點顯示大區塊不合適，移除獨立標題與說明，將關聯元件放在地圖匯入欄下。未關聯完全隱藏；已關聯以原生 details／summary 顯示緊湊狀態列，狀態和「關聯詳細資訊」在同一個 summary 對齊，Place ID 及解除按鈕在下方獨立展開；地點資料改動時才顯示核對提醒與確認按鈕。這是介面調整，沿用原本保存與授權規則。

google-place-link元件追蹤名稱／地址／座標 → 改動後暫停保存，提供重新確認或解除 → AttractionInput.googleLinkConfirmed（嚴格boolean命令）→ AttractionManagement在授權、版本校驗後 → GooglePlaceLinkPolicy比較DB既有值 → 原本交易／audit／Repository保存。

| 新檔 | 責任 |
| --- | --- |
| [GooglePlaceLinkPolicy.php](../../backend/src/Policies/GooglePlaceLinkPolicy.php) | 新增／替換ID或地點欄位改動，保留關聯須明確確認；不依賴HTTP／PDO |
| [google-place-link.js](../../frontend/assets/js/components/google-place-link.js) | 人工無關聯時隱藏、緊湊提示、可展開Place ID／解除、待確認／已確認、唯讀；確認等待期間資料變動不誤認完成 |
| [GooglePlaceLinkPolicyTest.php](../../backend/tests/Unit/GooglePlaceLinkPolicyTest.php) | 舊客戶端未改關聯、改動／替換／建立、解除與布林型別 |
| [google_link_integration.py](../../backend/tests/google_link_integration.py) | 真實API／DB的確認、略過前端、唯讀／越權／版本與不改資料；只用隔離庫 |
| [google-place-link.test.mjs](../../frontend/tests/google-place-link.test.mjs) | 真正元件的取消／確認、還原、座標表示法、唯讀、競態、參考區及錯誤焦點 |

googleLinkConfirmed不落DB、不是Google簽章或驗證。新增或替換googlePlaceId須true；舊客戶端未改名稱／地址／座標及ID時可沿用。修改內容後可人工確認同一地點，也可清空ID解除；不自動刪除已填地址／營業時間。更新仍須正確version，失敗不產生新版本。關聯ID不進公開API。

core/ui.js的formErrors優先找data-field-name群組，避免錯誤焦點落在隱藏Place ID；其他表單依原namedItem處理。按帶入後聚焦未知行政區或名稱；確認後聚焦可見關聯區；解除後關聯區隱藏，焦點移到景點名稱。只讀模擬不能帶入、確認、解除或送出，伺服器另有拒絕。

本次完整計畫及實際驗收見 [MAP-IMPORT-IMPROVEMENTS.md](../archive/MAP-IMPORT-IMPROVEMENTS.md)。其他模組依原階段，不因Google有欄位就擴增電話、評分、照片或評論功能。
