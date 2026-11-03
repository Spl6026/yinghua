# P0 複驗差項修正：2026-10-25

依使用者「那請你改善」處理 [前輪 R01–R04](P0-REVERIFICATION-20261025.md)。R01–R03 已修正並完成列明驗收；R04 已修正可重現的網址映射順序誤判，補足 API／HTML gate 診斷，並更新執行中的發布 worker。**前輪那筆未保存完整回應的 503，仍不追認確切根因；完整 AA／正式環境／映像安全差項未關閉，P0 尚未宣告通過。**

沿用 `phase/08-p0-validation`，以 `3883607` 為本輪起點，四項各作功能提交，不改原始交付或 main／遠端歷史。沒有新增 API、資料表、migration、Docker 服務或業務必填；仍為 72 API／67 固定頁／33 表 316 欄／18 migration。原始 `website/` 完整保留。

## 修正與實際結果

| 差項／來源 | 修改與責任 | 驗證結果 |
| --- | --- | --- |
| R01：功能規格 G34／串接總覽 B15 | phase2.css 僅提高 `.section-kicker`、`.film-meta`、`.demo-note` 色彩至 `#62665c`，不改原型字級／間距。 | 真實背景下對比由 3.35／3.03／2.93 提升至 5.21／4.76／4.76；三者達一般文字 4.5。這不是全站 AA 證明。 |
| R02：串接總覽 B7／功能規格 E6、L6 | cinema 模板的館舍區塊由 publish.mjs 使用同一快照館舍及既有 openingHoursLines 產生；下架顯示空態。 | 真產頁測更新地址、結構週表、舊式 hours、下架與編碼；Brave 桌機／390px 顯示與 museum 相同，連結可達，無頁面橫向溢出。 |
| R03：功能規格 E30／E32／L32 | seo-head.mjs 輸出目前主題的公開設定 context；list-seo.js 和初載共用 resolveSeo，控制器處理分類／分頁／popstate。消息主題文字由 server／browser 共用函式。 | 自訂 title／description／HTTPS canonical 在切分類、返回及重新整理均保留；無覆寫的活動頁使用自訂品牌。Node 另測第二頁與新 generation 轉讀發布頁，無 JS head 74 HTTP 零差異。 |
| R04：串接總覽 B7／B13、功能規格 E11／L11 | NewsRepository／OfferRepository 將 alias 映射按鍵排序，與既有景點／電影一致；PublicationPending 提供安全內部診斷，Kernel／HTML gate 加 requestId。 | 修前兩個受控順序測試均觸發 PUBLICATION_PENDING，修後兩項通過；真 MariaDB 反向查詢及真實下架拒舊 7 項通過；API／gate 診斷 8 項通過，優惠套件 40 項／8 契約通過。 |

數值、診斷欄位與重載方式均是 E-29 工程設定，沒有將 Excel 建議當成新硬性業務要求。

## R04：已證實與未證實的界線

PHP 以 JSON 的位元組計算 generation。消息／優惠 alias SQL 沒有保證回傳順序；相同 path→slug 映射若插入 PHP array 的順序不同，原先會產生不同 hash。列表排序是業務順序，仍保留；只對無順序語意的 alias map 排序，不新增索引或變更全域 DB 設定。

[PublicationOrderTest.php](../../backend/tests/Unit/PublicationOrderTest.php) 透過真 Repository、可控資料庫回傳順序與發布服務，修前重現兩項失敗。[publication_alias_order.php](../../backend/tests/publication_alias_order.php) 在 `yinghua_test` 交易內建立自有消息／優惠，刻意以 SQL ASC／DESC 查詢，確認原始列順序確實不同而 generation 不變；實際下架則必須拒絕，重發布後才恢復。這是在測 SQL 無順序保證的容許結果，不聲稱當前 optimizer 恰好隨機切換過計畫。資料全部 rollback、Node 產物在私有 `/tmp`，不切換網站 current。

本輪更新期間主站還實際出現一次 503，新日誌明確指出 `sections=newsAliases,offerAliases`：API 讀到新排序程式，長駐 PHP worker 仍執行更新前已載入的 Repository。CLI 手動發布後又被舊 worker 改回另一 hash；重啟 publisher 並發布後，首頁、home、offers 都恢復 200，之後多個 worker 週期亦一致。部署／開發指引已補「更新 PHP 發布程式後重載 worker」，沒有放寬 gate 或供應舊內容。

前輪 2026-10-24T11:22:00Z 那筆 requestId 的 body／當時 generation 沒保存；本輪修前優惠整合也一次通過。因此不能把這次重現原因直接當成前輪同一根因，歷史不確定性保留。

## 程式閱讀入口

| 檔案 | 用途 |
| --- | --- |
| [phase2.css](../../frontend/assets/css/phase2.css) | 共用三類小字對比補充；原始 style.css 不改。 |
| [cinema.html](../../frontend/pages/cinema.html)、[publish.mjs](../../frontend/scripts/publish.mjs) | 標記館舍位置；發布時使用同一資料與週表，處理下架空態。 |
| [list-seo.js](../../frontend/assets/js/core/list-seo.js) | 讀公開 context、呼叫 resolver、以 DOM text／attribute 更新 head，不插入未編碼 HTML。 |
| [seo-head.mjs](../../frontend/scripts/seo-head.mjs) | 初載 head 唯一產生者，另附目前主題的安全 JSON context，排除其他覆寫。 |
| [public-list-controller.js](../../frontend/assets/js/core/public-list-controller.js) | 非同步列表、競態、歷史與焦點；新 generation／舊頁缺 context 時轉讀新發布頁。 |
| [news-view.js](../../frontend/assets/js/core/news-view.js)、[public-news.mjs](../../frontend/scripts/public-news.mjs) | 共用消息列表標題／摘要規則；news.js／offers.js 組裝各自列表。 |
| [PublicationPending.php](../../backend/src/Exceptions/PublicationPending.php) | 分類缺快照／投影變更，僅保存固定區塊名稱及格式已驗 hash；對外仍固定 PUBLICATION_PENDING／Retry-After 5。 |
| [ContentPublication.php](../../backend/src/Services/ContentPublication.php)、[Kernel.php](../../backend/src/Http/Kernel.php)、[PublicAttractionController.php](../../backend/src/Controllers/PublicAttractionController.php) | 服務比對一次投影；API 與 HTML gate 各在邊界記安全診斷及 requestId，原拒絕狀態保留。 |
| [NewsRepository.php](../../backend/src/Repositories/NewsRepository.php)、[OfferRepository.php](../../backend/src/Repositories/OfferRepository.php) | SQL／映射仍留 Repository，alias map 排序避免誤判，不在 Controller 加 SQL。 |

相關完整閱讀指南：[無障礙](../modules/frontend-accessibility.md)、[電影](../modules/films.md)、[SEO](../modules/seo-settings.md)、[發布與運維](../modules/operations.md)。

## 本輪驗證與限制

| 檢查 | 實際結果 |
| --- | --- |
| Docker PHP | PHPUnit 91 項／318 斷言、PHPStan 10／PHPat 通過；格式 dry-run 261 檔零差異。 |
| Docker Node | 全 150 項通過，含館舍產頁及四個列表 SEO 回歸；67 頁 build 成功。 |
| 受影響真 DB／HTTP | 優惠 40／8 契約、消息 40／12 契約、SEO 設定 74／32 契約、SEO 發布 60／12 契約、發布失敗 18 項；無 JS head 74、建置完整性 67 頁／14 資產／81 HTTP 零差異。 |
| 新發布回歸 | Alias 單元修前 2 失敗→修後 2 通過；真 DB 7 項、API／gate 診斷 8 項；後者驗 requestId 對應、固定欄位、內容不進日誌及清理後恢復。 |
| Rector | dry-run 40 檔既有語法提案，工具 exit 2／標示 40 files would have been changed；已閱，沒有套用或當成零提案。 |
| 瀏覽器 | Brave 桌機／390×844；三類對比、館舍同源／連結、SEO 自訂覆寫／品牌／分類／返回／重整；測試設定與合成帳號已清理／撤銷，正常 audit 保留。 |
| 安全審查 | 公開 JSON 僅取已發布設定，編碼 script 結束字元；DOM 屬性更新不走 innerHTML；診斷不含內容、任意 key、Cookie、token、URL 或秘密；保留拒舊與公開邊界。對外 API 契約未新增診斷。 |

開發中曾遇到新測試 fixture 漏填必要欄位、反向查詢測試的比對字串與可見 HTML／JSON 範圍不符，已在首次提交前修正；不把這些測試程式錯誤算網站缺陷，也不把它們說成第一次全過。原失敗與受控修前結果保存在私有證據中。

沒有重跑整套前輪部署／還原／scanner；本輪沒有變更正式 Compose、映像基底、套件或 schema，因此不能藉此次 UI／發布修正關閉上游 CVE。正式 SMTP／可信 TLS／主機與備份政策、完整人工讀屏／200% 文字／400% 縮放仍待驗，P0 保持未合併。

## 操作與證據

```text
docker compose run --rm --no-deps frontend npm test
docker compose run --rm --no-deps frontend
docker compose exec -T app-test php tests/publication_alias_order.php
python backend/tests/publication_diagnostics.py
docker compose restart publisher
docker compose exec -T -u www-data app php bin/console.php content:publish
```

其他真 HTTP 腳本沿用 `--dependency-path artifacts/audit-jsonschema312` 或正確 PYTHONPATH，不能把缺套件當通過。主／測試發布 volume 分開；測試站無常駐 publisher，測試工具最後受控發布。

本輪忽略的 `artifacts/p0-improvements-20261025/` 保存每批 JSON／log、受控修前失敗、主站更新前後 generation 診斷、`contrast-ratios.json`、`seo-browser-after.json`、`brand-browser-after.json`、`cinema-museum-desktop.jpg`／`cinema-museum-mobile.jpg` 及交付核對。Git bundle 備份已驗證，原始 69 檔／18 migration、真 schema、main／origin、秘密忽略與合成資料清理另做最終檢查；不提交私有備份／fixture／.env／掃描內容。

收尾實測：1,118 個本地文件連結零新增缺失（既知防毒隔離參考檔除外）；主／測試 health、首頁、home、offers 均 200，gate 均 204，各站 home／offers generation 一致。SEO 設定已與測試前快照逐欄一致，合成內容已移除、帳號 session 撤銷；主 DB 容器 ID／具名卷與前輪一致。只有既有防毒隔離檔案刪除差異未納入提交。資料清理不刪正常 audit，也不改 main／origin。
