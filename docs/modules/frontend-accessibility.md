# 共用導覽與鍵盤閱讀指南

2026-10-25 R01 小字修正（S：功能規格 G34／串接總覽 B15；E-29）：[phase2.css](../../frontend/assets/css/phase2.css) 覆寫 `.section-kicker`、`.film-meta`、`.demo-note` 為 `#62665c`，不更動原始 `website/` 或 `style.css`。實際 computed style 在紙色／片單背景量得 5.21／4.76／4.76，一般文字門檻 4.5；保留原型字級、間距與版型。這只關閉這三類已重現的對比差項，不代表完整 AA 已通過。Node 150 項及本輪 Brave 桌機／390px 觀察完成，原始失敗與修正值另存忽略的 `artifacts/p0-improvements-20261025/contrast-*.json`。

更新：2026-10-24。來源：功能規格 E34／G34／L34、串接總覽 B15；具體處理為 E-29。這是階段八已重現問題的修正，沒有更動原始 website／style.css 或新增頁面。完整WCAG2.1 AA／人工讀屏尚未宣告通過。

入口在 [core/ui.js](../../frontend/assets/js/core/ui.js) 的 `initializeMenus()`；公開頁的 `.menu-toggle → .primary-nav` 與後台 `.admin-mobile-menu → .admin-sidebar` 共用一套行為。WeakSet讓會員導覽重刷時不重複綁事件，aria-expanded為開關狀態，原本class負責原型動畫與版型。

手機關閉的選單會被CSS裁切或移出畫面，原先仍可Tab到看不到的連結；Escape也只關面板，焦點留在其中。本次用原生inert讓關閉的手機選單不接收焦點；開啟即解除，切回桌面也解除，保持桌面導覽可用。Escape關閉時，只有焦點原在選單內才回到按鈕，避免使用者在其他區塊時被搶焦點。一般點擊連結不刻意搬動焦點，導向仍交瀏覽器處理。

[menu-state.test.mjs](../../frontend/tests/menu-state.test.mjs) 驗三項：重複初始化、公開及後台手機選單的inert／Escape／桌面斷點／外部焦點保留。2026-10-24全部145項Node測試通過。Brave390×844已重現修正前的隱形Tab點；修正後關閉選單Tab略過連結、Escape返回可見按鈕。

```text
docker compose run --rm --no-deps frontend npm test
docker compose run --rm --no-deps frontend
```

建置後重新載入頁面；不要手改dist。這份修正沒有自動等於全站AA符合；實際讀屏、完整對比／縮放與全部表單狀態以階段八驗收矩陣逐項記錄。
