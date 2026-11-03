# 階段十五安全審查與正式差項

2026-11-01。使用security-audit技能；範圍是交付CLI、匿名邊界、擴充復原與本輪正式封裝映像。不是第三方滲透測試或全面安全認證。

## 已執行

- 本輪66個受保護操作匿名均被拒絕；自有管理員與會員登入／登出後401。既有owner、CSRF、409及權限失敗由P1 181契約與P2 73項整合增量回歸；沒有重設既有root密碼或新增root瀏覽器權限。
- 17項新空庫部署、32項還原、9項回退通過。非空目標／損毀媒體／不相容migration拒絕，復原撤銷session與token、失敗不開流量，日誌敏感query不外洩；TLS檢查未停用。舊隔離憑證過期後另建私有測試憑證，只由測試客戶端明確載入，不變更OS信任庫。
- Composer正式lock audit回傳advisories／abandoned皆空。這不是OS映像掃描結果。
- Trivy 0.76.1 pinned image實掃本輪app／web／db封裝，secret皆0。報告在忽略的artifacts/phase15/image-scans；沒有把Docker socket、.env或主庫掛進掃描器。

## 不能宣告安全全綠

| 映像 | HIGH | CRITICAL | 掃描器列可修項數 | 結果 |
| --- | --- | --- | --- | --- |
| app | 69 | 3 | 2 | 未通過映像門檻 |
| web | 0 | 0 | 0 | 本輪掃描門檻通過 |
| db | 25 | 1 | 26 | 未通過映像門檻 |

這些是套件公告項目數，不等於已證明網站可被利用；亦不能未經分析便當誤報全部排除。app的兩個可修項是libssl3／openssl對同一CVE的兩筆套件記錄；Debian官方列bookworm security修補版為3.0.22-1~deb12u2，見[官方追蹤](https://security-tracker.debian.org/tracker/CVE-2026-84782)。其他critical在報告分別標affected／fix_deferred／will_not_fix，不冒稱沒有影響。

使用者U-18要求網站與既有正式server環境分開驗收；本輪不以Docker問題取代網站功能驗證，也沒有修改已固定的PHP／MariaDB映像、降低掃描門檻或刪除公告。**實際server的PHP／OpenSSL／MariaDB及OS套件需由部署者修補並重新掃描／評估，正式安全門檻仍未簽核。** 若採本倉庫Docker封裝上線，也必須先處理此表，不能直接把本機功能通過當成映像可上線。

正式HTTPS、外部SMTP、Google來源／配額限制、平台索引與分享、異地加密備份／ACL及保留期亦依[交接清單](../PRODUCTION-HANDOFF.md)取得實際證據。未新增病毒掃描或推定郵件驗證政策；U-14的圖片內容檢查範圍保持。

## U-23補位的增量安全審查

2026-11-01，依security-audit檢視本輪新管理／公開API。POST預覽沿管理session、CSRF與attractions.create或edit，view-only禁止；共用Input白名單與容量，不儲存。匿名受保護操作增為67。公開Google端點不接受任意Place ID或URL，固定places.googleapis.com、沿既有CurlGoogleHttp傳輸與最小field mask；金鑰不上前端、錯誤內容不回傳。

Google原文只存在當次回應與DOM，以textContent完整呈現，提供者URL限HTTP／HTTPS且拒絕帳密；不進發布檔／SEO／JSON-LD／搜尋／DB／localStorage。服務初始與外部請求完成後均核對DB發布狀態／版本及公開快照；並行下架時丟棄回應。全站100／日、30／分鐘、來源10／分鐘、地點6／分鐘與每DB一個並行請求屬E-40資源保護；供應商帳務仍需Google專案配額，不保證匿名呼叫不會用完額度。

新增真MariaDB八項涵蓋限速、並行拒絕與請求中下架；HTTP含原值不回寫、未發布404、任意ID query拒絕、view-only403及CSRF／外站Origin拒絕。Google替身涵蓋429／無內容／錯誤／缺key；另真Google實測available，只存48字長度等元資料。新UI依web-design-guidelines審查來源區、換行、焦點、aria-live與狀態；送出不丟失焦點、輸入後不顯示舊預覽。

本次沒有新依賴、DDL、env、權限種類或公開DB port；未重新掃描映像或執行整個Semgrep／Gitleaks，前表與歷史靜態掃描是已標日期的基線，不能冒稱本輪全綠。正式政策應補Google服務條款／隱私相關揭露，見交接清單。

## 2026-11-02 重驗增量

P0完整認證／權限／輸入與公開內容安全套件在全新具名副本完成；P1/P2、67匿名操作及兩種登入登出亦重跑。收藏離頁修正只等既有CSRF與身分檢核寫入確認，不使用keepalive、不寫私人storage或自動重送；站內URL比較同origin，修飾鍵／新頁／下載不攔截，失敗保留原頁。共用時區格式器只處理呈現，不改DB／API／權限。Composer正式lock本輪audit無公告；沒有重掃Semgrep／Gitleaks／OS映像，不消除前表HIGH／CRITICAL正式差項。詳見[三優先級重驗](P0-P1-P2-REVERIFICATION-20261102.md)。
