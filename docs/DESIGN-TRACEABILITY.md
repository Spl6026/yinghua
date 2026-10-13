# 設計來源：規格、使用者指定與工程補充

整理日期：2026-10-13。此文件標註設計來源，不代表功能已完成。總體架構見 [ARCHITECTURE.md](ARCHITECTURE.md)；完整 30 項功能見 [REQUIREMENTS-MAP.md](REQUIREMENTS-MAP.md)；階段二端點、欄位和頁面清單見 [PHASE-2-PLAN.md](PHASE-2-PLAN.md)。

原始 Excel「串接總覽」A2 明示「建議」是設計提案；「資料表建議」A2 也說明是估工／建模草案。因此「要求某種功能」和「要求某個表名／端點／類別」分開標記。以下引用原始工作表儲存格，沒有修改規格。

## 1. 標記方式

| 標記 | 含義 | 如何處理 |
| --- | --- | --- |
| S：規格要求 | 功能規格／安全底線／驗收明確要求的行為 | 納入必要驗收；附工作表與儲存格 |
| D：文件草案 | API草案／資料表建議提供的設計 | 保留對照，可因架構調整名稱與結構；必要功能仍依 S 驗收 |
| U：使用者指定 | 本次對話明確指定的技術或交付原則 | 設計依此執行，不冒稱 Excel 指定 |
| E：工程補充 | 我為完成、維護或改善功能提出的做法 | 標出理由與狀態；不把額外體驗／政策當成客戶原始要求 |

同一項可以同時有 S 和 E：例如「要限速」是 S，「用 MariaDB 限速桶」是 E。後續文件及業務註解使用這些 ID，來源有變更就更新此表。

## 2. 規格與使用者要求索引

| ID | 行為／決定 | 來源 |
| --- | --- | --- |
| S-SEC-09 | 安全密碼雜湊、TLS、Cookie、CSRF、登入限速／失敗鎖定、密碼重設 | 串接總覽 B9 |
| S-SEC-10 | 伺服器驗證碼，短效、一次性、限次、綁定註冊與速率限制 | 串接總覽 B10 |
| S-SEC-14 | 每個 API 驗帳號與 module/action，後端保護固定 admin | 串接總覽 B14 |
| S-016 | Email／名稱／密碼註冊、圖形驗證碼、同意條款、欄位錯誤 | 功能規格 E16–L16 |
| S-017 | 真實登入／登出、session rotation、限速／鎖定，不洩漏帳號存在與密碼 | 功能規格 E17–L17 |
| S-026 | 管理員 CRUD、root 永遠鎖定、刪除確認且無刪除專頁、保留管理權 | 功能規格 E26–L26 |
| S-027 | 逐人 module/action 矩陣、角色可複製起始值、稽核、刷新仍保存 | 功能規格 E27–L27 |
| S-PAGE | 獨立 HTML 頁面、後台登入檢查／權限不足狀態 | 頁面清單 G5–G44、E23–E44 |
| S-SEO-17 | 正確 SEO head，不執行 JS 可讀；直接網址可檢索、快取更新 | SEO規格 C17–F17；功能規格 E29–L33 |
| U-01 | PHP＋MariaDB＋Docker；原生 JS，可用 jQuery | 使用者對話「希望 php+mariadb 並且用 docker 開」 |
| U-02 | PHP 的網站責任全為 API，明確的 MVC 類似分工、重用、易讀與適當 design pattern | 使用者本次架構要求 |
| U-03 | website 原始交付保留，新文件在 project；其他技術使用者名稱 spl，DB yinghua | 使用者既有對話；固定網站 admin 仍依 S-026 |
| U-04 | 先安裝環境，暫不改應用程式；Git 原始提交和修正合併原則 | 使用者既有對話、根目錄 AGENTS.md |

## 3. 我額外提出的工程決定

「基礎方案」指計畫採用的實作方式；「額外方案」指超出規格明確功能的政策或入口，仍是可調整提案。兩者目前都不是已完成業務程式。

| ID | 工程補充 | 理由／規格關係 | 計畫狀態 |
| --- | --- | --- | --- |
| E-01 | Controller／Service／Repository、DTO、建構子 DI、Adapter／Middleware | 落實 U-02；Excel 沒有指定這些 pattern | 基礎方案，見 ARCHITECTURE |
| E-02 | 會員／後台獨立 auth namespace、Cookie 與 CSRF API | 實現 S-SEC-09／14 的身分邊界，確切端點是提案 | 基礎方案 |
| E-03 | password_reset_tokens 表、兩種重設用途、獨立重設頁 | 重設行為是 S-SEC-09；儲存及頁面拆法是提案 | 基礎方案 |
| E-04 | MariaDB auth_rate_limits 表、keyed hash、原子更新 | 限速／鎖定是規格；使用共享 DB 桶是提案 | 基礎方案 |
| E-05 | member_consents 表保存條款版本／時間 | S-016 要同意條款；API草案 G17 提到版本，獨立表是提案 | 基礎方案 |
| E-06 | Email 驗證碼作為圖形方式的無障礙替代入口 | API草案 F16 提到替代方式；指定 Email 是我新增的選擇，增加端點及郵件成本 | 額外方案，可改為其他可及方式 |
| E-07 | 一般管理員首次登入強制改密碼、本人改密碼頁／API | 降低管理者設定初始密碼的風險，規格未指定首登強制 | 額外方案 |
| E-08 | 15–128 字元密碼、挑戰 5 分鐘／3 次、session／token 期限 | 規格要求政策／期限，具體數值不是原文 | 政策初值，實作與驗收前寫清最終值 |
| E-09 | 管理員軟刪除、禁止自我停用／刪除、版本檢查 | 保留稽核及避免鎖死／覆蓋；細節超出 S-026 的最低敘述 | 基礎方案 |
| E-10 | 完整個人權限快照；非 root 禁授自己沒有的權限 | 起始矩陣符合 S-027；防越級在 G27 是可選策略，我選擇啟用 | 基礎方案，須顯示本案選擇 |
| E-11 | PHPMailer、Mailpit、品質工具／PHPat、admin:init CLI | 完成寄信、初始化與可維護性；套件與命令非 Excel 指定 | 工具已安裝；業務／架構規則待實作 |
| E-12 | frontend/ 與 Node 前端建置／公開內容預先產生、版本同步 | 同時滿足 U-02 API-only 和 S-SEO-17；工具不是文件指定 | 基礎方案，階段二／三分別建立 |
| E-13 | 角色／權限定義輔助讀取 API、統一 JSON 錯誤、requestId | 供原型表單與矩陣運作且便於排查；路徑／錯誤結構是提案 | 基礎方案 |
| E-14 | 中文業務理由、來源註記、模組閱讀指南及相依檢查 | 落實 U-02「需要看得懂 code」，格式與檢查是提案 | 基礎方案 |

## 4. 階段二 API 的來源

以下與 PHASE-2-PLAN 的 26 個計畫端點一一對應，目前全部尚未實作。API草案中的方法／路徑屬 D，背後功能的 S 與本案新增 E 分開列出。確切契約仍需在實作時更新 OpenAPI。

| 方法與路徑 | 要求來源 | 端點設計來源／補充 |
| --- | --- | --- |
| GET /api/v1/auth/csrf | S-SEC-09 | E-02，獨立取 token API 是工程設計 |
| GET /api/v1/auth/captcha | S-016／S-SEC-10 | D：API草案 B16–G16；圖片 data URL 是工程選擇 |
| POST /api/v1/auth/captcha/email | S-016 的註冊；不是規格必需的 Email 入口 | E-06；D：API草案 F16 只建議替代方式 |
| POST /api/v1/auth/register | S-016 | D：API草案 B17–G17 |
| POST /api/v1/auth/login | S-017 | D：API草案 B18–G18 |
| POST /api/v1/auth/logout | S-017 | D：API草案 B19–G19 |
| GET /api/v1/auth/me | S-017 | D：API草案 B20–G20 |
| POST /api/v1/auth/forgot-password | S-SEC-09 | E-03，草案未逐項列重設 API |
| POST /api/v1/auth/reset-password | S-SEC-09 | E-03 |
| POST /api/v1/auth/change-password | 沒有獨立會員改密碼功能要求 | E-07 |
| GET /api/v1/admin/auth/csrf | S-SEC-09 | E-02 |
| POST /api/v1/admin/auth/login | S-SEC-09／14；S-PAGE | E-02，草案未列獨立後台認證路徑 |
| POST /api/v1/admin/auth/logout | S-SEC-09／14 | E-02 |
| GET /api/v1/admin/auth/me | S-SEC-14；S-PAGE | E-02 |
| POST /api/v1/admin/auth/forgot-password | S-SEC-09 | E-03 |
| POST /api/v1/admin/auth/reset-password | S-SEC-09 | E-03 |
| POST /api/v1/admin/auth/change-password | 首登強制／本人改密碼是補充 | E-07 |
| GET /api/v1/admin/admins | S-026 | D：API草案 B35–G35 |
| GET /api/v1/admin/admins/{id} | S-026 的編輯頁需要載回資料 | E-13，CRUD 草案沒有逐列展開單筆 GET |
| POST /api/v1/admin/admins | S-026 | D：API草案 B35–G35 |
| PUT /api/v1/admin/admins/{id} | S-026 | D：API草案 B35–G35；E-09 的版本檢查 |
| DELETE /api/v1/admin/admins/{id} | S-026 | D：API草案 B35–G35；E-09 的軟刪除 |
| GET /api/v1/admin/roles | S-027 的起始角色矩陣 | E-13，輔助端點 |
| GET /api/v1/admin/permissions/catalog | S-027 的矩陣定義／選擇對象 | E-13，輔助端點 |
| GET /api/v1/admin/admins/{id}/permissions | S-027 | D：API草案 B36–G36 |
| PUT /api/v1/admin/admins/{id}/permissions | S-027／S-SEC-14 | D：API草案 B36–G36；E-10 的防越級選擇 |

目前沒有 health API；Docker 環境健康檢查不算網站 API 或業務功能。

## 5. 資料表的來源

以下 13 張業務表全部是計畫新建，目前資料庫沒有業務表。migration 中繼表 schema_migrations 屬工具補充，不算業務表。確切欄位、索引與表名都是實作設計，不以此表冒稱原始規格已指定 DDL。

| 資料表 | 狀態 | 文件是否提出名稱／概念 | 工程補充 |
| --- | --- | --- | --- |
| members | 計畫新增 | 資料表建議 A5–E5；S-016／17 | 索引、constraint 等正式 DDL |
| admin_users | 計畫新增 | 資料表建議 A6–E6；功能規格 I26 使用 admins | 採 admin_users，與功能名稱對照 |
| roles | 計畫新增 | 資料表建議 A7–E7 | root／系統角色保護具體約束 |
| permissions | 計畫新增 | 資料表建議 A8–E8 | module/action 的正式字典 |
| role_permissions | 計畫新增 | 文件有角色範本概念，未列此表名 | E-10：分開保存角色預設矩陣 |
| admin_role_permissions | 計畫新增 | 資料表建議 A9–E9 | E-10：完整個人快照、更新版本 |
| sessions | 計畫新增 | 資料表建議 A22–E22；S-017 | E-02：身分分離、閒置與撤銷細節 |
| audit_logs | 計畫新增 | 資料表建議 A26–E26；S-026／27 | 安全欄位、原子交易與保留政策 |
| captcha_challenges | 計畫新增 | 資料表建議 A23–E23、功能規格 I16 | E-06：Email mode；HMAC 等低熵保護實作 |
| password_reset_tokens | 計畫新增 | S-SEC-09 要重設，但沒有獨立列此表名 | E-03：雜湊、用途、期限、一次性儲存 |
| auth_events | 計畫新增 | 功能規格 I17 點名此表；資料表建議未列 | 認證事件欄位與隱私摘要設計 |
| auth_rate_limits | 計畫新增 | S-SEC-09／10 要限速，未列此表名 | E-04：共享限速桶 |
| member_consents | 計畫新增 | 功能規格 G16 要同意；API草案 G17 提版本，未列此表名 | E-05：保存同意證據的獨立表 |

## 6. 階段二畫面的來源

| 頁面／操作 | 是否原型已有 | 來源 |
| --- | --- | --- |
| /register.html | 有 | S-016；Email 替代入口 E-06 |
| /login.html | 有 | S-017 |
| /admin/admins.html | 有 | S-026 |
| /admin/admin-add.html | 有 | S-026 |
| /admin/admin-edit.html | 有 | S-026 |
| /admin/permissions.html | 有 | S-027 |
| /forgot-password.html | 無 | S-SEC-09 要重設；獨立頁面 E-03 |
| /reset-password.html | 無 | S-SEC-09 要重設；獨立頁面 E-03 |
| /change-password.html | 無 | E-07，本人改密碼補充 |
| /admin/login.html | 無 | S-PAGE 要管理員登入；獨立頁面 E-02 |
| /admin/forgot-password.html | 無 | S-SEC-09；後台獨立頁面 E-03 |
| /admin/reset-password.html | 無 | S-SEC-09；後台獨立頁面 E-03 |
| /admin/change-password.html | 無 | E-07，包含首登強制改密碼 |
| 其餘 18 個後台頁面的登入／授權 guard | 有 | S-SEC-14／S-PAGE；空殼＋API 授權實作 E-12 |
| 共用登入身分與登出 | 導覽原型有 | S-017／S-PAGE；移除模擬身分與共用模組為 E-01／02 |

所有新來源放 frontend/，與 website 原始檔對照。本文件列出提案讓範圍可讀；目前未開始改程式，不把工程補充說成已實作或規格硬性要求。
