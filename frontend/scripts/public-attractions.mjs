// 城市散策、美食列表與每個景點的詳情頁；沒有標示博物館的景點時，博物館頁顯示更新中。
import { readFile } from 'node:fs/promises';
import { join } from 'node:path';
import { escapeHtml } from '../assets/js/core/content-view.js';
import { openingHoursLines } from '../assets/js/core/opening-hours.js';
import { contentDetailUrl, contentKind } from '../assets/js/core/content-kinds.js';
import { mustReplace } from './html-edit.mjs';

export const attractionUrl = item => contentDetailUrl(contentKind('attraction'), item);
const url = attractionUrl;
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
  `<div class="${className}" data-image-url="${escapeHtml(item.image)}" role="${className === 'place-image' ? 'group' : 'img'}" aria-label="${escapeHtml(item.imageAlt || item.name)}" style="background-image:url(&quot;${escapeHtml(item.image || '/assets/image-placeholder.svg')}&quot;)"></div>`;
// 選填文字不補預設內容；與瀏覽器端卡片一致，省略空段落與多餘的分隔符號。
const card = item =>
  `<article class="place-card reveal in-view">${image(item, 'place-image').replace('</div>', () => `<span class="place-type">${escapeHtml(item.typeLabel || item.category)}</span><button type="button" class="favorite-button" aria-label="收藏${escapeHtml(item.name)}" aria-pressed="false" data-favorite="${escapeHtml(item.id)}">♡</button></div>`)}<div class="place-body"><span class="place-area">${[item.district, item.recommendationLabel || '在地推薦'].filter(Boolean).map(escapeHtml).join('　/　')}</span><h3>${escapeHtml(item.name)}</h3>${item.summary ? `<p>${escapeHtml(item.summary)}</p>` : ''}<div class="place-details"><span>◷ ${escapeHtml(item.hours || '依店家公告')}</span><span class="place-actions"><a href="${escapeHtml(url(item))}">看介紹 ↗</a><button type="button" data-add-stop="${escapeHtml(item.id)}">＋ 行程</button></span></div></div></article>`;

export async function publishAttractions(source, snapshot, page) {
  for (const kind of ['explore', 'food']) {
    let html = await readFile(join(source, 'pages', kind + '.html'), 'utf8');
    const items = snapshot.items.filter(item => kind === 'explore' || item.category === '美食');
    html = mustReplace(
      html,
      /(<div class="place-grid" id="place-grid">)[\s\S]*?(<\/div>)/,
      (_, open, close) =>
        open +
        (items.slice(0, 12).map(card).join('') || '<p class="empty-results">目前沒有地點資訊。</p>') +
        close
    );
    // 只有城市散策頁的分類分頁帶有筆數，美食頁的分類沒有，所以這裡允許找不到。
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
        .map(text => `<p>${escapeHtml(text).replaceAll('\n', '<br>')}</p>`)
        .join('') +
      `<div class="opening-hours-detail"><h2>營業時間</h2>${openingHoursLines(item.openingHours, item.hours)
        .map(line => `<p>${escapeHtml(line).replaceAll('\n', '<br>')}</p>`)
        .join(
          ''
        )}${item.openingHours ? '<small>跨日時段歸開始日；實際營業以店家公告為準。</small>' : ''}</div>`;
    const telephone =
      item.phone && /^tel:[+0-9]+(?:;ext=[0-9]+)?$/.test(item.phoneUrl || '')
        ? `<p>電話：<a href="${escapeHtml(item.phoneUrl)}">${escapeHtml(item.phone)}</a></p>`
        : '';
    const links = [
      [item.mapUrl, '在 Google 地圖開啟'],
      [item.websiteUrl, '官方網站'],
    ]
      .filter(([href]) => href)
      .map(
        ([href, label]) =>
          `<a class="text-link" href="${escapeHtml(href)}" target="_blank" rel="noopener noreferrer">${label} ↗</a>`
      )
      .join('　');
    const body = `<section class="section-pad detail-page">${image(item, 'detail-cover')}<div class="detail-body"><p class="eyebrow">CITY STORY / DETAIL</p><h1>${escapeHtml(item.name)}</h1>${item.summary ? `<p class="detail-lead">${escapeHtml(item.summary)}</p>` : ''}${item.description
      .split(/\n\s*\n/)
      .filter(text => text.trim())
      .map(text => `<p>${escapeHtml(text).replaceAll('\n', '<br>')}</p>`)
      .join(
        ''
      )}${!item.description ? `<div class="attraction-description-empty"><p>尚未提供景點介紹。</p></div>` : ''}${metadata}${telephone}<div class="detail-meta">${[item.typeLabel || item.category, item.district].filter(Boolean).map(escapeHtml).join('・')}</div>${links}<div class="detail-actions"><a class="button button-dark" href="/explore.html">回到城市散策 ↗</a><button type="button" class="text-link" data-add-stop="${escapeHtml(item.id)}">安排進旅行 <span>→</span></button><button type="button" class="text-link" data-favorite="${escapeHtml(item.id)}" aria-pressed="false">收藏此景點 ♡</button></div><small>更新日期：${escapeHtml(localDate(item.updatedAt))}</small></div></section>`;
    const html = mustReplace(
      detailTemplate,
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
      mustReplace(
        detailTemplate,
        /<section class="section-pad detail-page">[\s\S]*?<\/section>/,
        '<section class="section-pad detail-page"><h1>博物館資訊更新中</h1><p>博物館介紹即將更新。</p><a href="/explore.html">回到城市散策</a></section>'
      ),
      '博物館資訊更新中｜映畫竹塹',
      '博物館介紹目前更新中。'
    );
  }
}
