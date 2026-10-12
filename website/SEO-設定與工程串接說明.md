# 映畫竹塹 SEO 設定與工程串接說明

## 已完成的前端基礎

- 57 個獨立 HTML 頁面補上繁體中文描述、關鍵字、robots／googlebot、Open Graph、Twitter Card、網站名稱、語系、分享圖片與 favicon。
- 登入、註冊、會員行程及站內搜尋設定為 `noindex,follow`；`/admin/` 後台頁為 `noindex,nofollow,noarchive`。
- 公開 HTML 提供 WebSite JSON-LD 基本結構；前端示範程式會依網站設定更新標題、摘要、分享資訊、canonical 與驗證碼。
- `robots.txt` 允許公開內容檢索並排除 `/admin/`。
- 管理後台「網站設定」新增全站預設 SEO、正式網址、分享縮圖、Google／Bing 驗證碼、Twitter 帳號及主要頁面個別覆寫。

## 後台資料欄位

建議 `site_settings` 儲存：`name`、`description`、`seoTitleSuffix`、`seoRobots`、`seoKeywords`、`seoCanonicalBaseUrl`、`seoShareImage`、`seoTwitterCard`、`seoTwitterSite`、`seoGoogleVerification`、`seoBingVerification`、`seoPages`、`updatedAt`、`updatedBy`。

`seoPages` 以頁面路徑為 key，每頁可存 `title`、`description`、`keywords`、`ogImage`、`robots`。景點、電影、消息、優惠詳情模板可用 `places/*.html`、`films/*.html`、`news/*.html`、`offers/*.html` 通用覆寫，實際內容仍建議依內容資料的 SEO 欄位優先。

## 建議 API

- `GET /api/v1/site-settings/public`：回傳可公開的網站名稱、預設 SEO、驗證碼及社群分享設定；不得回傳任何後台機密。
- `GET /api/v1/admin/settings/seo`：需要 `settings.view` 權限，回傳完整 SEO 設定。
- `PUT /api/v1/admin/settings/seo`：需要 `settings.edit` 權限；驗證 URL、robots 白名單與欄位長度；寫入稽核紀錄。
- 公開內容 API 回傳各筆 `seoTitle`、`seoDescription`、`ogImage`、`canonicalPath`、`robots` 等欄位。

## 上線前工程工作

1. 將「正式網站網址」設定為實際 HTTPS 根網域；不可使用範例網域。產生 canonical 時統一網域、大小寫與尾斜線規則，移除追蹤參數，保留確有識別意義的內容 ID。
2. 建議由 PHP／伺服器模板或預先產生的獨立 HTML 輸出 SEO 標籤。JavaScript 即時更新僅是原型同步，無法保證所有搜尋／社群爬蟲執行腳本後才看到內容。
3. 動態內容需各自唯一 title、description、canonical、OG image 和 JSON-LD。避免同一個預設 meta 套到全部文章或電影詳情。
4. 後端建立 `sitemap.xml`，列出可索引且已發布的公開頁面與內容 slug；不要列出草稿、登入、註冊、個人行程、搜尋結果及後台頁。robots.txt 加入實際 `Sitemap: https://正式網域/sitemap.xml`。
5. 後台圖片欄位應改用媒體上傳，確認圖片可匿名讀取，分享圖片建議 1200×630、HTTPS、具適當替代文字與授權來源。
6. 設定 Google Search Console／Bing Webmaster 的驗證碼後，使用正式網域驗證；發布後提交 sitemap 並檢查索引狀態。
7. 發布與刪除時處理 sitemap／快取更新；若網址變更，設定 301 導向並更新 canonical 與 sitemap。
8. 會員個資或私人行程永不寫入 JSON-LD、Open Graph、公開 sitemap 或可被快取的公開 API。

## 說明

此網站仍為無資料庫原型。SEO 後台目前以瀏覽器 localStorage 示範，正式使用須接入具權限驗證的 API 與資料庫。`robots.txt` 不能取代登入授權或資料保護。
