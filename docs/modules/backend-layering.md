# PHP 分層閱讀指南

2026-10-17。U-02：使用者要求易讀、可重用的 API 架構，並指定可參考 Spring Boot。類別拆法、回應 DTO 與例外轉譯屬 E-01 工程設計；不是 Excel 新增的業務要求。

## 對照 Spring Boot 閱讀

| 常見角色 | 本案實作 | 職責 |
| --- | --- | --- |
| RestController | AdminController、PermissionController、AuthController | HTTP 輸入、呼叫服務、DTO → JSON；不查 SQL |
| Service | AdminManagement、PermissionManagement、RoleTemplates、Authentication | 使用案例、授權與交易；不依賴 Request、Cookie 或 PDO 驅動 |
| Repository | AccountRepository、PermissionRepository 等 | SQL、資料映射與已知儲存衝突轉譯；不自行提交交易 |
| Domain Model／Policy | Account、IdentityKind、PermissionPolicy | 帳號資料與純業務規則；含內部雜湊的 Account 不直接序列化 |
| Request／Response DTO | DTO/ 下的具名類別 | 輸入與輸出分開；安全摘要不持有密碼雜湊或完整 Account |
| 建構子 DI／設定 | Composition | 集中建立並傳入依賴；同請求共用連線，業務不查全域容器 |
| Filter／統一錯誤處理 | Http/Kernel | 身分、CSRF、唯讀模擬、module/action 與 HTTP 錯誤碼 |

建構子注入參考 [Spring Boot 官方 DI 建議](https://docs.spring.io/spring-boot/reference/using/spring-beans-and-dependency-injection.html)；儲存例外轉譯參考 [Spring Framework DAO](https://docs.spring.io/spring-framework/reference/data-access/dao.html)。PHP 採明確組裝與 PDO，沒有 Spring 的註解掃描、JPA 或 AOP；角色職責相近，執行機制不同。

## 三個服務如何合作

```mermaid
flowchart TD
    AC[AdminController] --> AM[AdminManagement：帳號 CRUD]
    PC[PermissionController] --> PM[PermissionManagement：個人矩陣]
    PC --> RT[RoleTemplates：範本查閱]
    AM --> RT
    AM --> R[Repositories]
    PM --> R
    RT --> PR[PermissionRepository]
    R --> DB[(MariaDB)]
    PR --> DB
```

- `AdminManagement` 負責新增／編輯／停用／刪除。換角色時向 `RoleTemplates.forAssignment` 取得合法起始值；帳號、個人權限與 audit 仍同交易保存。
- `PermissionManagement` 負責 catalog、讀取與保存個人矩陣，保留完整 40 格校驗、版本、鎖定、root 保護、防越級及本人保留管理權。
- `RoleTemplates` 負責查閱範本的授權、排除 root 範本及驗證可指派角色。不提交交易，也不把角色範本當成個人即時權限。

`Authorization`／`PermissionPolicy` 仍是共用規則。Kernel 檢查 HTTP 請求後，Service 在交易內重新讀取並鎖定操作者，防止請求途中停權或權限異動被忽略。沒有增加萬用 BaseService 或每類別一個空 interface。

## 實際資料流與回應

新增管理員：`POST /api/v1/admin/admins` → Kernel → `AdminController.create` → `AdminWriteInput` → `AdminManagement.create` → RoleTemplates／Policy → 同交易的帳號、權限、audit → `AccountSummary` → Controller `toArray()` → HTTP 201。

保存權限：`PUT /api/v1/admin/admins/{id}/permissions` → Kernel → `PermissionController.saveMatrix` → `PermissionMatrixInput` → `PermissionManagement.saveMatrix` → transaction／Authorization／Policy → PermissionRepository／EventRepository → HTTP 204。

| 輸出 DTO | 資料內容 |
| --- | --- |
| AccountSummary | 帳號欄位白名單；沒有 passwordHash、deletedAt 或內部 Account 參照 |
| AdminPage | 安全摘要列表、total、page、pageSize |
| RoleTemplate | id、name 與起始權限 |
| PermissionCatalog／PermissionMatrix | 最小選單摘要、矩陣欄位或對象／版本／權限 |
| IdentityResponse／PreviewStatus | member／admin、有效權限、可選的真正操作者摘要 |
| CaptchaResponse | challengeToken、期限與圖片或寄送提示；沒有答案 |

DTO 陣列集合有明確 PHPDoc 欄位型別，不為每格 module/action 額外建立類別。Controller 呼叫 `toArray()`；Cookie、status、CSRF、requestId 仍在 HTTP 邊界組合。API 路徑、JSON 欄位、可選欄位與 204 空內容保持相容。

## 重複資料與失敗

`AccountRepository.writeAccount` 只將 SQLSTATE 23000 **且 MariaDB code 1062** 轉成 `AccountConflict`。PdoTransactions 先回滾，Service 再於交易外重新查帳號／Email，轉成原有 409 欄位錯誤。外鍵、CHECK、連線及其他寫入失敗不冒充帳號衝突；Kernel 維持安全 500，不回 SQL 或內部訊息。死鎖重試仍由 PDO adapter 處理，SMTP 不放進可重試交易。

## 如何驗證

PHP 工具在 Docker 執行：

```powershell
docker compose exec -T app php /var/www/tools/vendor/bin/phpstan analyse --configuration=phpstan.neon --no-progress
docker compose exec -T app php /var/www/tools/vendor/bin/phpunit --configuration=phpunit.xml
docker compose exec -T app php /var/www/tools/vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --dry-run --diff
docker compose exec -T app php /var/www/tools/vendor/bin/rector process --config=rector.php --dry-run
docker compose exec -T app-test php tests/account_persistence.php
python backend/tests/api_integration.py
python backend/tests/admin_preview.py
python backend/tests/response_contract.py
```

整合測試需 test profile、yinghua_test 與 Mailpit；不能對 yinghua 執行。account_persistence.php 同樣檢查 APP_ENV／資料庫名稱，測試寫入最後回滾。HTTP 測試用隨機隔離帳號，憑證只留在被忽略的 artifacts，沒有 HTTP 測試後門。

PHPat 五條規則：Controller 不依賴 Repository／PDO；Repository 不依賴 Service／Controller／HTTP；Service 不依賴 HTTP／Controller；Service 不依賴 PDO／PDOException／PDOStatement；Model／DTO／Policy 不依賴 Repository／HTTP／PDO。相依檢查不能代替業務與越權測試。

實際結果見 [階段二驗收](../PHASE-2-ACCEPTANCE.md)。本次整理沒有修改 frontend/、website/、schema 或後續階段的業務功能。

E-01／E-13：Request.json() 不得丟棄 PHP 將 JSON 數字屬性名轉成的整數鍵；這類頂層鍵直接回 422，普通字串鍵繼續由 DTO 白名單驗證，巢狀 JSON 陣列不受影響。requestId 的契約固定為 32 位十六進位，JSON 與 X-Request-Id 一致。
