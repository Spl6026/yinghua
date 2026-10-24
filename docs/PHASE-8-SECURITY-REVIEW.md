# 階段八安全審查與映像風險

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

Composer `audit --no-dev` 對應用鎖版依賴：無已知公告／abandoned。Trivy **0.76.1** 官方Docker映像固定digest `790fd66d1697395d8693d1acaa1ea702406a20f109b6fc7bac8db623eca6c06c`；只讀掃描另存的image archive，未掛Docker socket或.env。工具見 [scan-images.py](../scripts/operations/scan-images.py)，結果保留私有artifacts/phase8-scans-final-r5，掃到漏洞exit1，未改成exit0。

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

## 未執行與正式差項

Semgrep／Gitleaks及全Git歷史秘密掃描未執行；不勾成通過。人工審查修改範圍與.env忽略、Composer audit、Trivy映像掃描有實際證據，不能取代未做的工具。沒有把全套skill雲端／K8s／AI掃描套在本專案。

正式SMTP、Google API配額、可信TLS更新、實際主機權限／Windows備份ACL、加密／異地／保留期／RPO／RTO、正式素材與政策版本仍待配置和驗證。備份含個資／密碼雜湊，運維文件明示私有保存；不提交備份、測試.env、APP_KEY、私鑰或帳密。

## 官方參考

- [PHP8.5.12](https://www.php.net/releases/8_5_12.php)、[Nginx公告](https://nginx.org/en/security_advisories.html)：官方安全版本。
- Debian判定：[zlib／CVE-2023-45853](https://security-tracker.debian.org/tracker/CVE-2023-45853)、[SQLite／CVE-2025-7458](https://security-tracker.debian.org/tracker/CVE-2025-7458)、[libxml2／CVE-2026-6653](https://security-tracker.debian.org/tracker/CVE-2026-6653)。
- [gosu1.19主程式](https://raw.githubusercontent.com/tianon/gosu/1.19/main.go)、[Trivy0.76.1](https://github.com/aquasecurity/trivy/releases/tag/v0.76.1)：用途判讀與scanner來源。
- [Compose dns_opt](https://docs.docker.com/reference/compose-file/services/#dns_opt)、[Nginx resolver_timeout](https://nginx.org/en/docs/http/ngx_http_core_module.html#resolver_timeout)：完整連線預算不能只靠PDO timeout。
