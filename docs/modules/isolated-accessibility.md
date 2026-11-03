# 隔離讀屏驗收環境

2026-10-26 最新補驗：使用者已另外明確允許專用 Windows 瀏覽器視窗；真實 NVDA 2026.2 已補完整確認訊息、欄位錯誤、收藏狀態與英文切換，見 [50 項 AA 結果](../archive/P0-AA-VERIFICATION-20261026.md)。這是另行允許的 Windows 流程，沒有改變下述容器工具不連接主機輸入／音效的隔離架構。自有 NVDA 已停止；Orca 前輪的自動讀取消與傳輸限制仍是該組合的歷史觀察，不與 Windows 結果混寫。網站整體仍有 AA-01／AA-02 不通過，不再籠統記為所有讀屏均未驗。

2026-10-26 登入後補驗的實際流程、輸入錯位、載入等待及保留差項見 [本輪結果](../archive/P0-COMPLETE-VERIFICATION-20261025.md#2026-10-26-登入後與確認框補驗)。Orca 官方 [表單操作](https://help.gnome.org/orca/howto_forms.html) 及 [網頁偏好](https://help.gnome.org/orca/preferences_web.html) 區分 Browse／Focus；本環境可用 CUA `pressKey(null, 'Insert+a')` 明確切換，再核對實際欄位焦點後輸入。畫布逐字 `press()` 可傳文字，對畫布的 bulk fill 不是遠端欄位輸入；快速鍵、反向 Tab 與 DOM／讀屏焦點不能互相冒充。這是私有桌面的操作約定，不改網站欄位或授權規則。自動開框只先讀取消、收藏成功通知未有自動語音證據，仍如實保留。

2026-10-26。來源 U-19／E-30：使用者要求處理 Windows 讀屏與前景操作干擾遊戲的問題。這是驗收工具，不是新增網站功能或正式部署元件；Excel「功能規格」G34／串接總覽 B15 的無障礙驗證仍須依實際覆蓋判定。

## 架構與檔案

背景 IAB → 本機 `127.0.0.1:6091` noVNC → 私有 Xvfb／Firefox／AT-SPI／Orca。Firefox 內的 `localhost:8081` 經容器內代理連到既有測試網站，維持網站 Origin。Windows 不啟動 NVDA，不掛載主機鍵盤、滑鼠、音效、X11、瀏覽器個人資料或 Docker socket，也不使用 Windows 前景視窗操作。

| 檔案 | 用途 |
| --- | --- |
| `tools/accessibility/Dockerfile` | 官方 Debian 固定 digest；從 Debian 套件庫裝 Firefox ESR、Orca、AT-SPI、noVNC 與私有桌面元件。基底固定不代表 apt 套件永久同版，採證另記錄實裝版本 |
| `tools/accessibility/compose.yaml` | 獨立 `spl-accessibility` 專案；非 root、唯讀根目錄、移除 capabilities、禁止提權；0.75 CPU／1 GiB RAM／512 程序與執行緒上限，僅綁 loopback 6091 |
| `tools/accessibility/entrypoint.sh` | 建立私有 X 認證與 VNC 認證，啟動無 TCP 的虛擬顯示器，關閉這個顯示器的閒置黑屏 |
| `tools/accessibility/session.sh` | 啟動私有 D-Bus、桌面、無主機音效連接的語音服務、Orca、Firefox、VNC 與網站 Origin 代理；不含 UI 操作腳本 |
| `tools/accessibility/run.ps1` | 建立被忽略的臨時 VNC secret、建置與重建自有桌面；`-Stop` 只停止這個專案；不開可見主機視窗 |
| `tools/accessibility/collect.py` | 擷取真實 `SPEECH OUTPUT` 與版本／隔離 metadata，檢查資源及安全設定；只寫 ignored `artifacts/`，不匯出原始鍵盤 debug 事件、不覆蓋既有採證 |
| `.dockerignore`／`.gitattributes` | 建置 context 僅含 Dockerfile 與兩個 shell；shell 固定 LF；密碼不進 image／Git |

桌面 home、Firefox profile 與暫存位於 tmpfs，重建後不保留。`evidence` named volume 保存自有服務日誌；原始 Orca debug 可能含測試輸入，只使用隔離假資料，不用正式帳號或個人秘密。VNC 使用一次性的 8 字元傳統認證，僅供本機短期驗收，不作正式遠端桌面部署。Docker 的 CPU／記憶體上限可控制負載，不能保證遊戲完全沒有資源競爭；不用時停止服務。

## 操作

在專案根目錄使用 PowerShell 7、Docker 與 Python 3。先確定既有測試網站 `http://localhost:8081` 可讀且測試發布版有效；本工具不執行主站發布、不重設資料庫。

```powershell
./tools/accessibility/run.ps1
# 背景瀏覽器開 http://127.0.0.1:6091/vnc.html
# 在密碼欄使用 ignored tools/accessibility/.env.vnc-password；不要放 URL 或提交文件。
python tools/accessibility/collect.py --directory artifacts/my-reader-check --label login-required
./tools/accessibility/run.ps1 -Stop
```

`-BuildOnly` 只建置。每次啟動重新建立暫存桌面，先採證再重建；不要以 `restart` 代替可重複的乾淨驗收。健康檢查包含 HTTP 與非 zombie 的 Xvfb／Orca／Firefox 程序；healthy 只代表環境就緒，仍須實際網站畫面和語音證據。沒有掛載音效裝置，驗證的是 Orca 真正輸出的語音文字，不宣稱人工聽音品質已驗。

網頁操作只經 CUA 的背景 noVNC 分頁。輸入與畫面為遠端容器的事件，不使用主機 SendKeys 或前景切換。遠端畫面更新有延遲，應分步確認；快速連續鍵若有焦點或朗讀跳動，不據此宣告網站鍵盤順序通過。需 Windows NVDA 專屬相容性時，另排不干擾使用者的設備／時段，不能用 Orca 結果冒充。

## 本輪實測與界線

證據放 `artifacts/isolated-a11y-20261026/`，不提交可能含測試輸入的原始採證。Orca 48.1、AT-SPI 2.56.2、Firefox 153.5.1esr；可取得網站載入、地標／標題、登入表單欄位與必填警示、註冊確認密碼必填的真實語音。詳細場景與剩餘範圍見 [本輪驗收](../archive/P0-COMPLETE-VERIFICATION-20261025.md#2026-10-26-隔離讀屏補驗)。

建立環境時曾有 Firefox 啟動退出及 `Resource temporarily unavailable`，實際存活程序／執行緒後續觀察超過原 256 配額，已調為有限的 512 並改善 zombie 健康檢查；沒有放寬 seccomp／瀏覽器 sandbox。Firefox 仍記錄 user namespace `EPERM`，不宣稱所有瀏覽器 sandbox 層均成功啟用。這些是驗收環境事項，不列為網站缺陷或完整 AA 結果。

參考：[Orca 手冊](https://manpages.debian.org/trixie/orca/orca.1.en.html)、[結構導航](https://help.gnome.org/orca/howto_structural_navigation.html)、[noVNC](https://github.com/novnc/noVNC)、[VNC 密碼限制](https://manpages.debian.org/trixie/tigervnc-tools/tigervncpasswd.1.en.html)。
