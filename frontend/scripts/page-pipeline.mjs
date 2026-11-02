// build.mjs（固定頁）與 publish.mjs（發布頁）共用的頁面處理步驟，兩邊產生的頁面才會一致。
import { readFile } from 'node:fs/promises';
import { join } from 'node:path';
import { renderFontHead } from './font-head.mjs';
import { addModulePreloads } from './module-preload.mjs';
import { versionHtmlAssets } from './asset-version.mjs';
import { mustReplace } from './html-edit.mjs';

const templateNames = ['site-header', 'site-footer', 'admin-sidebar', 'admin-topbar', 'dialog'];

/** 讀取共用模板，鍵名為頁面中的佔位符，例如 SITE_HEADER。 */
export async function loadTemplates(root) {
  return Object.fromEntries(
    await Promise.all(
      templateNames.map(async name => [
        name.replaceAll('-', '_').toUpperCase(),
        await readFile(join(root, 'templates', `${name}.html`), 'utf8'),
      ])
    )
  );
}

// 主選單每一項涵蓋的頁面；美食、搜尋與帳號頁在原型中沒有選中項目。
const navigationSections = [
  ['/explore.html', /^\/(?:explore|museum)\.html|^\/places\//],
  ['/cinema.html', /^\/cinema\.html|^\/films\//],
  ['/events.html', /^\/(?:events|activities|city-news)\.html|^\/news\//],
  ['/offers.html', /^\/offers(?:\.html|\/)/],
  ['/planner.html', /^\/(?:planner|routes)\.html/],
];

/** 依頁面路徑標記頁首主選單的目前項目。 */
export function markActiveNavigation(header, path) {
  const href = navigationSections.find(([, pattern]) => pattern.test(path))?.[0];
  return href
    ? mustReplace(header, `href="${href}"`, `class="active" aria-current="page" href="${href}"`)
    : header;
}

/**
 * 寫檔前的共同步驟：字型預載、登入期限監看、模組預載與資產版本號。
 * 順序有意義：版本號要在模組預載之後加，預載的網址才會帶到版本號。
 * 英文段落標示留給呼叫端在寫檔時加，首頁錯誤頁要從尚未標示的頁面衍生。
 */
export function finalizePage(html, { assetsRoot, version, preloadCache }) {
  html = renderFontHead(html);
  // 各頁共用登入期限監看；API 事件可以在入口程式載入前先暫存在記憶體。
  html = mustReplace(
    html,
    '</body>',
    '<script type="module" src="/assets/js/session-monitor.js"></script></body>'
  );
  // 讓瀏覽器一開始就平行下載整棵模組依賴，而不是逐層等待 import。
  html = addModulePreloads(html, assetsRoot, preloadCache);
  return versionHtmlAssets(html, version);
}
