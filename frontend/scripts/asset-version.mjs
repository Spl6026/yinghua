import { createHash } from 'node:crypto';
import { readdirSync, readFileSync, writeFileSync } from 'node:fs';
import { join, relative, sep } from 'node:path';

// CSS／JS 網址加上內容版本號（?v=…），Nginx 對帶版本號的網址回應長期快取。
// 沒有版本號時，瀏覽器每換一頁都要把二、三十個檔案逐一問伺服器「有沒有變」，
// 網路延遲一高，光等這些確認就要大半秒；有版本號就能直接使用快取，檔案一改版本號跟著變。
//
// 版本號只由前端原始檔決定，建置（build.mjs）與內容發布（publish.mjs）各自計算都會得到同一個值，
// 正式環境的發布容器裡沒有 dist，也不需要另外傳遞版本檔。字型檔名本身就代表版本，不另外加。

function filesUnder(directory) {
  const result = [];
  for (const entry of readdirSync(directory, { withFileTypes: true })) {
    const path = join(directory, entry.name);
    if (entry.isDirectory()) result.push(...filesUnder(path));
    else result.push(path);
  }
  return result;
}

/**
 * 計算前端資產的版本號：assets/ 下除字型外的所有檔案內容、建置腳本（決定輸出檔怎麼改寫），
 * 再加上頁面清單（建置時產生的 core/routes.js 由頁面清單決定）。
 */
export function assetVersion(root) {
  const hash = createHash('sha256');
  const files = ['assets', 'scripts', 'pages']
    .flatMap(directory => filesUnder(join(root, directory)))
    .map(path => relative(root, path).split(sep).join('/'))
    .filter(path => !path.startsWith('assets/fonts/'))
    .sort();
  for (const path of files) {
    hash.update(path + '\0');
    if (!path.startsWith('pages/')) hash.update(readFileSync(join(root, path)));
    hash.update('\0');
  }
  return hash.digest('hex').slice(0, 12);
}

// 字型另以檔名區分版本，其餘 /assets/ 下的 CSS、JS 與圖示都加上版本號。
const htmlAsset = /((?:src|href)=")(\/assets\/(?!fonts\/)[^"?#]+\.(?:js|css|svg))"/g;

/** HTML 中引用的 /assets/ 檔案（含 modulepreload）加上版本號。 */
export function versionHtmlAssets(html, version) {
  return html.replace(htmlAsset, (_, prefix, url) => `${prefix}${url}?v=${version}"`);
}

// 靜態 import／export…from 與動態 import() 的相對路徑；瀏覽器以完整網址（含 query）區分模組，
// 所以模組之間互相引用也必須帶同一個版本號，否則同一個檔案會被載入成兩份、各自保有狀態。
const moduleSpecifier = /(\b(?:from|import)\s*\(?\s*)(['"])(\.{1,2}\/[^'"?]+\.js)\2/g;

/** JS 模組內的相對 import 加上版本號。 */
export function versionModuleImports(source, version) {
  return source.replace(
    moduleSpecifier,
    (_, prefix, quote, path) => `${prefix}${quote}${path}?v=${version}${quote}`
  );
}

/** 把建置輸出中 assets/js 的所有模組改寫成帶版本號的 import。 */
export function versionModuleFiles(outputAssets, version) {
  for (const path of filesUnder(join(outputAssets, 'js')).filter(path => path.endsWith('.js'))) {
    writeFileSync(path, versionModuleImports(readFileSync(path, 'utf8'), version));
  }
}
