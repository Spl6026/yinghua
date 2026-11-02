// 站內頁面清單。建置時 scripts/build.mjs 會依 pages/ 的實際頁面覆寫輸出目錄中的這個檔案；
// 原始碼保留空清單，讓模組在未建置的環境（例如單元測試）也能載入。
export const routes = new Set();
