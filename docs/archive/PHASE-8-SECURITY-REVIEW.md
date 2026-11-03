# 階段八安全審查與映像風險

2026-10-26 到期提示修正已依 security-audit 審查：期限標頭不含憑證，閒置／絕對上限及伺服器授權不變；只在使用者明確操作時延長，重新登入仍用既有密碼／CSRF／限流／撤銷流程。51 項真實隔離 HTTP／MariaDB 測試涵蓋兩種身分各 11 次延長、到期拒絕、舊 token 撤銷、CSRF、越權與停用；公開索引只讀同版白名單快照並轉義，沒有新依賴、API、資料表或主庫發布指令。完整上游映像／正式環境差項沿用，不冒稱本輪重掃。見 [修正紀錄](P0-AA-REPAIRS-20261026.md)。

2026-10-26 E-30：依 security-audit 的 Docker／IaC 範圍審查新 `tools/accessibility/`。實際 inspect 及採證斷言確認非 root、唯讀根目錄、cap_drop ALL、no-new-privileges、非 host network、無主機裝置／Docker socket／X11／音效掛載、僅 loopback 6091 與 CPU／RAM／程序配額；Docker context 白名單不含 secret，Git 忽略 VNC 密碼及採證。健康檢查辨別 zombie，CLI 不開主機可見視窗。這是短期本機 VNC 傳統認證，沒有外網 TLS／正式遠端桌面用途；Firefox user namespace EPERM 保留，不宣稱所有 sandbox 層正常，亦未放寬 seccomp。原始 Orca debug 僅私有 volume，採證不匯出原始按鍵；只用隔離假資料，不輸入正式秘密。工具新映像本輪未重跑 Trivy／Semgrep／Gitleaks，不追認掃描無漏洞。沒有認證／管理 API 或應用碼變更，既有安全回歸不重記為本輪結果。見 [指南](../modules/isolated-accessibility.md)／[實測](P0-COMPLETE-VERIFICATION-20261025.md#2026-10-26-隔離讀屏補驗)。

2026-10-25 21:40：U-18 指定既有 server 為交付目標；下方 Docker CVE 屬環境交接資料，不作本次網站功能驗收的阻擋條件，亦非已修復／全部誤報。本輪僅 CSS 遮罩／重排，無 PHP／授權／session／DDL 變更，沒有重掃映像或降低安全控制；[介面補驗](P0-COMPLETE-VERIFICATION-20261025.md#2026-10-25-2140-網站介面補驗) 如實記錄其餘驗證及限制。

2026-10-25 20:15 前端修正：解析錯誤經現有 element／textContent 顯示，獨立查詢欄不混入保存 DTO；返回 URL／文字來自固定建置路由表，create-only 去向沿既有授權資訊，沒有變動 PHP／API／session 政策。152 項 Node 回歸與真非法域名拒絕／Google 重試完成。本輪未重掃映像、未手動主環境 content:publish；原有 CVE／人工 AA／正式門檻仍保留，見 [修正追記](P0-COMPLETE-VERIFICATION-20261025.md#2026-10-25-2015-修正後結果)。

## 2026-10-25 15:21 完整驗證輪

來源 `343bd7e`，範圍見 [完整驗證](P0-COMPLETE-VERIFICATION-20261025.md#安全及尚未完成的驗收)。本輪真 DB／HTTP 授權、CSRF、session、媒體／SSRF／XSS、發布故障及 TLS／還原／回退重跑；沒有降低安全設定或改動應用。

- Semgrep 1.182.0／同官方規則 commit：300 檔、256 執行規則、121 命中；78 邊界核對、18 heuristic 不符、21 相容性選擇、1 工程選擇、3 強化待辦。**publish.mjs:115 有 1 個 PartialParsing warning**；Node 執行與人工 escaping 核對不等於 scanner 完整覆蓋，未 suppress。
- Gitleaks 8.30.1：全可達 refs、離線遮蔽 18 命中／8 個唯一 skill 教學位置，與前輪 rule／file／line 集合相同；未對外驗 key，不保證全無秘密。Composer 應用 audit 無已知公告／abandoned。
- 新建 app `302d8e34…`：77 HIGH／3 CRITICAL；web `361f11ac…`：0／0；同 DB `5d313c7a…`：25 HIGH／1 CRITICAL；秘密命中均 0。保留 exit 1，DB 26 個 Go metadata 修補不代表官方 gosu 已更新。下方 vendor 分析仍為待處理，非全部誤報／安全通過；Debian SQLite／libxml2／zlib 狀態本輪再查官方 tracker。
- Google 網址錯誤未關聯輸入欄，非法網域本身仍由服務端拒絕；另有 AA 對比差項，見 V-01／V-03。
- SEO 兩表逐列還原、隔離 root 原摘要復原並撤銷 UI session、具名帳號停用及內容／媒體清理；三個自有 production projects／volumes 清理，主庫及其他服務保持。正式安全門檻、root master／pipeline 強化與完整人工 AA 保留。

下方較早日期數值與「未重掃」為各輪歷史，不代表本輪未執行。

## 2026-10-25 後續修正審查

公開列表 context 只含已發布的目前主題 SEO／公開媒體白名單，JSON 編碼 `<`／`>`／`&`，head 更新使用文字與屬性；沒有新增 HTML 注入或外部設定請求。PublicationPending 僅將固定原因／區塊名稱及已驗格式 hash 寫入日誌，API／gate 在邊界加 requestId；錯誤 body 不帶診斷。真 DB／HTTP 8 項驗對應與內容不外洩、7 項驗 alias 順序不誤判而真下架仍拒舊；相關發布失敗／契約回歸通過，見 [修正紀錄](P0-IMPROVEMENTS-20261025.md)。本輪未新增授權、降低 gate、改密碼政策或使用正式帳號；具名測試設定已復原。沒有修改映像／依賴或重跑 scanner，因此下方上游 CVE 與正式門檻保持待處理。

## 2026-10-25 複驗更新

本輪應用來源 `1fd4f14`，新增實際 Semgrep／全可達 Git refs Gitleaks 掃描；完整範圍、版本、逐筆分類與限制見 [複驗報告](P0-REVERIFICATION-20261025.md#安全掃描與尚未關閉的風險)。下方「未執行」是前輪歷史，現改以本節為準，未刪除原結果。

- Gitleaks 8.30.1：18 命中均在第三方 skill 教學文件，8 個唯一檔案／行位置跨歷史 refs 重複；人工遮蔽核對為範例，應用路徑 0 命中。沒有將疑似 key 對外驗證，也不能推論全無秘密。
- Semgrep 1.182.0：官方規則 commit `16b9e6d45f10649f6a531ec4838ed9924372c3bc`，256 rules／298 files，115 命中、0 parse errors。逐筆分類為 72 信任邊界核對、22 heuristic 不符、17 相容性選擇、1 OS 更新選擇、3 工程強化待辦（兩個 root master container／一處 pipeline pipefail）。這些分類不是全無漏洞或全面誤報宣告。
- 掃描離線執行，只掛所需 Git 或 source／規則；沒有 `.env`／Docker socket。命中保留 exit 1，沒有新增 suppress／ignore。
- 同來源新映像 a：app `63f484f5…` 為 78 HIGH／3 CRITICAL、web `1c0aa3de…` 為 0／0、DB `5d313c7a…` 為 25／1；秘密命中均 0。風險與下方 vendor 判讀一致，未因重掃而關閉。
- Composer 應用 audit、真 MariaDB 越權／失敗、HTTPS Cookie 及發布／復原故障重新執行；優惠首次 503 仍未定位，不把診斷後成功當作穩定性已修復。

本輪沒有修改應用 PHP／JS／CSS 或容器權限，只有兩個測試工具的隔離／診斷改善。正式安全門檻、完整人工 AA 與實際部署條件仍未完成。

日期：2026-10-24；依 security-audit 審查本次health、CSRF、DB錯誤、正式Docker與復原CLI，不宣稱整站滲透或正式安全認證。PHP8.5.12官方映像維持；程式未降低密碼／驗證碼／授權政策。來源E-29，業務依Excel／U既定規則。

## 已修正並驗證

| 邊界 | 實作／實際證據 |
| --- | --- |
| CSRF時鐘與觀測 | HMAC／來源／cookie／namespace仍需正確，僅有限5秒未來時間預算，過期不放寬；12受控案例。只記原因及requestId，無token/body。 |
| 健康資訊 | 精確匿名路徑提前組裝、只讀probe／無cookie；最小200及固定503，外部不含DB／migration／例外細節。18項真DB故障及6真HTTP契約。 |
| 可用性回應 | 普通PDO連線失敗安全503，DNS／connect／read預算，Nginx上游／公開gate失敗503；停DB／FPM及恢復實測。一般SQL缺陷仍500，不隱藏成健康問題。 |
| 授權與輸出 | 既有真MariaDB管理／會員越權、唯讀模擬、root、XSS／media／SSRF回歸本輪通過；health精確路徑不繞過一般API管線。 |
| 發布版／秘密 | selective COPY，不封入.env／測試／工具／demo資料；app/web同manifest與origin／policyVersion；正式DB與FPM無hostport。Trivy映像秘密0筆不等於全Git歷史掃描。 |
| TLS／cookie | 自有隔離TLS1.2／1.3、308、HSTS、Secure／HttpOnly／SameSite；Python驗測試憑證及hostname，未忽略驗證／改OS信任。正式信任鏈仍待驗。 |
| 日誌 | FPM不記URI/IP，正式Nginx不記query，原始error log抑制以免上游記token；安全status/requestId及PHP例外類別仍可診斷。9項回退演練含正常／上游停止兩種秘密query marker均未進日誌。CLI僅生命周期錯誤帶經環境秘密過濾的stderr，SQL等資料錯誤不轉印；2專用測試。 |
| 復原 | 新空target、可信備份、hash／tar traversal／symlink／媒體／schema／APP_KEY指紋閘門；授權撤銷後才重新發布，失敗不開流量。25真DB還原＋4 host unittest。checksum不當成防惡意來源簽章。 |

## 實際掃描（不是全綠）

Composer `audit --no-dev` 對應用鎖版依賴：無已知公告／abandoned。Trivy **0.76.1** 官方Docker映像固定digest `790fd66d1697395d8693d1acaa1ea702406a20f109b6fc7bac8db623eca6c06c`；只讀掃描另存的image archive，未掛Docker socket或.env。工具見 [scan-images.py](../../scripts/operations/scan-images.py)，結果保留私有artifacts/phase8-scans-final-r5，掃到漏洞exit1，未改成exit0。

| 最終r5映像 | HIGH／CRITICAL | scanner列有修補版本 | 秘密命中 |
| --- | --- | --- | --- |
| app `245b49f0…` | 78／3 | 0 | 0 |
| web `1205d3fd…` | 0／0 | 0 | 0 |
| db `5d313c7a…` | 25／1 | 26（gosu內Go標準庫metadata） | 0 |

初版app284HIGH／7CRITICAL（2項已有修補）、web9HIGH；已更新release OS套件、移除編譯器／headers／git等非運行依賴，web採官方Nginx1.30.6-alpine-slim，DB更新同12.3.3官方digest。未為消除scanner數字任意換OS／DB或自己重編安全敏感的gosu。

### 剩餘CRITICAL判讀與處理

- **CVE-2025-7458／libsqlite3-0**：Debian bookworm無當前修補（no-dsa）。本案PDO只用MySQL，不提供任意SQL入口；此為降低暴露的推論，不等於完整可達性證明。後續追官方修補及所需runtime依賴。
- **CVE-2026-6653／libxml2**：Debian當前延後修補；有internal DTD的XML解析UAF。網站不接受XML上傳，圖片限JPEG／PNG／WebP；仍未完成所有擴充／第三方套件的可達性證明，保留風險，不列全面誤報。
- **CVE-2023-45853／zlib**：Debian官方指出漏洞在未從該source package建置的contrib/minizip，bookworm該二進位不受影響。保留原掃描及vendor判定作候選誤報說明，不用ignore檔消音。
- **DB Go／gosu**：最新版本次官方MariaDB12.3.3仍帶gosu的Go1.24.6標準庫metadata，含CRITICAL **CVE-2025-68121**（crypto/tls）及25HIGH。gosu1.19是本機降權CLI，其主程式無網路daemon用途；可推論TLS類風險暴露較低，**未證明26項全部不可達**，scanner所列Go修補版不是已更新的官方DB二進位。正式交付前須追新版官方映像／逐項審核及明確風險處置。

剩餘78HIGH／3CRITICAL與DB26項不因無vendor fix或推論而算通過。正式上線安全門檻尚未完成；完整report包含套件版本與CVE對照，不含應用秘密。不把漏洞數直接等同26個可遠端利用的網站缺陷，也不反向宣稱完全安全。

## 2026-10-24 當輪未執行與正式差項

10月24日前輪的Semgrep／Gitleaks及全Git歷史秘密掃描未執行；不勾成通過。10月25日本輪已補做，結果見本文件開頭與複驗報告。人工審查修改範圍與.env忽略、Composer audit、Trivy映像掃描有實際證據，不能取代未做的工具。沒有把全套skill雲端／K8s／AI掃描套在本專案。

正式SMTP、Google API配額、可信TLS更新、實際主機權限／Windows備份ACL、加密／異地／保留期／RPO／RTO、正式素材與政策版本仍待配置和驗證。備份含個資／密碼雜湊，運維文件明示私有保存；不提交備份、測試.env、APP_KEY、私鑰或帳密。

## 官方參考

- [PHP8.5.12](https://www.php.net/releases/8_5_12.php)、[Nginx公告](https://nginx.org/en/security_advisories.html)：官方安全版本。
- Debian判定：[zlib／CVE-2023-45853](https://security-tracker.debian.org/tracker/CVE-2023-45853)、[SQLite／CVE-2025-7458](https://security-tracker.debian.org/tracker/CVE-2025-7458)、[libxml2／CVE-2026-6653](https://security-tracker.debian.org/tracker/CVE-2026-6653)。
- [gosu1.19主程式](https://raw.githubusercontent.com/tianon/gosu/1.19/main.go)、[Trivy0.76.1](https://github.com/aquasecurity/trivy/releases/tag/v0.76.1)：用途判讀與scanner來源。
- [Compose dns_opt](https://docs.docker.com/reference/compose-file/services/#dns_opt)、[Nginx resolver_timeout](https://nginx.org/en/docs/http/ngx_http_core_module.html#resolver_timeout)：完整連線預算不能只靠PDO timeout。
