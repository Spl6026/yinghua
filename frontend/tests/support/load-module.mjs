// 載入真正的頁面模組，只把它依賴的模組換成測試替身。
// 每次載入都加上不同的 query，取得全新的模組實例，測試之間不共用模組內的狀態。
import { mock } from 'node:test';

const root = new URL('../../assets/js/', import.meta.url);
let instance = 0;

/**
 * @param {string} path 相對 assets/js 的模組路徑，例如 'pages/route-list.js'
 * @param {Record<string, object>} stubs 依賴模組路徑（同樣相對 assets/js）→ 要替換的匯出
 */
export async function loadModule(path, stubs = {}) {
  // core/routes.js 由建置流程依頁面清單產生，原始碼目錄沒有這個檔案；測試預設提供空清單。
  stubs = { 'core/routes.js': { routes: new Set() }, ...stubs };
  const mocks = Object.entries(stubs).map(([dependency, exports]) =>
    mock.module(new URL(dependency, root).href, { namedExports: exports })
  );
  try {
    return await import(new URL(path, root).href + '?instance=' + ++instance);
  } finally {
    for (const handle of mocks) handle.restore();
  }
}
