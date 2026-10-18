# Excel 基準校正交付

2026-10-18。範圍為已實作的階段二、三；不新增下一階段功能。原始 Excel 與 website 均保留。回溯發現與當時證據見 [回溯報告](SPECIFICATION-BASELINE-REVIEW-20261018.md)，資料表完整對照見 [資料庫對照](DATABASE-SPECIFICATION-MAP.md)。

2026-10-18 U-15 覆蓋先前確認選填決定：確認密碼必填且一致。下方355項、37項PHP／66項前端及七個快照屬先前校正當次紀錄；本次確認密碼更正的實測結果另列於文末，不能將舊選填通過當成新政策通過。

## 已落實的行為

| 回溯項目 | 校正後 |
| --- | --- |
| R-01 地址必填 | 地址選填，省略／空白保存空字串；HTML、DTO、OpenAPI 一致 |
| R-02 無圖禁止發布 | 圖片選填，可發布無圖內容，公開頁使用既有替代圖；不偽填來源／授權文字 |
| R-03 來源／授權必填 | 上傳 source／license 可省略或空白；alt 仍必填，格式、尺寸、解碼與重新編碼仍驗證 |
| R-04 一般帳號不能改名 | 一般帳號可改名；UTF-8 非空文字、60 字資源邊界、唯一性及固定 admin 保護保留。舊登入失效，新帳號可登入；身分與稽核仍以 UUID 關聯 |
| R-05 首登強制改密碼 | 經 U-13 明確授權取消；既有旗標清零，新增預設 0，API 相容欄位 mustChangePassword 固定 false 並標 deprecated；本人改密碼與重設保留 |
| R-06 帳號字首／三字／ASCII 限制 | 移除未指定的字首、regex 及最短三字限制，005 改 DB 字元集為 utf8mb4_unicode_ci，唯一索引保留 |
| R-07 openingHours 契約 | 新增及編輯均可省略週表，契約不再列必填；有送資料時仍驗證完整結構。原生表單仍依 U-09 使用同一週表 |

其餘政策也已定案：密碼至少八字沿用原型、最多 128 個 Unicode 字元為資源邊界；確認密碼依最新 U-15 必填且逐字一致；省略、空字串、錯誤型別及不一致均拒絕。slug 新增留空由系統產生，編輯留空保留舊網址；行政區可未知，不推測北區。手填及解析地圖網址同樣採 G20 的 HTTPS／Google 入口規則。自我停用、軟刪除、降權僅在操作者本來具該操作權限時允許；最後管理權由不可停用／刪除／降權的固定 root 保護。

### 明確的使用者選擇

- U-13：自動核准審查曾拒絕降低密碼門檻、取消首登及移除自我操作限制；在列明三項行為及風險後，使用者回答「全部同意，依上述範圍套用」。只依該明確範圍實作，不將泛稱「以 Excel 為準」當成其他安全設定的授權。
- U-14：使用者選擇「維持圖片內容檢查，文件明確說明掃描範圍」。現況為 MIME、大小／像素、實際解碼與重新編碼；沒有獨立病毒掃描服務，不宣稱已通過病毒掃描。
- U-01／02／05／06／09／10／11 保留：PHP API-only／MariaDB／Docker、既有版型、唯讀模擬、共用週表、Google 原文參考、摘要與介紹選填。自動文案補位仍延後，不能假裝已實作。

## 資料庫與環境

新增 005（一般帳號字元集及 root trigger）、006（選填地址／行政區、操作者外鍵）、007（核准後清除舊首登旗標與預設值）。既有已套用 migration 內容／checksum 不改。共八份 migration、20 張業務表與 schema_migrations 工具表；沒有新增業務表、API 或 Docker 服務，API 仍 44 操作，HTML 仍 64 固定頁。

主庫 yinghua 與隔離庫 yinghua_test 均先備份再升級，皆可重跑。主庫套用後逐表核對，20 張業務表的資料筆數與升級前一致；007 僅更新核准的舊旗標。完成後重建 frontend、重啟既有 publisher 並產生公開版本。資料／媒體／公開頁 volumes 及 `.env` 未重建，正式管理員密碼未修改。

006 初次在隔離庫遇到外鍵 IF NOT EXISTS 語法錯誤，停在 journal 的第一個 statement。只修正尚未交付的 006 與隔離庫未完成項 checksum 後續跑；主庫使用修正後檔案，未修改 001–004 的既有紀錄。DDL 依 MariaDB 12.3 實際結果處理，不把錯誤算成通過。

## 程式閱讀

- Input.optionalText 共用選填文字；Input.password 共用八字政策；Input.confirmedPassword 供註冊／重設／更改密碼共用必填確認。仍驗證型別、UTF-8、NUL、長度與 JSON 白名單。
- AdminWriteInput／AdminManagement／AccountRepository 與 admin-form 共同處理一般帳號改名。PermissionPolicy 保護固定 root；個人授權與角色變更仍重查真正權限、交易與稽核。
- AttractionInput.withDefaultSlug 將留空與有效網址識別分開；Service 在交易內以舊 slug 或新 UUID 派生識別補足。媒體存在時檢查 FK，無媒體不再阻擋發布。
- AttractionRepository.save 只接受服務層傳入的 actorId，保存 created_by／updated_by；HTTP 不能傳這兩欄冒名。舊記錄沒有可靠建立者則保留 NULL，不虛構歷史。
- GoogleUrlPolicy 同時驗證手填與解析入口；官網外連仍依 U-10 可為 HTTP／HTTPS，後端不抓取官網。
- must_change_password 舊 DB 欄與回應欄暫留相容，已無業務門檻；Kernel、PermissionPolicy、前端 guard、唯讀模擬與媒體存取不再使用此限制。

## 實際驗證

- Docker PHPStan level 10／PHPat 通過；PHPUnit 37 項、182 斷言通過。
- PER-CS 123 個 PHP 檔 dry-run 零差異；Rector 僅 dry-run，建議已檢視、不自動套用，不能當成零差異。
- 先前已實測空庫 migration、重跑及角色初始化；當時 CLI 確認選填已依 U-15 改為必填。臨時驗證庫已移除。
- 前端 66 項測試通過，包含新增的帳號改名送出及圖片來源／授權留空上傳；64 頁建置通過。
- 10 組真實隔離 HTTP／MariaDB 測試共 355 項：認證 88、唯讀模擬 58、景點 65、契約 9、文案選填 19、既有景點修正 21、週表 21、候選欄位 10、Google 關聯 15、本次校正 49。最後一組涵蓋八字註冊／選填確認、自我停用／刪除／降權、失去權限後拒絕操作、固定 root 最後管理權、DB 外鍵／CHECK／唯一索引及空值發布。
- 桌面實際檢查 1745 CSS px、手機 355 CSS px；表單僅景點名稱有使用者必填標記，slug／地址／行政區／文案皆可留空，七天未知狀態正常。橫向內容寬分別 1732／341，未觀察到頁面溢出；viewport 已還原。一般管理員編輯欄 readOnly=false、pattern／minlength 均未另加限制。該次註冊確認欄為 required=false，現已依 U-15 改為 required=true；Unicode 密碼長度由共用 JS 檢查，不以 UTF-16 原生 minlength 推導政策。
- security-audit 審查範圍為本次帳號修改、真實權限、自我操作、root／DB 保護、session 撤銷、上傳與手填網址；前端依 web-design-guidelines 核對標籤、可選／必填一致性及既有焦點／錯誤流程。不宣稱外部弱點 scanner、獨立病毒掃描、正式 TLS／SMTP、全站逐像素、負載或完整 P0 驗收通過。

本機完整結果放忽略的 artifacts/spec-alignment-final-*.log、specification-alignment-results.json、specification-schema-inventory.json、specification-main-migration-verification.json。資料庫備份及 UI 驗收憑證不提交 Git。舊回溯 33 項觀察不灌入先前 355 項，也不將舊「被拒絕」結果當作新規格通過。

歷史整理另核對七個相依變更快照：PHPStan level 10 均通過，各版本已有的 PHPUnit、前端建置／Node 及 Router／OpenAPI 檢查亦通過。曾發現密碼測試與登入導向修正的提交順序不一致，已歸回同一功能並重跑；最終應用程式內容與完整驗收版本一致。這些快照檢查不重複計入355項HTTP／MariaDB驗證。

## U-15：確認密碼必填更正（2026-10-18）

使用者明確更正確認密碼應為必填；取代 U-13 的確認選填部分。註冊、會員／管理員重設與更改密碼均要求非空且與新密碼逐字一致；八字政策、Unicode 與空白處理、取消首登及已核准的一般管理員自我操作均不變。CLI 既有確認提示同樣必填。管理員新增只有初始密碼欄，不擅自增加表單欄位；確認值不保存到 DB，無新 migration／環境變數／端點。

Input.password 僅負責共用長度邊界；RegisterInput／PasswordInput 透過 Input.confirmedPassword 驗證必填及相等。前端五頁皆有 required，passwordConfirmationError 在共用 submitForm 中提供欄位錯誤與焦點；仍由 API 再次驗證，不能移除前端 required 來繞過。

本次實測：PHPStan level10／PHPat、PHPUnit37項182斷言、Node67項、64頁建置、PER-CS123檔零差異通過；Rector僅限定四檔dry-run，提出一份既有console風格建議，未套用。真實隔離MariaDB／HTTP的認證套件108項及校正套件50項共158項通過，涵蓋五種流程的省略／空字串／錯誤型別／不一致共20個拒絕案例，並確認仍可使用原驗證碼、重設token或目前密碼完成後續正確操作。CLI 空白／不一致失敗，符合的八字密碼成功讀取既有隔離root。五個實際HTML的確認欄required皆已核對；瀏覽器註冊頁顯示「確認密碼」、required=true、留空valueMissing=true。security-audit確認只在通過DTO後才進行密碼或token交易，沒有新增敏感日誌；UI標籤、autocomplete與既有錯誤／焦點處理依web-design-guidelines核對。

本次僅重跑受密碼確認影響的兩組HTTP套件；未將先前355項或七個歷史快照混算成本次結果。原始website／Excel、正式管理員密碼與.env皆未修改。詳見忽略的artifacts/confirmation-*.log及confirmation-cli.json。

2026-10-19 後續再次檢查：本文件的升級結果保留當時紀錄；目前另有 008 migration，累計九份。7 項修正、獨立契約驗證與完整結果見 [再次檢查報告](FULL-RECHECK-20261019.md)。
