import { renderFontHead } from './font-head.mjs';
import { escapeHtml } from '../assets/js/core/content-view.js';
// 由 PHP CLI／API 提供已驗證的資料，HTML 只在前端產生。
// 每個 generation 都包含完整的公開資料與頁面，全部寫完後才以原子操作切換 current。
import { readFile, writeFile, mkdir, rename, symlink, rm, realpath } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { openingHoursLines } from '../assets/js/core/opening-hours.js';
import {
  weeklyFilms,
  filmRows,
  filmCards,
  filmDetail,
  screeningPeriodLabel,
} from '../assets/js/core/film-view.js';
import { publishNews } from './public-news.mjs';
import { publishOffers } from './public-offers.mjs';
import { routeCards } from '../assets/js/core/route-view.js';
import { contentNeighbors } from '../assets/js/core/content-neighbors.js';
import { homeNewsRows } from '../assets/js/core/home-view.js';
import { renderSeoHead } from './seo-head.mjs';
import { renderPolicyVersion } from './policy-pages.mjs';
import { annotateEnglishPassages } from './language-passages.mjs';
import { addModulePreloads } from './module-preload.mjs';
import { renderPublicContentIndex } from './public-content-index.mjs';
import { resolveSeo } from '../assets/js/core/seo-resolver.js';
import { crawlerEntry, buildSitemaps, robotsText } from './crawler-index.mjs';

const source = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const modulePreloadCache = new Map();
const output = resolve(process.argv[2] || '/var/www/publication');
let input = '';
for await (const chunk of process.stdin) {
  input += chunk;
  if (input.length > 32 * 1024 * 1024) throw new Error('Publication too large');
}
const snapshot = JSON.parse(input);
const seoCatalog = JSON.parse(await readFile(join(source, 'resources/seo-pages.json'), 'utf8'));
const publicImages = ['items', 'films', 'news', 'offers']
  .flatMap(key => (snapshot[key] || []).flatMap(item => [item.image, ...(item.bodyImages || [])]))
  .filter(Boolean);
if (
  !/^[a-f0-9]{64}$/.test(snapshot.generation) ||
  !Array.isArray(snapshot.items) ||
  !/^https?:\/\//.test(snapshot.origin)
)
  throw new Error('Invalid publication');
const e = value =>
  String(value ?? '').replace(
    /[&<>"']/g,
    character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character]
  );
const url = item => (item.isMuseum ? '/museum.html' : `/places/${item.slug}.html`);
const json = value =>
  JSON.stringify(value).replaceAll('<', '\\u003c').replaceAll('>', '\\u003e').replaceAll('&', '\\u0026');
// 資料庫保存 UTC 時間，網站上的日期依台北時區顯示。
const localDate = value =>
  new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Taipei',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).format(new Date(value));
// 含收藏控制的圖片區，要讓互動子元素保留在無障礙樹中。
const image = (item, className) =>
  `<div class="${className}" data-image-url="${e(item.image)}" role="${className === 'place-image' ? 'group' : 'img'}" aria-label="${e(item.imageAlt || item.name)}" style="background-image:url(&quot;${e(item.image || '/assets/image-placeholder.svg')}&quot;)"></div>`;
// 選填文字不補預設內容；與瀏覽器端卡片一致，省略空段落與多餘的分隔符號。
const card = item =>
  `<article class="place-card reveal in-view">${image(item, 'place-image').replace('</div>', () => `<span class="place-type">${e(item.typeLabel || item.category)}</span><button type="button" class="favorite-button" aria-label="收藏${e(item.name)}" aria-pressed="false" data-favorite="${e(item.id)}">♡</button></div>`)}<div class="place-body"><span class="place-area">${[item.district, item.recommendationLabel || '在地推薦'].filter(Boolean).map(e).join('　/　')}</span><h3>${e(item.name)}</h3>${item.summary ? `<p>${e(item.summary)}</p>` : ''}<div class="place-details"><span>◷ ${e(item.hours || '依店家公告')}</span><span class="place-actions"><a href="${e(url(item))}">看介紹 ↗</a><button type="button" data-add-stop="${e(item.id)}">＋ 行程</button></span></div></div></article>`;
const templates = Object.fromEntries(
  await Promise.all(
    ['site-header', 'site-footer', 'admin-sidebar', 'admin-topbar', 'dialog'].map(async name => [
      name.replaceAll('-', '_').toUpperCase(),
      await readFile(join(source, 'templates', name + '.html'), 'utf8'),
    ])
  )
);
const filmEnabled = Array.isArray(snapshot.films);
const newsEnabled = Array.isArray(snapshot.news);
const offersEnabled = Array.isArray(snapshot.offers);
const filmItems = weeklyFilms(snapshot.films || [], snapshot.filmPeriod);
const weekLabel = snapshot.filmPeriod ? screeningPeriodLabel(snapshot.filmPeriod) : '';
const root = join(output, 'generations', snapshot.generation);
await mkdir(root, { recursive: true });
// 已完成的 generation 不覆寫，避免正在讀取的版本遭原地修改。
let existing = false;
try {
  existing =
    JSON.parse(await readFile(join(root, '.snapshot.json'), 'utf8')).generation === snapshot.generation;
} catch {}
const indexEntries = [];

async function page(path, html, title, description, item, canonicalPath = path, managed = true) {
  if (snapshot.detailNeighbors?.[path])
    html = html.replace('</main>', () => contentNeighbors(snapshot.detailNeighbors[path]) + '</main>');
  html = renderPolicyVersion(html, snapshot.policyVersion || '2026-11-02');
  if (!html.includes('/assets/css/phase6.css'))
    html = html.replace('</head>', '<link rel="stylesheet" href="/assets/css/phase6.css"></head>');
  html = html.replace(/<script type="application\/ld\+json">[\s\S]*?<\/script>/g, '');
  const context = {
    path: canonicalPath,
    title,
    description,
    item,
    seo: snapshot.seo,
    catalog: seoCatalog,
    origin: snapshot.origin,
    publicImages,
    published: true,
    unavailable: canonicalPath === '/museum.html' && !item,
    modifiedAt: snapshot.indexModifiedAt?.[canonicalPath],
  };
  let meta = resolveSeo(context);
  if (context.unavailable) meta = { ...meta, robots: 'noindex,follow' };
  html = html.replace(
    '</head>',
    '<noscript><style>.reveal,.image-reveal{opacity:1!important;transform:none!important}</style></noscript></head>'
  );
  if (managed)
    html = html.replace(
      '<body>',
      `<body data-managed-attractions="true"${filmEnabled ? ' data-managed-films="true"' : ''}${newsEnabled ? ' data-managed-news="true"' : ''}${offersEnabled ? ' data-managed-offers="true"' : ''}>`
    );
  html = html.replace(
    '</head>',
    '<link rel="stylesheet" href="/assets/css/phase3.css"><link rel="stylesheet" href="/assets/css/films.css"><link rel="stylesheet" href="/assets/css/public-content.css"><link rel="stylesheet" href="/assets/css/ui-consistency.css"></head>'
  );
  html = html.replace(/\{\{([A-Z_]+)\}\}/g, (_, name) => {
    const template = templates[name];
    if (template === undefined) throw new Error('Unknown template');
    if (name === 'SITE_HEADER' && canonicalPath === '/routes.html')
      return template.replace(
        'href="/planner.html"',
        'class="active" aria-current="page" href="/planner.html"'
      );
    if (
      name === 'SITE_HEADER' &&
      (canonicalPath.startsWith('/offers.html') || canonicalPath.startsWith('/offers/'))
    )
      return template.replace(
        'href="/offers.html"',
        'class="active" aria-current="page" href="/offers.html"'
      );
    if (
      name === 'SITE_HEADER' &&
      (/^\/(?:events|activities|city-news)\.html/.test(canonicalPath) || canonicalPath.startsWith('/news/'))
    )
      return template.replace(
        'href="/events.html"',
        'class="active" aria-current="page" href="/events.html"'
      );
    if (name === 'SITE_HEADER' && (path === '/cinema.html' || path.startsWith('/films/')))
      return template.replace(
        'href="/cinema.html"',
        'class="active" aria-current="page" href="/cinema.html"'
      );
    return name === 'SITE_HEADER' &&
      (path === '/explore.html' || path === '/museum.html' || path.startsWith('/places/'))
      ? template.replace('href="/explore.html"', 'class="active" aria-current="page" href="/explore.html"')
      : template;
  });
  html = renderSeoHead(html, context, meta);
  html = renderPolicyVersion(html, snapshot.policyVersion || '2026-11-02', snapshot.general?.values);
  html = html.replace(
    /(<span class="brand-copy"><strong>)[\s\S]*?(<\/strong>)/g,
    (_, a, b) => a + escapeHtml(meta.siteName) + b
  );
  html = html.replace(
    /(<span data-site-copyright>)[\s\S]*?(<\/span>)/,
    (_, a, b) => a + '© 2026 ' + escapeHtml(meta.siteName) + '・新竹城市生活指南' + b
  );
  if (snapshot.general?.values.contactEmail)
    html = html.replace(
      '<a href="/site-map.html">',
      () =>
        '<a data-site-contact href="mailto:' +
        escapeHtml(snapshot.general.values.contactEmail) +
        '">聯絡我們</a><a href="/site-map.html">'
    );
  if (managed)
    html = html.replace(
      '</body>',
      () =>
        `<script type="application/json" id="attraction-publication">${json({ generation: snapshot.generation, items: snapshot.items })}</script><script type="module" src="/assets/js/pages/attractions.js"></script></body>`
    );
  if (managed && filmEnabled)
    html = html.replace(
      '</body>',
      () =>
        `<script type="application/json" id="film-publication">${json({ generation: snapshot.generation, total: filmItems.length, period: snapshot.filmPeriod })}</script><script type="module" src="/assets/js/pages/films.js"></script></body>`
    );
  const target = join(root, path.slice(1));
  await mkdir(dirname(target), { recursive: true });
  html = renderFontHead(html);
  html = html.replace(
    '</body>',
    '<script type="module" src="/assets/js/session-monitor.js"></script></body>'
  );
  // 讓瀏覽器一開始就平行下載整棵模組依賴，而不是逐層等待 import。
  html = addModulePreloads(html, join(source, 'assets'), modulePreloadCache);
  await writeFile(target, annotateEnglishPassages(html));
  const entry = crawlerEntry(context, meta);
  if (entry) indexEntries.push(entry);
}

if (!existing) {
  for (const kind of ['explore', 'food']) {
    let html = await readFile(join(source, 'pages', kind + '.html'), 'utf8');
    const items = snapshot.items.filter(item => kind === 'explore' || item.category === '美食');
    html = html.replace(
      /(<div class="place-grid" id="place-grid">)[\s\S]*?(<\/div>)/,
      (_, open, close) =>
        open +
        (items.slice(0, 12).map(card).join('') || '<p class="empty-results">目前沒有地點資訊。</p>') +
        close
    );
    html = html.replace(
      /data-category="([^"]+)"([\s\S]*?)<span>\d+<\/span>/g,
      (_, category, rest) =>
        `data-category="${category}"${rest}<span>${String(snapshot.items.filter(item => category === 'all' || item.category === category).length).padStart(2, '0')}</span>`
    );
    await page(
      `/${kind}.html`,
      html,
      kind === 'explore' ? '城市散策｜映畫竹塹' : '新竹美食｜映畫竹塹',
      kind === 'explore' ? '探索新竹景點、文化場館與在地店家。' : '探索新竹美食與巷弄店家。'
    );
  }
  const detailTemplate = await readFile(join(source, 'pages', 'museum.html'), 'utf8');
  for (const item of snapshot.items) {
    if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(item.slug)) throw new Error('Invalid content slug');
    const metadata =
      [item.address, item.transport && `交通：${item.transport}`]
        .filter(Boolean)
        .map(text => `<p>${e(text).replaceAll('\n', '<br>')}</p>`)
        .join('') +
      `<div class="opening-hours-detail"><h2>營業時間</h2>${openingHoursLines(item.openingHours, item.hours)
        .map(line => `<p>${e(line).replaceAll('\n', '<br>')}</p>`)
        .join(
          ''
        )}${item.openingHours ? '<small>跨日時段歸開始日；實際營業以店家公告為準。</small>' : ''}</div>`;
    const telephone =
      item.phone && /^tel:[+0-9]+(?:;ext=[0-9]+)?$/.test(item.phoneUrl || '')
        ? `<p>電話：<a href="${e(item.phoneUrl)}">${e(item.phone)}</a></p>`
        : '';
    const links = [
      [item.mapUrl, '在 Google 地圖開啟'],
      [item.websiteUrl, '官方網站'],
    ]
      .filter(([href]) => href)
      .map(
        ([href, label]) =>
          `<a class="text-link" href="${e(href)}" target="_blank" rel="noopener noreferrer">${label} ↗</a>`
      )
      .join('　');
    const body = `<section class="section-pad detail-page">${image(item, 'detail-cover')}<div class="detail-body"><p class="eyebrow">CITY STORY / DETAIL</p><h1>${e(item.name)}</h1>${item.summary ? `<p class="detail-lead">${e(item.summary)}</p>` : ''}${item.description
      .split(/\n\s*\n/)
      .filter(text => text.trim())
      .map(text => `<p>${e(text).replaceAll('\n', '<br>')}</p>`)
      .join(
        ''
      )}${!item.description ? `<div class="attraction-description-empty"><p>尚未提供景點介紹。</p></div>` : ''}${metadata}${telephone}<div class="detail-meta">${[item.typeLabel || item.category, item.district].filter(Boolean).map(e).join('・')}</div>${links}<div class="detail-actions"><a class="button button-dark" href="/explore.html">回到城市散策 ↗</a><button type="button" class="text-link" data-add-stop="${e(item.id)}">安排進旅行 <span>→</span></button><button type="button" class="text-link" data-favorite="${e(item.id)}" aria-pressed="false">收藏此景點 ♡</button></div><small>更新日期：${e(localDate(item.updatedAt))}</small></div></section>`;
    const html = detailTemplate.replace(
      /<section class="section-pad detail-page">[\s\S]*?<\/section>/,
      () => body
    );
    await page(
      url(item),
      html,
      item.seoTitle || `${item.name}｜映畫竹塹`,
      item.seoDescription || item.summary,
      item
    );
  }
  if (!snapshot.items.some(item => item.isMuseum)) {
    await page(
      '/museum.html',
      detailTemplate.replace(
        /<section class="section-pad detail-page">[\s\S]*?<\/section>/,
        '<section class="section-pad detail-page"><h1>博物館資訊更新中</h1><p>博物館介紹即將更新。</p><a href="/explore.html">回到城市散策</a></section>'
      ),
      '博物館資訊更新中｜映畫竹塹',
      '博物館介紹目前更新中。'
    );
  }
  let home = await readFile(join(source, 'pages', 'index.html'), 'utf8');
  const museum = snapshot.home ? snapshot.home.museum : snapshot.items.find(item => item.isMuseum);
  const featured = snapshot.home
    ? snapshot.home.featured
    : snapshot.items.find(item => item.featured) || museum;
  if (museum)
    home = home.replace(
      /(<div class="intro-copy reveal">)[\s\S]*?(<div class="museum-facts">)/,
      (_, open, close) =>
        open +
        `<p>${e(museum.summary)}</p>${museum.description ? `<p>${e(museum.description)}</p>` : `<div><p>尚未提供景點介紹。</p></div>`}` +
        close
    );
  if (featured) {
    home = home
      .replace(
        /<a class="museum-feature image-reveal"[^>]*>/,
        () =>
          `<a class="museum-feature image-reveal" href="${e(url(featured))}" aria-label="${e(featured.name)}">`
      )
      .replace(
        /<div class="museum-photo"[^>]*>/,
        () =>
          `<div class="museum-photo" data-image-url="${e(featured.image)}" role="img" aria-label="${e(featured.imageAlt || featured.name)}" style="background-image:url(&quot;${e(featured.image)}&quot;)">`
      )
      .replace(
        /(<div class="museum-feature-label">)[\s\S]*?(<\/div>)/,
        (_, open, close) =>
          open + `<span>${e(featured.category)}</span><strong>${e(featured.name)}</strong>` + close
      );
  } else {
    home = home.replace(
      /<a class="museum-feature image-reveal"[\s\S]*?<\/a>/,
      '<div class="museum-feature"><p>精選景點即將更新。</p></div>'
    );
  }
  if (!museum)
    home = home.replace(
      /<div class="intro-copy reveal">[\s\S]*?<\/div>\s*<\/div>\s*<\/div>/,
      '<div class="intro-copy reveal"><p>博物館參觀資訊更新中，請稍後再訪。</p></div>'
    );
  if (filmEnabled) {
    home = home
      .replace(
        /(<div class="screening-grid">)[\s\S]*?(<\/div>\s*<p class="demo-note">)/,
        (_, open, close) =>
          open +
          filmCards(snapshot.home?.films || filmItems, snapshot.home?.filmTotal ?? filmItems.length) +
          close
      )
      .replace('10.10 — 10.16', weekLabel)
      .replace(
        '映演內容為示意資料，實際場次請以影像博物館公告為準。',
        '實際放映資訊請以影像博物館最新公告為準。'
      );
    let cinema = await readFile(join(source, 'pages/cinema.html'), 'utf8');
    cinema = cinema
      .replace(
        /(<div class="cinema-list" id="cinema-list">)[\s\S]*?(<\/div>)/,
        (_, open, close) => open + filmRows(filmItems.slice(0, 20)) + close
      )
      // 以穩定的 id 定位並更新放映週期，保留英文的語言標示，不依賴原型的純文字內容。
      .replace(
        /(<p\b[^>]*\bid="film-period"[^>]*>)[\s\S]*?(<\/p>)/,
        (_, open, close) => `${open}<bdi lang="en">THIS WEEK</bdi> · ${e(weekLabel)}${close}`
      )
      .replace('※ 場次為示意資料', '實際放映資訊請以最新公告為準。');
    // 與館舍詳情共用同一份快照與週表，不另外保存開放時間。
    const visit = museum
      ? `<div class="museum-info"><div><p class="eyebrow">VISIT THE MUSEUM</p><h2>${e(museum.name)}</h2><p>${[
          museum.address,
          ...openingHoursLines(museum.openingHours, museum.hours),
        ]
          .filter(Boolean)
          .map(text => e(text).replaceAll('\n', '<br>'))
          .join(
            '<br>'
          )}</p></div><a class="text-link" href="/museum.html">查看館舍與交通資訊 <span>↗</span></a></div>`
      : '<div class="museum-info"><div><p class="eyebrow">VISIT THE MUSEUM</p><h2>博物館參觀資訊</h2><p>博物館介紹即將更新。</p></div><a class="text-link" href="/museum.html">查看館舍介紹 <span>↗</span></a></div>';
    cinema = cinema.replace(/<!-- MUSEUM_VISIT_START -->[\s\S]*?<!-- MUSEUM_VISIT_END -->/, () => visit);
    await page(
      '/cinema.html',
      cinema,
      '本週映演｜映畫竹塹',
      '查看影像博物館本週放映日期、時間、電影介紹與海報。'
    );
    // 電影頁採用原型的電影專用頁殼，保留「本週映演」導覽，不沿用城市散策頁。
    const filmTemplate = await readFile(join(source, 'pages/films/window-sunday.html'), 'utf8');
    for (const film of snapshot.films) {
      if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(film.slug)) throw new Error('Invalid film slug');
      const html = filmTemplate.replace(/<section class="section-pad detail-page">[\s\S]*?<\/section>/, () =>
        filmDetail(film)
      );
      const description =
        film.seoDescription ||
        Array.from(film.synopsis || `${film.title}的電影介紹與放映場次。`)
          .slice(0, 500)
          .join('');
      await page(film.url, html, film.seoTitle || `${film.title}｜映畫竹塹`, description, {
        ...film,
        category: '電影',
      });
    }
  }
  if (newsEnabled) {
    home = home.replace(
      /(<div class="event-list">)[\s\S]*?<\/div>/,
      (_, open) => open + homeNewsRows(snapshot.home?.news || snapshot.news.slice(0, 3)) + '</div>'
    );
    await publishNews(source, snapshot, page);
  }
  if (offersEnabled) await publishOffers(source, snapshot, page);
  if (Array.isArray(snapshot.recommendedRoutes)) {
    let html = await readFile(join(source, 'pages/routes.html'), 'utf8');
    html = html.replace(
      /<div class="route-cards" id="route-cards">\s*<\/div>/,
      () => '<div class="route-cards" id="route-cards">' + routeCards(snapshot.recommendedRoutes) + '</div>'
    );
    await page(
      '/routes.html',
      html,
      '推薦行程｜映畫竹塹',
      '參考新竹主題散步推薦行程，查看有序景點與預估時間。'
    );
  }
  if (snapshot.home)
    home = home.replace(
      '</body>',
      () =>
        `<script type="application/json" id="home-publication">${json({ ...snapshot.home, generation: snapshot.generation })}</script></body>`
    );
  // 與原型及後台 SEO 目錄使用同一個標題；舊字串以品牌開頭又被加上後綴，會讓「映畫竹塹」重複出現。
  await page(
    '/index.html',
    home,
    '在城市裡，遇見一場好電影｜映畫竹塹',
    '從新竹市影像博物館出發，探索本週電影、城市散策、在地美食、店家優惠、活動消息與新竹旅行提案。',
    featured
  );
  const indexTemplate = await readFile(join(source, 'pages/site-map.html'), 'utf8');
  await page(
    '/site-map.html',
    indexTemplate.replace(
      '<div id="public-content-index"></div>',
      () => `<div id="public-content-index">${renderPublicContentIndex(snapshot)}</div>`
    ),
    '網站導覽｜映畫竹塹',
    '依分類瀏覽或按名稱尋找映畫竹塹的景點、電影、活動消息與店家優惠。',
    undefined,
    '/site-map.html',
    false
  );
  // 非內容型的固定公開頁也放進同一個 generation；保護頁使用安全的靜態 shell。
  const managedPaths = new Set([
    '/index.html',
    '/explore.html',
    '/food.html',
    '/museum.html',
    '/cinema.html',
    '/events.html',
    '/activities.html',
    '/city-news.html',
    '/offers.html',
    '/routes.html',
    '/site-map.html',
  ]);
  for (const entry of seoCatalog.filter(
    entry => !entry.protected && !entry.template && !managedPaths.has(entry.path)
  )) {
    let html;
    try {
      html = await readFile(join(source, 'pages', entry.path.slice(1)), 'utf8');
    } catch (error) {
      if (error.code === 'ENOENT') continue;
      throw error;
    }
    await page(entry.path, html, entry.title, entry.description, undefined, entry.path, false);
  }
  // 索引與 HTML 全部成功才啟用此版本；不掃描 dist 或舊的 generation。
  const canonicalOrigin = snapshot.seo?.defaults?.canonicalOrigin || snapshot.origin;
  for (const [name, xml] of buildSitemaps(indexEntries, canonicalOrigin))
    await writeFile(join(root, name), xml);
  await writeFile(join(root, 'robots.txt'), robotsText(canonicalOrigin));
  // 寫完所有頁面最後才放快照，避免半成品被當成可重用版本。
  await writeFile(join(root, '.snapshot.json'), JSON.stringify(snapshot));
}
// 同一 volume 的 symlink rename 是啟用點；PHP 與 Nginx 都讀 current。
await realpath(root);
const temporary = join(output, `.current-${process.pid}`);
await rm(temporary, { force: true });
await symlink(root, temporary);
await rename(temporary, join(output, 'current'));
console.log('Publication activated.');
