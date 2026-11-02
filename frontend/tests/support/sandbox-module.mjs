// 讓原本「複製原始碼到 node:vm」的測試改為載入真正的模組，而不必重寫斷言：
// 測試沿用自己的 sandbox 物件，sandbox 裡有的名稱會取代模組的 import（依 import 的本地名稱對應），
// 沒有提供的 import 則使用真正的模組；document、window 等瀏覽器全域物件也從 sandbox 讀取。
import { readFile } from 'node:fs/promises';
import { mock } from 'node:test';

const root = new URL('../../assets/js/', import.meta.url);
const browserGlobals = [
  'document',
  'window',
  'location',
  'history',
  'navigator',
  'FormData',
  'Image',
  'HTMLElement',
  'HTMLImageElement',
  'CustomEvent',
  'sessionStorage',
  'matchMedia',
  'IntersectionObserver',
  'requestAnimationFrame',
  'fetch',
  'addEventListener',
  'removeEventListener',
  'scrollTo',
  // 測試用的可控時鐘（只有 sandbox 有提供時才換）。
  'Date',
];
let instance = 0;
const active = [];

// 以目前測試的 sandbox 取值：函式與類別透過 Proxy 轉給 sandbox 裡最新的值。
function delegate(holder, name) {
  return new Proxy(function () {}, {
    get: (_, key) => holder.sandbox[name][key],
    set: (_, key, value) => {
      holder.sandbox[name][key] = value;
      return true;
    },
    apply: (_, self, args) => holder.sandbox[name].apply(self, args),
    construct: (_, args) => new holder.sandbox[name](...args),
    has: (_, key) => key in holder.sandbox[name],
  });
}

function installGlobals(sandbox) {
  for (const name of browserGlobals) {
    if (!(name in sandbox)) continue;
    Object.defineProperty(globalThis, name, {
      configurable: true,
      get: () => sandbox[name],
      set: value => {
        sandbox[name] = value;
      },
    });
  }
}

// 由下而上準備整棵依賴樹：每個依賴都重新載入一份，並把 sandbox 有提供的名稱換成替身，
// 舊測試把多個模組串在一起執行時，間接依賴也會用到 sandbox 的替身，這裡維持同樣的效果。
async function prepare(moduleUrl, holder, mocks, prepared) {
  if (prepared.has(moduleUrl.href)) return;
  prepared.add(moduleUrl.href);
  const source = await readFile(moduleUrl, 'utf8');
  // 靜態 import 與動態 import()（例如延後載入的店家面板）都要換成替身。
  const imports = [
    ...[...source.matchAll(/^import\s*\{([^}]*)\}\s*from\s*'([^']+)';/gm)].map(([, names, specifier]) => [
      names,
      specifier,
    ]),
    ...[...source.matchAll(/\bimport\('([^']+)'\)/g)].map(([, specifier]) => ['', specifier]),
  ];
  for (const [names, specifier] of imports) {
    const dependency = new URL(specifier, moduleUrl);
    if (prepared.has(dependency.href)) continue;
    await prepare(dependency, holder, mocks, prepared);
    const fresh = await import(dependency.href + '?sandbox=' + ++instance);
    const exports = { ...fresh };
    for (const name of Object.keys(exports)) {
      if (name in holder.sandbox) {
        const value = holder.sandbox[name];
        // 函式轉給 sandbox 最新的值（測試可能中途換掉替身）；物件（含 Set、Map）直接傳入原物件。
        exports[name] = typeof value === 'function' ? delegate(holder, name) : value;
      }
    }
    // import { a as b } 以本地名稱 b 對應 sandbox。
    for (const entry of names
      .split(',')
      .map(part => part.trim())
      .filter(Boolean)) {
      const [imported, local = imported] = entry.split(/\s+as\s+/);
      if (local !== imported && local in holder.sandbox) exports[imported] = delegate(holder, local);
    }
    mocks.push(mock.module(dependency.href, { namedExports: exports }));
  }
}

/**
 * 載入 assets/js 下的模組（每次都是新的實例），回傳模組的匯出。
 * @param {string} path 例如 'pages/route-form.js'
 * @param {object} sandbox 測試原本的替身集合
 */
// 同一時間只處理一次載入：替身是全域註冊的，兩次載入交錯會互相覆蓋。
// 模組連結完成（或執行完畢）後就換下一次載入；頂層 await 可能等測試稍後才放行，不能等它整個結束。
let queue = Promise.resolve();
const settled = (promise, ms) =>
  Promise.race([
    promise.then(
      () => {},
      () => {}
    ),
    new Promise(resolve => setTimeout(resolve, ms)),
  ]);

export function loadWithSandbox(path, sandbox) {
  const turn = queue.then(async () => {
    // 上一次載入的替身保留到這裡才撤除：頁面在初始化後才用 import() 載入的模組，也要拿到替身。
    for (const handle of active.splice(0)) handle.restore();
    installGlobals(sandbox);
    const moduleUrl = new URL(path, root);
    const holder = { sandbox };
    await prepare(moduleUrl, holder, active, new Set());
    const imported = import(moduleUrl.href + '?sandbox=' + ++instance);
    await settled(imported, 200);
    return { imported };
  });
  queue = turn.catch(() => {});
  // evaluating：模組已開始執行（事件已綁好），供刻意讓頁面停在等待中的測試使用。
  const result = turn.then(({ imported }) => imported);
  result.evaluating = turn.then(() => {});
  return result;
}
