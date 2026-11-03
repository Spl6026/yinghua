# 一般網站設定與政策閱讀指南

2026-11-01，階段14.2。S：Excel功能規格E28:L28、G16。原型基本資訊、聯絡信箱與政策選項；E-36將示範政策選項落實為純文字／版本編輯。版本標籤與文案不是新增業務必填，空白保留原有開發說明；正式文字仍由業主提供。

GET/PUT /api/v1/admin/settings分別要求settings.view/edit。DTO只接受version、seoVersion、siteName、description、values；values只接受contactEmail、termsVersion、privacyVersion、termsText、privacyText。PUT是完整替換，省略values內欄位等同清空；UI總是送完整文件。信箱非空須有效；網站名稱160、摘要500、政策20000字與版本80字是E資源邊界，HTTP仍受既有64KiB限制。未知key拒絕，不支援任意環境／秘密設定。

| 檔案 | 用途 |
| --- | --- |
| backend/src/DTO/GeneralSettings.php | 白名單、Email格式、純文字及政策內容摘要token；聯絡信箱變更不使同意失效 |
| backend/src/DTO/GeneralSettingsDocument.php／GeneralSettingsInput.php | 儲存文件與管理更新邊界，技術版本不等於要求填滿業務欄位 |
| backend/src/Repositories/GeneralSettingsRepository.php | 固定general key，沿用site_settings JSON/version/published/updated_by，不接受外部key |
| backend/src/Services/GeneralSiteSettings.php | 發布鎖→交易→重新授權→general/seo雙版本→保存→安全稽核→同版發布；409不盲覆寫 |
| backend/src/Controllers/GeneralSettingsController.php | JSON輸入／回應與Service連接 |
| backend/src/Services/PublishedPolicies.php | CSRF metadata讀已發布token；註冊檢查DB與快照一致，再於交易內鎖定重驗 |
| backend/src/Services/Authentication.php／Controllers/AuthController.php | 依注入服務驗證註冊同意；舊測試建構介面相容 |
| backend/src/Services/ContentPublication.php／PublicSiteSettings.php／DTO/PublicSiteSettingsDocument.php | 同generation公開general設定與政策，公開API不含管理metadata |
| frontend/assets/js/pages/general-settings.js／pages/admin/settings.html | 基本資訊／政策表單、唯讀／模擬、雙版本、錯誤焦點、未儲存提醒及重新載入 |
| frontend/assets/js/pages/seo-settings.js | 在整合頁隱藏重複基本資訊；保留原SEO管理與內容覆寫，雙區各自儲存、防止舊版本覆寫 |
| frontend/scripts/policy-pages.mjs／publish.mjs | 政策純文字轉義及換行、版本、網站識別／聯絡信箱SSR；不執行管理員文案HTML |
| frontend/pages/terms.html／privacy.html | 開發說明fallback及政策內容標記 |
| frontend/templates/site-footer.html | 政策導覽、網站版權與聯絡插入位置 |
| frontend/assets/js/core/site-information.js／session-monitor.js | 靜態認證／會員頁讀同版公開識別與聯絡資訊；不覆寫各頁head |
| backend/tests/Unit/GeneralSettingsTest.php | 文案／隱私改變使同意摘要變更、空白／未知key |
| backend/tests/phase14_policy_integration.py | 真註冊、過期同意、發布pending與兩份同意保存 |
| frontend/tests/general-settings-page.test.mjs／policy-pages.test.mjs | 儲存鎖定、唯讀、409保留、重新讀取及轉義 |

網站名稱／描述的唯一來源仍是seo文件；一般儲存只更新這兩欄，保留其他SEO欄與所有內容覆寫。兩個表單任一保存後，另一區編輯前需重新讀取，409防止舊值倒寫。general與seo同交易提交，發布失敗回publicationReady=false並由既有publisher重試，不重複新增。

條款／隱私標籤或文字變更後，既有termsVersion API欄位使用71字元policy-SHA256摘要，並由member_consents分別記錄terms及privacy；不改舊同意、不要求既有會員重新登入。沒有管理政策時沿用TERMS_VERSION。僅聯絡信箱變更不使政策同意失效。註冊於交易前與行內重驗發布版本，避免設定變更與註冊競爭。登入與取得CSRF不因發布pending被阻斷。

資料表沿用site_settings與member_consents、audit_logs；無DDL、依賴或env新增。安全審查與實際結果見[驗收](../archive/PHASE-14-ACCEPTANCE.md)。
