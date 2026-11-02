import { existsSync, readFileSync } from 'node:fs';
import { join, posix } from 'node:path';

// 頁面的 JS 是原生 ES module，瀏覽器要先下載入口檔、解析後才知道下一層要載入哪些檔案，
// 一頁二十多個模組就會變成好幾輪依序等待。建置時先把每個入口的靜態 import 關係展開，
// 在 <head> 放入 <link rel="modulepreload">，讓瀏覽器一開始就平行下載整棵依賴樹。

const staticImport = /(?:^|[;\n])\s*(?:import|export)\s+(?:[^'"]*?\sfrom\s+)?['"](\.{1,2}\/[^'"]+)['"]/g;

/** 回傳某個 /assets/js/ 模組（含自己）遞迴靜態相依的網址清單，順序為深度優先。 */
export function moduleGraph(entryUrl, assetsRoot, cache = new Map()) {
  const seen = new Set();
  const visit = url => {
    if (seen.has(url)) return;
    seen.add(url);
    let imports = cache.get(url);
    if (!imports) {
      // 建置時才產生的模組（例如 core/routes.js）在來源目錄不存在，仍預載它，但視為沒有下一層相依。
      const file = join(assetsRoot, url.replace(/^\/assets\//, ''));
      const source = existsSync(file) ? readFileSync(file, 'utf8') : '';
      imports = [...source.matchAll(staticImport)].map(match => posix.join(posix.dirname(url), match[1]));
      cache.set(url, imports);
    }
    imports.forEach(visit);
  };
  visit(entryUrl);
  return [...seen];
}

/** 為 HTML 中每個 type="module" 的本站腳本加上 modulepreload 提示；已存在的提示不重複加入。 */
export function addModulePreloads(html, assetsRoot, cache = new Map()) {
  const entries = [...html.matchAll(/<script\b[^>]*type="module"[^>]*src="(\/assets\/js\/[^"]+\.js)"/g)].map(
    match => match[1]
  );
  if (!entries.length) return html;
  const urls = new Set(entries.flatMap(entry => moduleGraph(entry, assetsRoot, cache)));
  const links = [...urls]
    .filter(url => !html.includes(`rel="modulepreload" href="${url}"`))
    .map(url => `<link rel="modulepreload" href="${url}">`)
    .join('\n');
  return links ? html.replace('</head>', `${links}\n</head>`) : html;
}
