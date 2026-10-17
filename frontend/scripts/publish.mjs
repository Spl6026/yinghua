// [E-12／E-18] 由 PHP CLI／API 交付已驗證資料；只在前端產生 HTML。
// 每個 generation 含完整公開投影與頁面，成功寫完後才原子切換 current。
import { readFile, writeFile, mkdir, rename, symlink, rm, realpath } from 'node:fs/promises';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { openingHoursLines } from '../assets/js/core/opening-hours.js';

const source = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const output = resolve(process.argv[2] || '/var/www/publication');
let input = '';
for await (const chunk of process.stdin) {
  input += chunk;
  if (input.length > 32 * 1024 * 1024) throw new Error('Publication too large');
}
const snapshot = JSON.parse(input);
if (!/^[a-f0-9]{64}$/.test(snapshot.generation) || !Array.isArray(snapshot.items) || !/^https?:\/\//.test(snapshot.origin)) throw new Error('Invalid publication');
const e = value => String(value ?? '').replace(/[&<>"']/g, character => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[character]);
const url = item => item.isMuseum ? '/museum.html' : `/places/${item.slug}.html`;
const json = value => JSON.stringify(value).replaceAll('<', '\\u003c').replaceAll('>', '\\u003e').replaceAll('&', '\\u0026');
// [U-01／E-18] 資料庫保存 UTC，網站日期依台北時區顯示。
const localDate = value => new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Taipei', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date(value));
const image = (item, className) => `<div class="${className}" data-image-url="${e(item.image)}" role="img" aria-label="${e(item.imageAlt || item.name)}" style="background-image:url(&quot;${e(item.image || '/assets/image-placeholder.svg')}&quot;)"></div>`;
const card = item => `<article class="place-card reveal in-view">${image(item, 'place-image').replace('</div>', `<span class="place-type">${e(item.typeLabel || item.category)}</span><button type="button" class="favorite-button" aria-label="收藏${e(item.name)}" aria-pressed="false" data-favorite="${e(item.id)}">♡</button></div>`)}<div class="place-body"><span class="place-area">${e(item.district)}　/　${e(item.recommendationLabel || '在地推薦')}</span><h3>${e(item.name)}</h3><p>${e(item.summary)}</p><div class="place-details"><span>◷ ${e(item.hours || '依店家公告')}</span><span class="place-actions"><a href="${e(url(item))}">看介紹 ↗</a><button type="button" data-add-stop="${e(item.id)}">＋ 行程</button></span></div></div></article>`;
const templates = Object.fromEntries(await Promise.all(['site-header','site-footer','admin-sidebar','admin-topbar','dialog'].map(async name => [name.replaceAll('-','_').toUpperCase(),await readFile(join(source,'templates',name+'.html'),'utf8')])));
const root = join(output, 'generations', snapshot.generation);
await mkdir(root, { recursive: true });
// 已完成的 generation 不覆寫，避免正在讀取的版本遭原地修改。
let existing = false;
try { existing = JSON.parse(await readFile(join(root,'.snapshot.json'),'utf8')).generation === snapshot.generation; } catch {}

async function page(path, html, title, description, item) {
  const canonical = snapshot.origin + path;
  const imageUrl = item?.image ? new URL(item.image, snapshot.origin).href : snapshot.origin + '/assets/image-placeholder.svg';
  html = html.replace(/<title>[\s\S]*?<\/title>/, '').replace(/<meta\b[^>]*(?:name="(?:description|keywords|robots|googlebot|twitter:[^"]+)"|property="og:[^"]+")[^>]*>/g,'')
    .replace(/<link\b[^>]*rel="canonical"[^>]*>/g,'').replace(/<script type="application\/ld\+json">[\s\S]*?<\/script>/g,'');
  html = html.replace('</head>', `<title>${e(title)}</title><meta name="description" content="${e(description)}"><meta name="keywords" content="新竹,映畫竹塹,${e(item?.category || '城市指南')}"><meta name="robots" content="index,follow"><link rel="canonical" href="${e(canonical)}"><meta property="og:title" content="${e(title)}"><meta property="og:description" content="${e(description)}"><meta property="og:url" content="${e(canonical)}"><meta property="og:type" content="${item ? 'article' : 'website'}"><meta property="og:site_name" content="映畫竹塹"><meta property="og:locale" content="zh_TW"><meta property="og:image" content="${e(imageUrl)}"><meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="${e(title)}"><meta name="twitter:description" content="${e(description)}"><meta name="twitter:image" content="${e(imageUrl)}"><noscript><style>.reveal,.image-reveal{opacity:1!important;transform:none!important}</style></noscript></head>`);
  html = html.replace('<body>', '<body data-managed-attractions="true">');
  html = html.replace('</head>', '<link rel="stylesheet" href="/assets/css/phase3.css"></head>');
  html = html.replace(/\{\{([A-Z_]+)\}\}/g, (_,name) => {
    const template = templates[name];
    if (template === undefined) throw new Error('Unknown template');
    return name === 'SITE_HEADER' && path !== '/index.html' && path !== '/food.html' ? template.replace('href="/explore.html"','class="active" aria-current="page" href="/explore.html"') : template;
  });
  html = html.replace('</body>', `<script type="application/json" id="attraction-publication">${json({generation:snapshot.generation,items:snapshot.items})}</script><script type="module" src="/assets/js/pages/attractions.js"></script></body>`);
  const target = join(root,path.slice(1));
  await mkdir(dirname(target),{recursive:true});
  await writeFile(target,html);
}

if (!existing) {
  for (const kind of ['explore','food']) {
    let html = await readFile(join(source,'pages',kind+'.html'),'utf8');
    const items = snapshot.items.filter(item => kind === 'explore' || item.category === '美食');
    html = html.replace(/(<div class="place-grid" id="place-grid">)[\s\S]*?(<\/div>)/, `$1${items.slice(0,12).map(card).join('') || '<p class="empty-results">目前沒有已發布的地點。</p>'}$2`);
    html = html.replace(/data-category="([^"]+)"([\s\S]*?)<span>\d+<\/span>/g, (_,category,rest) => `data-category="${category}"${rest}<span>${String(snapshot.items.filter(item=>category==='all'||item.category===category).length).padStart(2,'0')}</span>`);
    await page(`/${kind}.html`,html,kind==='explore'?'城市散策｜映畫竹塹':'新竹美食｜映畫竹塹',kind==='explore'?'探索新竹景點、文化場館與在地店家。':'探索新竹美食與巷弄店家。');
  }
  const detailTemplate = await readFile(join(source,'pages','museum.html'),'utf8');
  for (const item of snapshot.items) {
    if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(item.slug)) throw new Error('Invalid content slug');
    const metadata = [item.address, item.transport && `交通：${item.transport}`].filter(Boolean).map(text=>`<p>${e(text).replaceAll('\n','<br>')}</p>`).join('') + `<div class="opening-hours-detail"><h2>營業時間</h2>${openingHoursLines(item.openingHours,item.hours).map(line=>`<p>${e(line).replaceAll('\n','<br>')}</p>`).join('')}${item.openingHours ? '<small>跨日時段歸開始日；實際營業以店家公告為準。</small>' : ''}</div>`;
    const telephone = item.phone && /^tel:[+0-9]+(?:;ext=[0-9]+)?$/.test(item.phoneUrl || '')
      ? `<p>電話：<a href="${e(item.phoneUrl)}">${e(item.phone)}</a></p>` : '';
    const links = [[item.mapUrl,'在 Google 地圖開啟'],[item.websiteUrl,'官方網站']].filter(([href])=>href).map(([href,label])=>`<a class="text-link" href="${e(href)}" target="_blank" rel="noopener noreferrer">${label} ↗</a>`).join('　');
    const body = `<section class="section-pad detail-page">${image(item,'detail-cover')}<div class="detail-body"><p class="eyebrow">CITY STORY / DETAIL</p><h1>${e(item.name)}</h1><p class="detail-lead">${e(item.summary)}</p>${item.description.split(/\n\s*\n/).map(text=>`<p>${e(text).replaceAll('\n','<br>')}</p>`).join('')}${metadata}${telephone}<div class="detail-meta">${e(item.typeLabel || item.category)}・${e(item.district)}</div>${links}<div class="detail-actions"><a class="button button-dark" href="/explore.html">回到城市散策 ↗</a><button type="button" class="text-link" data-add-stop="${e(item.id)}">安排進旅行 <span>→</span></button><button type="button" class="text-link" data-favorite="${e(item.id)}" aria-pressed="false">收藏此景點 ♡</button></div><small>更新日期：${e(localDate(item.updatedAt))}</small></div></section>`;
    const html = detailTemplate.replace(/<section class="section-pad detail-page">[\s\S]*?<\/section>/,body);
    await page(url(item),html,item.seoTitle || `${item.name}｜映畫竹塹`,item.seoDescription || item.summary,item);
  }
  if (!snapshot.items.some(item=>item.isMuseum)) {
    await page('/museum.html',detailTemplate.replace(/<section class="section-pad detail-page">[\s\S]*?<\/section>/,'<section class="section-pad detail-page"><h1>博物館資訊更新中</h1><p>目前沒有已發布的博物館介紹。</p><a href="/explore.html">回到城市散策</a></section>'),'博物館資訊更新中｜映畫竹塹','博物館介紹目前更新中。');
  }
  let home = await readFile(join(source,'pages','index.html'),'utf8');
  const museum = snapshot.items.find(item=>item.isMuseum);
  const featured = snapshot.items.find(item=>item.featured) || museum;
  if (museum) home = home.replace(/(<div class="intro-copy reveal">)[\s\S]*?(<div class="museum-facts">)/,`$1<p>${e(museum.summary)}</p><p>${e(museum.description)}</p>$2`);
  if (featured) {
    home = home.replace(/<a class="museum-feature image-reveal"[^>]*>/,`<a class="museum-feature image-reveal" href="${e(url(featured))}" aria-label="${e(featured.name)}">`)
      .replace(/<div class="museum-photo"[^>]*>/,`<div class="museum-photo" data-image-url="${e(featured.image)}" role="img" aria-label="${e(featured.imageAlt || featured.name)}" style="background-image:url(&quot;${e(featured.image)}&quot;)">`)
      .replace(/(<div class="museum-feature-label">)[\s\S]*?(<\/div>)/,`$1<span>${e(featured.category)}</span><strong>${e(featured.name)}</strong>$2`);
  } else {
    home = home.replace(/<a class="museum-feature image-reveal"[\s\S]*?<\/a>/,'<div class="museum-feature"><p>目前沒有已發布的精選景點。</p></div>');
  }
  if (!museum) home = home.replace(/<div class="intro-copy reveal">[\s\S]*?<\/div>\s*<\/div>\s*<\/div>/,'<div class="intro-copy reveal"><p>博物館參觀資訊更新中，請稍後再訪。</p></div>');
  await page('/index.html',home,'映畫竹塹｜新竹城市影像與旅行指南','從新竹市影像博物館出發，探索城市散策、在地美食與新竹旅行。',featured);
  // 寫完所有頁面最後才放快照，避免半成品被當成可重用版本。
  await writeFile(join(root,'.snapshot.json'),JSON.stringify(snapshot));
}
// 同一 volume 的 symlink rename 是啟用點；PHP 與 Nginx 都讀 current。
await realpath(root);
const temporary = join(output,`.current-${process.pid}`);
await rm(temporary,{force:true});
await symlink(root,temporary);
await rename(temporary,join(output,'current'));
console.log('Publication activated.');
