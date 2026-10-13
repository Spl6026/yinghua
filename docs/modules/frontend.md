# 前端首頁與共用版型閱讀

U-05 沿用原交付設計，E-12 使用 Node 建置與唯讀原型資料。來源在 frontend/pages、templates、assets；PHP 不輸出 HTML。

2026-10-23 複驗補充（U-05／E-14）：真實「更改密碼／登出」加入頂列後，`phase2.css` 的 `.admin-topbar>.admin-preview` 保持「查看網站 ↗」完整文字，避免窄螢幕箭頭單獨換行。原型 style.css 不變，頁名仍可依可用寬度換行。

先讀 scripts/build.mjs 的模板組合、templates/site-header.html 與 site-footer.html，再讀 assets/js/public.js、components/content-cards.js 與 data/prototype.js。公開資料尚未接正式內容 API；其餘原型頁依後續細項提交加入。

共用 `status()` 以 `data-error` 表示結果；`phase2.css` 的成功／一般提示使用原版綠色，錯誤使用紅色（E-14）。這會覆蓋認證表單原本 `.form-error` 的預設紅色，避免「資料已載入」或重設信申請成功也顯示為錯誤色。
