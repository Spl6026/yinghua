// 發布時把資料寫進原型頁面的固定位置。原型或模板的標記一改，若替換靜靜地沒有發生，
// 頁面仍會發布成功、只是少了內容，很難發現；所以找不到預期的標記時直接中止這次發布。

/** 與 String.prototype.replace 相同，但 pattern 必須在 html 中出現，否則拋出錯誤。 */
export function mustReplace(html, pattern, replacement) {
  const found =
    typeof pattern === 'string'
      ? html.includes(pattern)
      : new RegExp(pattern.source, pattern.flags.replace('g', '')).test(html);
  if (!found)
    throw new Error(`Publication template is missing the expected markup: ${String(pattern).slice(0, 80)}`);
  return html.replace(pattern, replacement);
}
