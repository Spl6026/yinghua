# 原始 website 與目前實作複查

日期：2026-10-14。依使用者要求，由三個 subagent 分別檢查公開內容、認證／後台、交付清單；主代理交叉確認、修正與實際驗收。基準為第一提交的 `website/`，不是另做一套設計。本次修正屬原有實作的更正，不新增專案階段。

## 比對範圍與結論

原始 57 個 HTML（35 個公開、22 個後台）全有新前端對應，沒有缺頁。另七個新增網址是已記錄的認證流程，共 64 個來源／建置頁。原始 website 的 68 個檔案與根目錄 Excel 保持第一提交的 bytes；style.css、seo.css、favicon 也保留原內容。

目前網站與原型**不是所有行為都相同**。版型與交付文案應沿用；真實認證、權限及 U-06 唯讀模擬依已授權的階段二實作；內容、收藏、行程等尚未進入的模組不恢復原型的假保存。以下分開記錄實作偏差、原型既有問題及已知功能差異。

## 已修正的實作偏差

| 問題與影響 | 修正 | 主要來源 |
| --- | --- | --- |
| 美食頁初次 render 讀取不存在的 tab 計數 span，JS 中斷，兩張卡片停在透明動畫狀態 | 有計數節點才更新，保留原美食頁沒有計數的外觀 | `frontend/assets/js/public.js` |
| 首頁五個入口只跳不存在的 hash，無法前往散策／活動 | anchor 使用真正的 explore.html／events.html，保留鍵盤與新分頁操作 | `frontend/pages/index.html` |
| 註冊誤用登入標題，既有文字被縮短 | 恢復「讓旅程，有你的名字。」、「顯示名稱」、「建立會員帳號」與登入切換文字；保留必要驗證欄位 | `frontend/pages/register.html` |
| 登入／註冊與 22 個後台頁籤、靜態 metadata 被不必要精簡 | 恢復原 title／分享文字／可解析 JSON-LD 等；認證／後台仍保留 noindex,nofollow 與 no-referrer 政策 | `frontend/pages/` 的 24 個原始路由 |
| 共用 footer 少了原「示範網站｜」，30 頁 active 導覽少了 aria-current | 恢復文字與目前頁面的輔助閱讀標記 | `frontend/templates/site-footer.html`、`scripts/build.mjs` |
| 通用景點詳情的箭頭少了 span，優惠詳情頁籤少了「店家優惠」 | 恢復原箭頭間距／hover 結構及頁籤文案 | `frontend/assets/js/pages/public-details.js` |
| 權限選單只顯示名稱，兩個同名管理員無法區分 | 已授權的 catalog 摘要加入 account，顯示「名稱（帳號）」；不增加 Email | `AccountRepository.php`、`AdminManagement.php`、`openapi.json`、`permissions.js` |
| 管理員讀取失敗後成功重試仍不能保存，重載直接丟棄草稿，舊回應覆蓋新資料 | 統一重算控制狀態、放棄草稿確認、請求序號；保存期間禁止重複提交／重載 | `frontend/assets/js/pages/admin-form.js` |
| 權限保存與切換交錯後錯誤啟用 root 按鈕；重讀失敗被成功訊息蓋過 | 固定保存對象與版本，避免回應覆蓋新對象；保存成功但重讀失敗明示並禁止用舊版本再存 | `frontend/assets/js/pages/permissions.js` |
| 共用提交完成後無條件恢復按鈕，管理保存 401 未採一致逾時流程 | canSubmit 依當前授權／載入／保存狀態恢復，onError 共用 handleAdminError | `frontend/assets/js/core/ui.js` |
| 總計畫現況仍寫只有原型／沒有 API／空資料庫 | 改為真實階段二現況，不改寫成新的專案進度 | `docs/IMPLEMENTATION-PLAN.md` |

這些更正依 U-05（沿用原型）、S-026／S-027（管理員／逐人權限）及 E-14（介面狀態與可讀性）處理，沒有增加 API 路由或業務資料表。

## 原型既有問題與合理改善

- 美食的「在地店家」tab 原本使用「店家優惠」，資料實際 category 是「店家」，原型與舊實作都查不到。只修新 frontend 的分類值，現在可看到兩筆店家；website 保留原樣。
- 原型部分優惠 slug 未帶 query 時誤顯第一筆優惠。新實作依 slug 顯示正確優惠，所以兩頁標題／內容與原型當時的錯誤輸出不同。
- 通用詳情的站內絕對網址、消息 role／aria 拼字、手機 Escape、reduced-motion 等已有改善。這些不是漏掉原型功能。

## 保留並明確標註的差異

| 差異 | 來源與目前狀態 |
| --- | --- |
| 原型 localStorage 模擬登入、免密碼換身分，改為 PHP API／session／MariaDB | 階段二認證與授權；不恢復假認證 |
| 左下可切換身分，但僅固定 admin 能啟動唯讀模擬，不能修改資料 | 使用者指定 U-06；一般管理員不因此取得 root 能力 |
| 確認密碼、初始密碼／狀態、密碼政策、忘記／重設／改密碼等增加欄位與高度 | 已記錄的規格、文件草案或工程選擇；原雙欄、字型、色彩與外框保留，不宣稱新增欄位後等高 |
| 一般管理員 account 建立後不能修改；原型可修改 | **E-09 工程政策，Excel 未明文要求所有 account 固定**。已補來源與說明，不當成規格硬限制 |
| 訪客開 planner 先登入，登入後才看唯讀三站示意 | E-12 前端門檻；原型訪客直接看範例。尚未實作正式行程持久化 |
| 18 個後台內容／會員／設定頁只有原外框、真實登入／權限門檻與待開放提示 | 階段三以後；原型列表與新增／編輯假操作目前未轉成正式功能 |
| 公開內容、搜尋結果仍是交付快照；收藏、套用／移動／保存行程不能正式保存 | 後續內容與會員模組，不能把提示或 localStorage 當正式完成 |
| 動態 SEO、canonical、sitemap、正式內容發布與售票欄位連動未完成 | 後續計畫；靜態原始 metadata 保留不代表正式 SEO 驗收完成 |

規格／文件草案／使用者指定／工程補充的完整區分見 [DESIGN-TRACEABILITY.md](../DESIGN-TRACEABILITY.md)。

## 本次實際驗證

- 靜態對照覆蓋全部 57 個原始網址、共用模板、站內資產與 JS imports；公開原型快照的 6 景點／店家、4 電影、4 消息、3 優惠欄位一致。64 頁 HTTP 回應與建置 HTML bytes 一致。
- 以同一瀏覽器與 viewport 逐頁對照 35 個公開網址。桌面實際 1745×826；內容列表／卡片數一致，未觀察到橫向溢出。planner 訪客登入門檻與兩個優惠 slug 的正確資料屬上述已知差異。
- 手機實際 355×767（覆寫 390×844、使用者縮放影響 CSS viewport），再對照首頁、美食、映演、活動、優惠、搜尋、註冊七頁，列表數一致，未觀察到橫向溢出。註冊必要新增欄位不作等高比較。
- 隔離 8081 實際管理員登入、編輯草稿重載取消／確認、名稱（帳號）選單、root 的 40 欄／保存停用、唯讀模擬 40 欄／保存停用、退出回原身分及登出均確認。瀏覽器沒有保存權限或解註冊挑戰。
- 新增 13 項表單狀態回歸測試，使用真實 ui／session／頁面 JS，模擬失敗／延遲回應，驗證重試、草稿、舊回應、401、root／唯讀及保存交錯；連同原三項測試，共 **16 項 Node 測試通過**。
- 本次重跑真實 MariaDB／HTTP／Mailpit **88 項**與唯讀模擬 **58 項**通過；catalog 的最小摘要契約同步驗證。PHPStan level 10／四條 PHPat 規則零錯誤、PHPUnit 五項通過、PER-CS dry-run 零待修改。Rector 僅 dry-run，八個檔案的建議未套用。
- Docker 重建 64 頁，單一 main／唯一 ID／表單標籤／資產／全部 JS 語法與原交付檔案再次核對。測試只使用 yinghua_test，不重設開發庫 admin 憑證。

這是交付清單、DOM／資料／程式與指定 viewport 的實際審查，**不宣稱全站每個像素、所有表單成功流程、完整 WCAG、外部圖片或正式部署完成驗收**。其他既有驗收項目見 [PHASE-2-ACCEPTANCE.md](PHASE-2-ACCEPTANCE.md)。

原始觀察、Node 重現、對照 JSON、畫面與 Git 歷史備份保留於被忽略的 `artifacts/`，不提交測試密碼或 session。閱讀修正程式見 [modules/frontend.md](../modules/frontend.md)、[modules/admin-permissions.md](../modules/admin-permissions.md)。
