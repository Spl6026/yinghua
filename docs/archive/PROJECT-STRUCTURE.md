# 專案資料夾與產物管理

更新：2026-11-02。這次是資料夾維護，不是新增功能階段；網站程式、原始69檔、資料庫與Docker volumes保持。資料夾以來源、環境、文件與可重建產物區分。

## 固定來源與文件

| 路徑 | 放什麼／怎麼維護 |
| --- | --- |
| `backend/src/`、`config/`、`public/`、`bin/` | PHP API及CLI來源；分層見[架構](../ARCHITECTURE.md) |
| `backend/database/`、`resources/` | migrations、seed、公開契約及受控展示資料 |
| `backend/tests/` | 單元／真DB／HTTP測試，不混放一次性改碼腳本 |
| `frontend/pages/`、`templates/`、`assets/` | 新前端來源與共用設計；不是原始範本 |
| `frontend/scripts/`、`tests/`、`resources/` | 靜態建置／發布、前端測試與SEO目錄 |
| `website/`、根目錄Excel | 原始交付，不能當清理目標 |
| `docs/`、`docs/modules/`、`docs/evidence/` | 階段計畫／驗收、模組逐檔指南、可提交的非敏感證據 |
| `docker/`、根目錄compose檔 | 開發／正式環境定義；不存資料庫檔 |
| `scripts/operations/`、`scripts/validation/` | 可重用維運與交付驗證程式 |
| `tools/php/`、`tools/accessibility/`、`tools/validation/` | 品質工具設定、隔離無障礙工具及Python驗證依賴清單 |

較早階段文件保留當時結果，不因目前已完成就刪除。最新狀態看[全站導讀](PROJECT-GUIDE-20261103.md)及[Excel逐列核實](../EXCEL-FUNCTIONAL-VERIFICATION-20261102.md)；以此避免根README重複堆疊歷史狀態。

## 本機產物與快取

- `.cache/validation/python/`：依 `tools/validation/requirements.txt` 安裝的本機Python套件。Windows／WSL與Python小版本的原生擴充不同，換環境要重建，不能共用同一份二進位套件；本輪保留的是原有Windows Python 3.13套件，已重新驗證可匯入。
- `.cache/validation/schemaorg-current-https.jsonld`：Schema.org官方字彙快照；驗收時記錄來源及版本。可重新下載，與歷史驗收證據分開。
- `backend/.build/`、各 `__pycache__/`：可重建的分析／測試快取。
- `frontend/dist/`：建置產物，目前由Nginx掛載；服務運作時不要直接刪除。用 `docker compose run --rm --no-deps frontend node scripts/build.mjs` 重建。
- `backend/vendor/`及Docker vendor volumes：Composer依賴；由lock檔管理，這輪保留運作中掛載。
- `.env`：保留目前設定；`.env.example`保留範本。舊隔離演練的 `.env.*` 已歸檔後從根目錄移除。

## 驗收證據與舊產物

`artifacts/`保留最近兩輪驗收目錄、本輪整理清單、後續新執行的`phase15/`、`archive/`及文件直接引用圖片的`evidence-images/`。舊報告、截圖、失敗紀錄、不同版本Git bundle與資料庫／媒體備份集中在本機忽略的`artifacts/archive/legacy-before-20261102.zip`，ZIP內保留原專案相對路徑；ZIP內`archive-manifest.json`列出檔案，外部`archive/index.json`記錄略過的可重建產物與SHA256。

因此較早文件提到的 `artifacts/phase*`、`artifacts/p0-*` 等歷史證據路徑，現在應在該ZIP中按原路徑查找；不是撤銷當時證據。最近兩輪仍保留原目錄供直接查看。歸檔先完整讀回CRC及核對大小，成功後才移除散落副本。

只刪除掃描用的Docker映像匯出TAR、舊安裝程式／重複依賴、分析快取及過期測試輸出；掃描報告、映像digest、Git bundle與真正備份的`media.tar`均保留歸檔。不要把映像掃描輸入TAR與資料備份混為一談。

查舊證據可將ZIP解壓到**新的**`artifacts/recovered/`資料夾，避免覆蓋現有 `.env`、程式及最近證據。歸檔含本機備份／停用環境設定，不可提交Git、放到公開網站或加入Docker build context。

本輪未清理Docker images／containers／volumes，也不刪除其他專案資料。Git整理前保留完整bundle，維護分支驗證後以merge commit合併；不改原始交付提交。

整理結果：約釋放7.01 GiB；45,015個歷史檔案歸檔，根目錄移除27個停用演練環境檔。16張仍由文件直接呈現的圖片集中保留並修正連結。Python 3.13的OpenAPI驗證、維運4項、兩站6個HTTP入口及1,548個文件連結通過；詳見[非敏感整理清單](../evidence/PROJECT-CLEANUP-20261102.json)。掃描TAR、舊快取的移除不會改變既有安全掃描結論。

## 後續新增檔案

功能來源放既有backend／frontend模組；可重用腳本放scripts／tools；一次性診斷、憑證與截圖放按日期命名的artifacts子目錄。不要再於根目錄留下演練 `.env.*`，也不要讓正式測試依賴舊驗收資料夾內的pip安裝目錄。
