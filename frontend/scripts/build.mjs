// [E-12] 固定 HTML 在前端建置時組合；PHP 不輸出任何畫面。
import { readFile, writeFile, mkdir, readdir, cp } from 'node:fs/promises';
import { dirname, join, relative, resolve, sep } from 'node:path';
import { fileURLToPath } from 'node:url';
import { renderSeoHead } from './seo-head.mjs';
import { renderPolicyVersion } from './policy-pages.mjs';
import { annotateEnglishPassages } from './language-passages.mjs';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const output = join(root, 'dist');
const seoCatalog=JSON.parse(await readFile(join(root,'resources/seo-pages.json'),'utf8'));
if (dirname(output) !== root || output === root) throw new Error('Invalid build output');
await mkdir(output, { recursive: true });
const templates = Object.fromEntries(await Promise.all(
  ['site-header', 'site-footer', 'admin-sidebar', 'admin-topbar', 'dialog'].map(async name => [name.replaceAll('-', '_').toUpperCase(), await readFile(join(root, 'templates', `${name}.html`), 'utf8')])
));
async function pages(directory) {
  const result = [];
  for (const entry of await readdir(directory, { withFileTypes: true })) {
    const path = join(directory, entry.name);
    if (entry.isDirectory()) result.push(...await pages(path));
    else if (entry.name.endsWith('.html')) result.push(path);
  }
  return result;
}
const routes = [];
// [U-05／E-29] 內容新增／編輯頁保留原型的上方返回列表；其他後台仍查看網站。
const adminBackLinks = {
  'admin/place-add.html': ['/admin/places.html', '← 返回景點與店家'],
  'admin/place-edit.html': ['/admin/places.html', '← 返回景點與店家'],
  'admin/film-add.html': ['/admin/films.html', '← 返回本週映演'],
  'admin/film-edit.html': ['/admin/films.html', '← 返回本週映演'],
};
// [U-03 / E-12] 保留原型各頁導覽的 active 樣式；food/search/auth 原版沒有選中項目。
const navigationSections = {
  '/explore.html': ['explore.html', 'museum.html', 'place-detail.html', 'places/'],
  '/cinema.html': ['cinema.html', 'film-detail.html', 'films/'],
  '/events.html': ['events.html', 'activities.html', 'city-news.html', 'news-detail.html', 'news/'],
  '/offers.html': ['offers.html', 'offer-detail.html', 'offers/'],
  '/planner.html': ['planner.html', 'routes.html'],
};
for (const source of await pages(join(root, 'pages'))) {
  const route = relative(join(root, 'pages'), source).split(sep).join('/');
  let html = renderPolicyVersion(await readFile(source, 'utf8'),process.env.TERMS_VERSION||'local-development-v1');
  if (html.includes('/assets/js/public.js') && !html.includes('/assets/css/phase6.css')) {
    html = html.replace('</head>', '<link rel="stylesheet" href="/assets/css/phase6.css">\n</head>');
  }
  const title = html.match(/<title>([^<]*)<\/title>/)?.[1]?.split('｜')[0] ?? '映畫竹塹';
  const selectedNavigation = Object.entries(navigationSections).find(([, paths]) => paths.some(path => path.endsWith('/') ? route.startsWith(path) : route === path))?.[0];
  html = html.replace(/\{\{([A-Z_]+)\}\}/g, (_, name) => {
    if (name === 'ADMIN_TOPBAR' && adminBackLinks[route]) {
      const [href, label] = adminBackLinks[route];
      return templates[name].replace(/<a class="admin-preview"[^>]*>[^<]*<\/a>/, `<a class="admin-preview" href="${href}">${label}</a>`);
    }
    if (name === 'SITE_HEADER' && selectedNavigation) {
      return templates[name].replace(`href="${selectedNavigation}"`, `class="active" aria-current="page" href="${selectedNavigation}"`);
    }
    return templates[name] ?? (name === 'PAGE_TITLE' ? title : (() => { throw new Error(`Unknown template: ${name}`); })());
  });
  html = html.replaceAll('{{PAGE_TITLE}}', title);
  html = html.replace(/<form id="(auth-form|admin-form|permissions-form)"/g, '<form method="post" id="$1"');
  if (/data-auth-kind=/.test(html) || route.startsWith('admin/')) {
    html = html.replace(/<input\b([^>]*)>/g, (match, attributes) => {
      const additions = [];
      if (!/spellcheck=/.test(attributes)) additions.push('spellcheck="false"');
      if (!/autocomplete=/.test(attributes)) additions.push('autocomplete="off"');
      return `<input${attributes} ${additions.join(' ')}>`;
    });
  }
  html = html.replace(/<input\b[^>]*type="password"[^>]*>/g, match => match.replace(/\s(?:minlength|maxlength)="\d+"/g, ''));
  // [S：SEO E7/E17；E-28] 固定／保護頁與發布頁共用 head，任何 query 均不含私人資料。
  html=renderSeoHead(html,{path:'/'+route,title:html.match(/<title>([^<]*)<\/title>/)?.[1],
    description:html.match(/<meta name="description" content="([^"]*)"/)?.[1],catalog:seoCatalog,origin:process.env.APP_URL||'http://localhost:8080'});
  if (html.includes('{{')) throw new Error(`Unresolved template: ${route}`);
  const target = join(output, route);
  await mkdir(dirname(target), { recursive: true });
  // [S G34／B15；E-29] 各頁共用期限監看；API 事件可在入口載入前暫存於記憶體。
  html=html.replace('</body>','<script type="module" src="/assets/js/session-monitor.js"></script></body>');
  await writeFile(target, annotateEnglishPassages(html));
  routes.push(`/${route}`);
}
await cp(join(root, 'assets'), join(output, 'assets'), { recursive: true });
await mkdir(join(output, 'assets/js/core'), { recursive: true });
await writeFile(join(output, 'assets/js/core/routes.js'), `export const routes = new Set(${JSON.stringify(['/', ...routes])});\n`);
await writeFile(join(output, 'robots.txt'), 'User-agent: *\nDisallow: /admin/\nDisallow: /api/\nDisallow: /reset-password.html\n');
console.log(`Built ${routes.length} HTML routes with shared templates.`);
