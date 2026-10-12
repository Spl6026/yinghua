# 工程串接建議

此包是可互動的前後台展示原型，畫面內容採示範資料。建議工程師保留 UI 結構，將 `js/app.js` 的示範陣列與 `localStorage` 操作換成 API 呼叫。

## 建議資料實體

- `members`: id、display_name、email、password_hash、status、created_at、verified_at
- `places`: id、name、category、district、address、latitude、longitude、google_place_id、map_url、hours_text、description、cover_image、status、created_by、updated_at
- `place_offers`: id、place_id、title、description、starts_at、ends_at、terms、status
- `screenings`: id、film_title、genre、description、starts_at、ends_at、ticket_url、status
- `news`: id、category、title、summary、body、cover_image、starts_at、ends_at、status、published_at
- `routes`: id、title、theme、description、cover_image、status、created_by
- `route_stops`: id、route_id、place_id、sequence_no、suggested_time、note
- `member_favorites`: member_id、place_id、created_at
- `member_trips`: id、member_id、title、trip_date、updated_at
- `trip_stops`: id、trip_id、place_id、sequence_no、planned_time、note

## 建議 API

- `POST /api/auth/register`, `POST /api/auth/login`, `POST /api/auth/logout`, `POST /api/auth/email-verification`
- `GET /api/places?district=&category=&q=`, `GET /api/places/:id`, `POST /api/admin/places`, `PATCH /api/admin/places/:id`, `DELETE /api/admin/places/:id`
- `POST /api/admin/places/resolve-map-url`：後端展開 Google 短網址、驗證 host、呼叫 Places API，回傳可編輯欄位；拒絕 localhost、私有網段與非 Google host 以降低 SSRF 風險。
- `GET /api/screenings`, `GET /api/news`, `GET /api/routes`
- `GET/POST/PATCH/DELETE /api/me/trips`, `PUT /api/me/trips/:id/stops`, `GET/PUT /api/me/favorites`
- `GET/POST/PATCH /api/admin/*`：以角色權限保護所有管理端 API，前端隱藏選單不等於授權。

## 安全與無障礙交接

- 註冊表單上的隨機碼只為展示；正式防濫用請由伺服器產生短效挑戰或使用成熟 CAPTCHA，搭配 Email 驗證、登入限流、CSRF 防護及安全工作階段 Cookie。
- 使用 Argon2id 或 bcrypt 儲存密碼；不可儲存明文密碼，不可將驗證碼答案或 Google API 私密金鑰暴露到瀏覽器。
- Google Maps 短網址要在伺服器端安全展開，限制重新導向次數、只允許受信任網域、阻擋私有/保留 IP，並處理 Places API 欄位與來源標示要求。
- 管理員需 RBAC、操作日誌、CSRF 防護、輸入驗證、HTML 輸出編碼與圖片上傳 MIME/大小檢查。
- 目前已提供語意標籤、跳至主內容、鍵盤焦點、表單標籤、對話框 Escape 關閉、狀態播報與 `prefers-reduced-motion`。正式 AA 驗收仍需逐頁用鍵盤／螢幕閱讀器測試，檢查圖片替代文字、色彩對比、錯誤提示關聯、焦點圈限與第三方嵌入。
- 為了避免第三方來源限制，可把 Google Fonts 與示意照片改成本機合法授權資產；建立 CSP 時也需一併處理外部圖片與字型來源。


## 多頁原型與管理表單

各內容清單、詳情與後台新增／編輯頁均為獨立 HTML。前台加入行程、管理員新增／修改／刪除景點等操作使用瀏覽器 localStorage 示範狀態；刪除採確認視窗並在原列表顯示通知，沒有獨立刪除頁。正式工程請將這些操作改為具登入授權的 API 請求，並保留各頁面路徑或對應後端路由。
