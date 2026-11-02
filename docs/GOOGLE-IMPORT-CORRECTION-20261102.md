# Google 簡介匯入更正驗收

日期：2026-11-02。來源 U-24，依使用者最新指定更正先前 Google 顯示補位語意；從 Claude 合併後 main 開始，未改寫既有提交。

- 確認候選帶入時，列表摘要、景點介紹逐欄補空白。已有內容原樣保留；沒有 Google 簡介不填。
- 超過欄位既有容量不截斷原文，留空並提示。只有明確儲存才保存；取消確認不改表單。
- 移除現行後台舊補位預覽與公開產頁的即時 Google 參考框；候選確認前仍可看來源。
- 無新增 API、DDL、套件或環境變數；舊補位 API 保留相容性，不再由現行表單／公開產頁呼叫。既有自家介紹摘錄摘要不在本次更正範圍。

## 驗證

Docker Node：`docker compose run --rm --no-deps frontend node --test tests/*.test.mjs`，295／295 通過。涵蓋空白／單欄已填／雙欄已填、缺少簡介、重複匯入、長度、不自動儲存、取消及公開產物不再載入 Google 參考框。

內建 Browser 在 localhost:8081 的自有隔離帳號實測：真 Google 候選將兩個空白欄位填入；明確儲存草稿後 MariaDB 兩欄一致，重新開啟編輯頁內容仍在；再次匯入保留人工摘要並補空白介紹。舊額外參考框與補位預覽不顯示。圖片證據保留 artifacts/google-import-result.png（不入 Git）。

Windows 主機 Node 的首輪回歸因既有產頁測試使用 Linux 路徑／symlink 而失敗，已在專案 Docker Linux Node 重跑全數通過；不將首次失敗隱藏或誤報應用缺陷。

沿用既有 Google 候選來源與保存授權提示；普通 Places API 不自動授權永久保存或任意改寫內容，正式使用須確認相應權利。
