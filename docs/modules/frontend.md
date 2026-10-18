# 前端版型與程式閱讀

日期：2026-10-14。`U-05` 要求沿用原始交付的排版，`E-12` 是前端建置與唯讀內容快照，`E-14` 是中文閱讀與介面狀態補充。沒有新增 API 或資料表。

2026-10-23 複驗補充（U-05／E-14）：真實「更改密碼／登出」加入頂列後，`phase2.css` 的 `.admin-topbar>.admin-preview` 保持「查看網站 ↗」完整文字，避免窄螢幕箭頭單獨換行。原型 style.css 不變，頁名仍可依可用寬度換行。

`website/` 是原始交付參考，仍與第一筆提交逐 byte 相同。實際服務從 `frontend/dist/` 提供，來源在 `frontend/pages/`、`templates/`、`assets/`。不要修改 dist；也不要把網站連回原始示範的 localStorage 認證。

## 原版設計如何沿用

`frontend/assets/css/style.css` 完整複製原始 `website/css/style.css`，包括 Noto Sans TC／Noto Serif TC 字型載入、色彩、尺寸與斷點。`phase2.css` 只補充 API 錯誤、必要新增欄位、確認對話框與互動狀態，不覆寫 account-card 寬度、標題字級或卡片造型。

共用 `status()` 以 `data-error` 表示結果；`phase2.css` 的成功／一般提示使用原版綠色，錯誤使用紅色（E-14）。這會覆蓋認證表單原本 `.form-error` 的預設紅色，避免「資料已載入」或重設信申請成功也顯示為錯誤色。

| 畫面 | 原始來源與 CSS 結構 | 現在的程式 |
| --- | --- | --- |
| 登入／註冊 | website/login.html、register.html 的 site-shell → page → account-page → account-card | pages/login.html、register.html；auth.js 共用真正 API |
| 新增／編輯管理員 | website/admin/admin-add.html、admin-edit.html 的 admin-wrap、admin-page-heading、admin-panel、admin-form-grid、admin-field | pages/admin/admin-add.html、admin-edit.html；pages/admin-form.js |
| 管理員清單 | website/js/app.js 的 renderAdmin('admins')：帳號表格、狀態標籤、固定保護說明 | pages/admin/admins.html；pages/admin-list.js |
| 個人權限矩陣 | renderAdmin('permissions')：permission-toolbar、permission-table、permission-check、admin-form-actions | pages/admin/permissions.html；pages/permissions.js |
| 景點／優惠／消息／電影 | 原始 renderPlaces、renderOffers、renderNews、renderFilms、renderHomeFilms 的 DOM 與 class | components/content-cards.js；public.js |
| 搜尋／路線／行程示意 | 原始 search-hit、route-card、itinerary-stop | components/content-cards.js；public.js；data/prototype.js |
| 詳情與文章導覽 | 原始 detail-cover、detail-body、article-neighbors | pages/public-details.js |

公開資料是交付時的唯讀快照，尚未接後續內容 API。行程保留原始三站示意版型並明示唯讀；收藏、移動、套用與保存只告知待開放，不保存假會員資料。後台尚未實作的內容頁也只保留原版後台外框與待開放提示。

## 新功能與原版的差別

規格要求的真實驗證碼／條款同意（S-016）和權限保存（S-027）仍保留。必填且一致的確認密碼（U-15）、8–128 Unicode 字元提示（U-13）、Email 驗證碼替代方式、忘記／重設／本人改密碼入口、初始密碼、狀態、搜尋、分頁、重新載入及登出等，沿用已記錄的工程選擇；不是為了改版新增功能。具體來源與政策見 DESIGN-TRACEABILITY.md、PHASE-2-PLAN.md。

例如管理員表單比原型多一列狀態／初始密碼，因此面板高度增加；原版的雙欄、欄距、字型與欄位樣式仍相同。註冊增加確認密碼與條款說明也會增加高度，不能拿掉真正的認證要求來追求等高。

## 先讀哪些檔案

1. `scripts/build.mjs`：把共用模板組成 64 個固定 HTML，保留各原型頁的導覽 active 狀態，設定表單 POST fallback。PHP 不組 HTML。
2. `templates/`：公開頁 header/footer 與後台 sidebar/topbar、共用確認對話框。未登入的 member-navigation 使用 display:contents，讓原版登入按鈕仍是 header-actions 的 flex 項目。
3. `assets/js/core/ui.js`：安全建立 DOM、錯誤標籤、焦點、導覽、提交等待與未保存提示。
4. `assets/js/auth.js`、`admin.js`：先讀身分，再接各頁模組。後台拒絕／錯誤只更新 admin-content，保留側欄外框。
5. `assets/js/components/content-cards.js`：每個函式建立一種原型卡片，使用 element／textContent，不把資料直接拼成 HTML。
6. `assets/js/public.js`：分類、地區、搜尋及唯讀列表的組合；保留原版進入畫面的 reveal 動畫及 reduced-motion 行為。
7. `assets/js/pages/public-details.js`：詳情資料與同主題前後篇。使用站內絕對網址，巢狀頁不會因相對路徑連錯。
8. `assets/js/components/admin-preview.js`：U-06 固定 root 的左下唯讀模擬選單，沿用原版 admin-session-switch 外觀；API 取真實帳號、顯示模擬提示與退出，不儲存假身分。詳見 [admin-preview.md](admin-preview.md)。

## 建置與驗收

```powershell
docker compose run --rm frontend
```

重整 http://localhost:8080/。瀏覽器驗收先比對同一 viewport 的原版與新版，再確認真實 API 與手機導覽；不要只確認 HTML 有 class 就宣稱畫面一致。

本次對照尺寸、原始檔保留與功能觀察記錄在 PHASE-2-ACCEPTANCE.md。圖片仍使用原交付的外部來源；素材品質、正式內容、SEO 與全站最終驗收屬後續階段。

全 57 個原始網址的再次複查與差異分類見 [WEBSITE-COMPARISON-REVIEW.md](../WEBSITE-COMPARISON-REVIEW.md)。本次還恢復 24 頁原有 head 文案、30 頁 aria-current、註冊／footer 文字、首頁真實 href 與詳情箭頭。美食 tab 沒有計數 span 時不能假定節點存在；原「在地店家」的分類值錯誤只修新 frontend，website 仍保留交付原貌。
