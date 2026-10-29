# 共用導覽與鍵盤閱讀指南

## 2026-10-26 最終 AA 補驗與共用檔案

[50 項準則結果](../P0-AA-VERIFICATION-20261026.md) 為目前狀態：37 通過、2 不通過、10 不適用、1 已廢止。來源 S：功能規格 E34／G34／L34、串接總覽 B15；具體實作 E-29。

- `frontend/assets/js/components/dialog.js`：原生 showModal／取消初始焦點／Escape 維持；keydown 只攔截第一個按鈕的 Shift+Tab 與最後一個的 Tab，關閉時移除該次監聽並返回觸發點。最外側曾移到瀏覽器工具列，修正後四個方向與 Escape 都已實測；不是只靠原生 modal 就推定循環通過。
- `frontend/scripts/language-passages.mjs`：集中保存已核對的英文介面文案，由 `build.mjs` 與 `publish.mjs` 共用，只在既有葉節點增加 lang=en；script／JSON、style、textarea、pre、code、title 與明確 lang 不改寫。未知內容不推定英文；index／cinema 的中英混排以 bdi 只標英文，不把中文標成英文。
- `frontend/assets/css/phase2.css`：補搜尋、表單、權限與設定邊界的對比，不改尺寸／紙色；首頁旅行區 planner-orbit 保留圖形但停止持續旋轉，reduced-motion 規則維持。
- `frontend/tests/dialog-keyboard.test.mjs` 與 `language-passages.test.mjs`：測最外側循環、原生 Escape、取消／監聽清理，以及不破壞 JSON／程式／未知語言、重複處理與混排；最終前端共 159 項通過。

真實 NVDA 2026.2 已讀出確認標題／完整說明、錯誤及收藏通知，英文區段也有語言切換。Orca／Firefox 前輪自動只先讀取消的觀察仍保留，沒有宣稱該組合已改為相同播報順序；本輪主要組合與補充組合分開判讀。完整 AA 仍因到期提示／延長及內容尋找途徑不通過，無 PHP／API／DDL 變動。

## 2026-10-26 確認框語意補驗

`frontend/templates/dialog.html` 的共用重要確認新增 `role="alertdialog"`、`aria-modal="true"` 與指向 `confirm-message` 的 `aria-describedby`，保留原生 `showModal()`、取消初始焦點、既有文字及版面。來源為 G34／串接總覽 B15 的無障礙要求，具體標記屬 E-29，參考 [WAI 確認警示模式](https://www.w3.org/WAI/ARIA/apg/patterns/alertdialog/)，不新增業務必填或刪除限制。

直接瀏覽器已確認名稱、刪除對象說明、取消初始焦點、Tab 到確認及 Escape 返回原觸發按鈕。Tab 到末端時 `document.activeElement` 出現 BODY 的觀察保留，沒有把它寫成完整圈選已通過。Orca／Firefox 可用標題及方向鍵讀出完整說明；自動開框仍只先讀取消，不能宣稱自動播報問題已解決。最終前端 152 項、67 頁／81 HTTP 通過，沒有 PHP／API／DDL 變更。

2026-10-26：新增 [背景隔離讀屏工具](isolated-accessibility.md)，真實 Orca／Firefox 已有首頁、登入必填、註冊欄位及 checkbox 狀態的語音；[補驗範圍與限制](../P0-COMPLETE-VERIFICATION-20261025.md#2026-10-26-隔離讀屏補驗) 明確區分 DOM、實際語音、人工聽音、Windows NVDA 與完整 AA。noVNC 連續鍵出現焦點／朗讀跳動，不拿這組結果宣告完整 Tab 順序通過；本輪未改前端來源。

2026-10-26 01:20：同一組 31 公開／認證頁現有實際 200%／400% 觀察；400% 的手機選單超出短視窗時，Tab 會捲至最後項，Escape 返回開關亦須等原生平滑捲動後核對位置。縮放數據、背景視窗 outerWidth 的限制、原生輸入欄下緣部分不可見，以及尚未完成的讀屏界線均見 [400% 補驗](../P0-COMPLETE-VERIFICATION-20261025.md#2026-10-26-0120-實際-400-縮放補驗)，不將公開頁結果擴張成所有權限頁或完整人工 AA。

2026-10-25 22:02：實際縮放須保留 DPR、inner／outer width、CSS zoom 及是否使用尺寸覆寫，不能把 320px 等同真 400%。200% 已完成 31 公開／認證頁；原生錨點的 main 無 tabindex 時，需驗下一次 Tab 的循序起點，不能只看 activeElement；平滑捲動也須分開記錄即時與穩定位置。NVDA 的程式啟動／Speech Viewer 視窗存在不等於取得讀屏結果；本輪存取授權逾時、已關閉 helper。詳見 [實際縮放補驗](../P0-COMPLETE-VERIFICATION-20261025.md#2026-10-25-2202-實際-200-縮放補驗)。

2026-10-25 21:40（S G34／B15；E-29）：首頁 `.hero-content` 改以正常排版撐高 `.hero`，預留 caption 空間，避免標題換行或窄寬時 CTA 重疊。style.css 保留圖片／字型，調整背景漸層與 caption 透明度；館舍圖片的 inline background-image 會覆蓋 background 的影像層，因此遮罩放在 phase2.css 的 `.museum-photo::after`，與資料圖片分開。量測須檢查實際 computed style／pseudo、文字大小與前景透明度，不能只讀 CSS 預設值；普通文字 4.5、大字及圖形 3 的分類依實際用途，不把所有小字當圖示例外。實際結果與界線見 [介面補驗](../P0-COMPLETE-VERIFICATION-20261025.md#2026-10-25-2140-網站介面補驗)。

2026-10-25 20:15（S G34／B15、U-05；E-29）：style.css 的 43 處中性色前景共用 `--muted:#5b5f55`，淺底眉題改既有深綠，深色側註提高不透明度；前輪三類 #62665c 覆寫保持。背景／字型／主版型不變。117 次觀察的 5,105 可計算樣本候選失敗 0，muted 最低 4.967:1；108 複雜樣本不算通過，完整讀屏／縮放仍待驗。

build.mjs 依四個固定內容表單路由恢復返回列表，沒有 view 時沿既有可用去向，未放寬 API 權限。phase2.css 令 640px 以下頂列保留操作並將頁名分行，grid 子項與 input 最小寬度歸零，避免 320px 局部裁切。四表單在 320／390 的頂列子項與 panel 寬度均通過。見 [修正追記](../P0-COMPLETE-VERIFICATION-20261025.md#2026-10-25-2015-修正後結果)；下方較早「未改 style.css」只指當時範圍。

景點的 `mapLookupUrl` 僅是前端查詢欄，實際寫入 DTO 的欄位清單仍明確列出，不把它送進景點 API。解析錯誤的 mapUrl 先映射到查詢欄名稱，再交給共用 formErrors 的獨立群組；保存時的 mapUrl 仍對應下方景點地圖網址，不能因同名 API 錯誤而混淆。錯誤使用 element／textContent，不插入服務端原始 HTML；finally 解鎖後才聚焦。單元測試涵蓋拒絕／重試、區塊錯誤不互清及兩欄保存分流，真 Google／非法網域操作另在本輪證據。

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

## 2026-10-29：到期與錯誤訊息焦點

來源 S G34／L34／串接總覽B15、U-20、E-29；[AA完整矩陣](../P0-AA-FINAL-20261029.md)。`components/session-prompt.js` 的標題關聯提示及帳號，開框／模式轉換先聚焦訊息；Tab／Shift+Tab由靜態訊息進入首／末控制項並循環。進度使用polite status，錯誤使用獨立atomic alert；失敗時焦點到錯誤摘要，按Tab進密碼欄，不只停在同一欄位重讀invalid entry。密碼欄透過describedby關聯錯誤，密碼用後清除，取消回觸發點，草稿不重建／不持久化、不自動重送保存。

`frontend/tests/session-prompt.test.mjs` 驗證初始雙向Tab、警示轉到期、錯誤摘要／密碼清除／關閉返回；Docker完整174項通過。沒有新增套件、PHP、API、schema或環境變數。真Orca完整預警／到期說明已取得；原生密碼提示干擾及未取得完整錯誤字句自動語音如實保留，已接收焦點的摘要按W3C相應準則驗收，不額外指定NVDA品牌。正常CSS／TTL已恢復，隔離讀屏停止。


## 2026-10-30 搜尋與回頂端圖示置中（U-21）

`frontend/assets/css/style.css` 的 `.icon-button`、`.back-top` 使用inline-flex、align-items:center與justify-content:center，清除文字按鈕預設padding，line-height:1與letter-spacing:0避免繼承的行高／字距影響位置。維持38×38搜尋及43×43回頂端原尺寸，flex-shrink:0避免擠縮。

主站8080內建Browser實測兩者computed align／justify均center；桌面搜尋圖示與390手機圓框箭頭已視覺檢查，回頂端點擊與可見焦點保留。手機搜尋的原型顯示規則不變。只改排版，HTML連結、aria-label、API、資料表與環境不變；已建置68頁，不為簡單CSS變更增加鏡像測試。採證在忽略的artifacts/phase9/centered-footer-mobile.png。
