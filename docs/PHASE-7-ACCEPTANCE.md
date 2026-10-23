# 階段七本機驗收

日期：2026-10-24。分支 `phase/07-seo-settings-head`，依使用者「實作」授權。對照 [固定計畫](PHASE-7-PLAN.md)、原始 Excel 與 website，完成 SEO 設定、逐頁／範本／單筆覆寫、公開白名單及同版 HTML head；條款／隱私頁維持開發測試說明。PHP 仍只提供 API。P0 階段八、完整 JSON-LD／索引與一般設定未開始。

目前 71 個 API、67 固定頁、33 表／316 欄／18 migration；主／測試庫均已升級。主庫升級前備份在被忽略的 artifacts，未輸出秘密；測試寫入只在 yinghua_test／8081，自有帳號停用、session／內容清理、SEO 快照恢復，正常 audit 保留。未呼叫 Google 或推送遠端。

## 固定清單結果

| ID | 實際結果與證據 |
| --- | --- |
| 7-T01 | seo_settings_schema.py 新空庫／31 表升級均 33／18，重跑安全；原會員／景點／收藏／行程保留。公開內容與會員 schema 複驗通過，臨時 schema 清除；原 17 migration 逐位元組不變 |
| 7-T02–04 | seo_settings_integration.py 74 項／32 回應契約：匿名／會員／無權、view-only、CSRF、唯讀模擬、撤權、完整白名單、舊版本 409、兩客戶端並行一成一拒、audit 故障全 rollback |
| 7-T05 | 143 項 Node 中包含真實 resolver／head／build／表單行為：逐欄繼承、keywords 明確清空、預覽來源及不阻擋的重複提示。API 測試保留無權讀取的草稿覆寫，拒絕盲寫 |
| 7-T06 | seo_head_inventory.py 對 74 個固定／動態／篩選 HTML 回應直接解析，不執行 JS；11 個公開固定頁標題／摘要差異化；單一 head 標籤、canonical／OG、robots、favicon、必要分享值通過。四個無 ID legacy 詳情為預期 404 |
| 7-T07–09 | seo_publication_integration.py 60 項／12 契約：四類詳情自指、實際消息第 2 頁、首頁後綴去重、追蹤／私人 query 排除、強制 noindex、token 純文字、私有 media 拒絕、匿名資產。正式 HTTPS origin 另在隔離副本執行真 build；不是已部署正式網域 |
| 7-T10–11 | 刻意破壞隔離發布紀錄：PUT 回已保存／publicationReady=false，公開 API 與固定 HTML 同回 503，管理／認證仍可用；移除自有故障後同版恢復。模板雜湊、設定版本、內容下架與既有改 slug／alias 回歸通過。no-store 維持；私人保存不觸發公開 SEO 發布 |
| 7-T12 | Brave 實際 1440×900／390×844：原型側欄／頂列／面板與整列欄位對照、details／Tab／可見焦點、422 錯誤欄位、保存中互斥、409 保留輸入、重讀確認取消／確認、待發布及復原、手機導覽、換唯讀帳號讀回版本且不能修改。跨客戶端讀回另用獨立 Cookie 的 HTTP 客戶端；未冒稱使用第二套瀏覽器引擎 |
| 7-T13 | seo_settings_restart.py 4 項：只重建 app-test／web-test，既有管理 session、設定版本／覆寫與公開版本保留；未重啟 DB／刪 volume。資料層 14 項 rollback，PK／唯一路徑 EXPLAIN 命中 PRIMARY／seo_path；內容 lookup 每類批次，不逐頁 N+1 |
| 7-T14 | 階段二至五 21 組契約整合＋5 組消息／優惠／首頁／安全／原型回歸全部完成；789 回應與260 成功輸入契約、54 個原有操作，零差異。階段六三組合計229檢查／106回應契約，五輪發布切換通過 |
| 7-T15 | PHP 72 項／282 斷言；PHPStan10／PHPat 零錯；244 PHP 檔格式 dry-run 零修正；Node143 全通過；建置67頁。frontend_integrity.py：67頁／14資產／81 HTTP，issues=[]。Rector dry-run 正常回報38檔建議，審閱後未套用，退出碼2表示有提案，不能寫成零差異 |
| 7-T16 | policy_pages_integration.py 13 項：公開條款／隱私／註冊的真實 TERMS_VERSION、另開連結、既有同意紀錄數不變；瀏覽器確認未提交輸入保留，未勾同意／建立會員。正式文案未提供 |
| 7-T17 | 原始69檔、原CSS、舊migration、.env忽略及工程文件連結核對；臨時內容／故障清理，正常安全紀錄保留。防毒隔離的既有 skill 檔案刪除保持原狀，不納入提交 |

## 介面與安全審查

使用 php-modernization、security-audit、mariadb-query-optimization，以及 [當日 Web Interface Guidelines](https://raw.githubusercontent.com/vercel-labs/web-interface-guidelines/main/command.md) 審查本次 frontend 範圍。修正已併回相應功能：錯誤控制項先恢復再聚焦、唯讀搜尋不記成待保存、加入覆寫立即有預覽、原型字體與整列欄位、管理頁既有「後台」標題不重加品牌。所有欄位有標籤，狀態可播報、無手機橫向溢出，保留原始 CSS 不改。沒有宣稱逐像素相同；權限側欄、真實狀態、單筆選擇／預覽與未開放提示為必要差異。

主要畫面證據位於本機 artifacts：phase7-settings-original-desktop.jpg、phase7-settings-desktop.jpg、phase7-settings-override-mobile.jpg、phase7-settings-conflict-mobile.jpg、phase7-settings-pending-mobile.jpg、phase7-settings-readonly-desktop.jpg、phase7-privacy-mobile.jpg。API／測試結果檔不含請求帳密，瀏覽器臨時憑證與資料庫備份保持 Git 忽略。

安全審查範圍為授權／CSRF、版本與交易、草稿及 media 邊界、輸入 URL／token、HTML 編碼、公開投影與發布失敗。沒有使用外部抓圖、任意 SQL／路徑或 PHP View；未執行 Semgrep／Trivy 等外部掃描，不將 skill 安裝當作掃描通過。

## 保留限制與正式環境差項

- 本輪直接執行舊會員工具曾在第二個測試會員註冊失敗；當時工具未記錄回應碼，不能斷言原因。改以現有瀏覽器的「僅明確 CSRF_INVALID 更新重試一次」流程加記錄後，三組229項完成、CSRF更新0次；原跨模組26組則有一次註冊 CSRF 更新。這延續階段六已記錄的偶發驗證問題，本階段沒有修改認證或宣告其根因已消除，也沒有自動重跑業務錯誤。
- 正式 HTTPS 網域／TLS、業主正式條款與版本、品牌分享素材／權利，以及 Google／Bing 平台驗證／搜尋收錄與實際分享預覽，仍待正式環境與業主提供。保護頁共用 dist 的 origin 取 APP_URL；8081 測試站共用8080的固定 shell，但公開內容使用各自 publication。正式部署副本使用 HTTPS build 已驗，不等於正式主機驗收。
- 完整 JSON-LD、動態 sitemap／robots、一般聯絡／品牌／政策編輯與後續營運介面維持原優先級。階段七完成本機範圍，不代表全部 P0 或正式上線完成。

重現命令與每個檔案責任見 [SEO 指南](modules/seo-settings.md) 及 [全站導讀](PROJECT-GUIDE.md)。
