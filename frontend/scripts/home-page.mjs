// 首頁由多個區塊組成：博物館介紹、精選景點、本週電影與最新消息，各區塊只在快照有該類內容時替換。
import { readFile } from 'node:fs/promises';
import { join } from 'node:path';
import { escapeHtml, jsonForHtml } from '../assets/js/core/content-view.js';
import { filmCards } from '../assets/js/core/film-view.js';
import { homeNewsRows } from '../assets/js/core/home-view.js';
import { mustReplace } from './html-edit.mjs';
import { attractionUrl as url } from './public-attractions.mjs';

export async function buildHomePage(source, snapshot, { museum, featured, filmItems, weekLabel }) {
  let home = await readFile(join(source, 'pages', 'index.html'), 'utf8');
  if (museum)
    home = mustReplace(
      home,
      /(<div class="intro-copy reveal">)[\s\S]*?(<div class="museum-facts">)/,
      (_, open, close) =>
        open +
        `<p>${escapeHtml(museum.summary)}</p>${museum.description ? `<p>${escapeHtml(museum.description)}</p>` : `<div><p>尚未提供景點介紹。</p></div>`}` +
        close
    );
  if (featured) {
    home = mustReplace(
      home,
      /<a class="museum-feature image-reveal"[^>]*>/,
      () =>
        `<a class="museum-feature image-reveal" href="${escapeHtml(url(featured))}" aria-label="${escapeHtml(featured.name)}">`
    );
    home = mustReplace(
      home,
      /<div class="museum-photo"[^>]*>/,
      () =>
        `<div class="museum-photo" data-image-url="${escapeHtml(featured.image)}" role="img" aria-label="${escapeHtml(featured.imageAlt || featured.name)}" style="background-image:url(&quot;${escapeHtml(featured.image)}&quot;)">`
    );
    home = mustReplace(
      home,
      /(<div class="museum-feature-label">)[\s\S]*?(<\/div>)/,
      (_, open, close) =>
        open +
        `<span>${escapeHtml(featured.category)}</span><strong>${escapeHtml(featured.name)}</strong>` +
        close
    );
  } else {
    home = mustReplace(
      home,
      /<a class="museum-feature image-reveal"[\s\S]*?<\/a>/,
      '<div class="museum-feature"><p>精選景點即將更新。</p></div>'
    );
  }
  if (!museum)
    home = mustReplace(
      home,
      /<div class="intro-copy reveal">[\s\S]*?<\/div>\s*<\/div>\s*<\/div>/,
      '<div class="intro-copy reveal"><p>博物館參觀資訊更新中，請稍後再訪。</p></div>'
    );
  if (Array.isArray(snapshot.films)) {
    home = mustReplace(
      home,
      /(<div class="screening-grid">)[\s\S]*?(<\/div>\s*<p class="demo-note">)/,
      (_, open, close) =>
        open +
        filmCards(snapshot.home?.films || filmItems, snapshot.home?.filmTotal ?? filmItems.length) +
        close
    );
    home = mustReplace(home, '10.10 — 10.16', weekLabel);
    home = mustReplace(
      home,
      '映演內容為示意資料，實際場次請以影像博物館公告為準。',
      '實際放映資訊請以影像博物館最新公告為準。'
    );
  }
  if (Array.isArray(snapshot.news))
    home = mustReplace(
      home,
      /(<div class="event-list">)[\s\S]*?<\/div>/,
      (_, open) => open + homeNewsRows(snapshot.home?.news || snapshot.news.slice(0, 3)) + '</div>'
    );
  if (snapshot.home)
    home = mustReplace(
      home,
      '</body>',
      () =>
        `<script type="application/json" id="home-publication">${jsonForHtml({ ...snapshot.home, generation: snapshot.generation })}</script></body>`
    );
  return home;
}
