import { renderFontHead } from './font-head.mjs';
import { homeErrorView } from './home-error-view.mjs';
// 固定 HTML 頁面在前端建置時組合，PHP 不輸出任何畫面。
import { readFile, writeFile, mkdir, readdir, cp, rm } from 'node:fs/promises';
import { dirname, join, relative, resolve, sep } from 'node:path';
import { fileURLToPath } from 'node:url';
import { renderSeoHead } from './seo-head.mjs';
import { renderPolicyVersion } from './policy-pages.mjs';
import { annotateEnglishPassages } from './language-passages.mjs';
import { addModulePreloads } from './module-preload.mjs';
import { assetVersion, versionHtmlAssets, versionModuleFiles } from './asset-version.mjs';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const output = join(root, 'dist');
const modulePreloadCache = new Map();
const version = assetVersion(root);
const seoCatalog = JSON.parse(await readFile(join(root, 'resources/seo-pages.json'), 'utf8'));
if (dirname(output) !== root || output === root) throw new Error('Invalid build output');
await mkdir(output, { recursive: true });
// 每次從空的輸出目錄開始，已刪除的頁面或資產不會殘留成舊版本；只清內容不刪目錄本身，Nginx 的掛載點才不會失效。
for (const entry of await readdir(output)) await rm(join(output, entry), { recursive: true, force: true });
const templates = Object.fromEntries(
  await Promise.all(
    ['site-header', 'site-footer', 'admin-sidebar', 'admin-topbar', 'dialog'].map(async name => [
      name.replaceAll('-', '_').toUpperCase(),
      await readFile(join(root, 'templates', `${name}.html`), 'utf8'),
    ])
  )
);
async function pages(directory) {
  const result = [];
  for (const entry of await readdir(directory, { withFileTypes: true })) {
    const path = join(directory, entry.name);
    if (entry.isDirectory()) result.push(...(await pages(path)));
    else if (entry.name.endsWith('.html')) result.push(path);
  }
  return result;
}
const routes = [];
// 內容新增／編輯頁保留原型上方的「返回列表」連結，其他後台頁則維持「查看網站」。
const adminBackLinks = {
  'admin/route-add.html': ['/admin/routes.html', '← 返回推薦行程'],
  'admin/route-edit.html': ['/admin/routes.html', '← 返回推薦行程'],
  'admin/news-add.html': ['/admin/news.html', '← 返回消息與活動'],
  'admin/news-edit.html': ['/admin/news.html', '← 返回消息與活動'],
  'admin/offer-add.html': ['/admin/offers.html', '← 返回店家優惠'],
  'admin/offer-edit.html': ['/admin/offers.html', '← 返回店家優惠'],
  'admin/place-add.html': ['/admin/places.html', '← 返回景點與店家'],
  'admin/place-edit.html': ['/admin/places.html', '← 返回景點與店家'],
  'admin/film-add.html': ['/admin/films.html', '← 返回本週映演'],
  'admin/film-edit.html': ['/admin/films.html', '← 返回本週映演'],
};
// 保留原型各頁導覽的 active 樣式；food、search、auth 原版本來就沒有選中項目。
const navigationSections = {
  '/explore.html': ['explore.html', 'museum.html'],
  '/cinema.html': ['cinema.html'],
  '/events.html': ['events.html', 'activities.html', 'city-news.html'],
  '/offers.html': ['offers.html'],
  '/planner.html': ['planner.html', 'routes.html'],
};
for (const source of await pages(join(root, 'pages'))) {
  const route = relative(join(root, 'pages'), source).split(sep).join('/');
  let html = renderPolicyVersion(await readFile(source, 'utf8'), process.env.TERMS_VERSION || '2026-11-02');
  const title = html.match(/<title>([^<]*)<\/title>/)?.[1]?.split('｜')[0] ?? '映畫竹塹';
  const selectedNavigation = Object.entries(navigationSections).find(([, paths]) =>
    paths.some(path => (path.endsWith('/') ? route.startsWith(path) : route === path))
  )?.[0];
  html = html.replace(/\{\{([A-Z_]+)\}\}/g, (_, name) => {
    if (name === 'ADMIN_TOPBAR' && adminBackLinks[route]) {
      const [href, label] = adminBackLinks[route];
      return templates[name].replace(
        /<a class="admin-preview"[^>]*>[^<]*<\/a>/,
        `<a class="admin-preview" href="${href}">${label}</a>`
      );
    }
    if (name === 'SITE_HEADER' && selectedNavigation) {
      return templates[name].replace(
        `href="${selectedNavigation}"`,
        `class="active" aria-current="page" href="${selectedNavigation}"`
      );
    }
    if (name === 'PAGE_TITLE') return title;
    if (!(name in templates)) throw new Error(`Unknown template: ${name}`);
    return templates[name];
  });
  html = html.replaceAll('{{PAGE_TITLE}}', title);
  // 固定頁、保護頁與發布頁共用同一套 head；任何 query 都不含私人資料。
  html = renderSeoHead(html, {
    path: '/' + route,
    title: html.match(/<title>([^<]*)<\/title>/)?.[1],
    description: html.match(/<meta name="description" content="([^"]*)"/)?.[1],
    catalog: seoCatalog,
    origin: process.env.APP_URL || 'http://localhost:8080',
  });
  if (html.includes('{{')) throw new Error(`Unresolved template: ${route}`);
  const target = join(output, route);
  await mkdir(dirname(target), { recursive: true });
  // 各頁共用登入期限監看；API 事件可以在入口程式載入前先暫存在記憶體。
  html = renderFontHead(html);
  html = html.replace(
    '</body>',
    '<script type="module" src="/assets/js/session-monitor.js"></script></body>'
  );
  // 讓瀏覽器一開始就平行下載整棵模組依賴，而不是逐層等待 import。
  html = addModulePreloads(html, join(root, 'assets'), modulePreloadCache);
  html = versionHtmlAssets(html, version);
  await writeFile(target, annotateEnglishPassages(html));
  // 內部使用的 503 回應頁資產，不列入固定公開頁、sitemap 或正常發布資料。
  if (route === 'index.html')
    await writeFile(join(output, 'home-unavailable.response'), annotateEnglishPassages(homeErrorView(html)));
  routes.push(`/${route}`);
}
await cp(join(root, 'assets'), join(output, 'assets'), { recursive: true });
await mkdir(join(output, 'assets/js/core'), { recursive: true });
await writeFile(
  join(output, 'assets/js/core/routes.js'),
  `export const routes = new Set(${JSON.stringify(['/', ...routes])});\n`
);
// 輸出目錄裡的模組改寫成帶版本號的 import，和 HTML 引用的網址一致；原始檔不動。
versionModuleFiles(join(output, 'assets'), version);
// 真正的 robots.txt 由成功的發布版本提供；建置階段不放入可能過時的 sitemap 網址。
await writeFile(join(output, 'robots.txt'), 'User-agent: *\nDisallow: /admin/\n');
console.log(`Built ${routes.length} HTML routes with shared templates (assets v=${version}).`);
