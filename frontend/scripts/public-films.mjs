// 本週映演頁與每部電影的詳情頁；放映週期與博物館參觀資訊與首頁共用同一份快照。
import { readFile } from 'node:fs/promises';
import { join } from 'node:path';
import { escapeHtml } from '../assets/js/core/content-view.js';
import { openingHoursLines } from '../assets/js/core/opening-hours.js';
import { filmRows, filmDetail } from '../assets/js/core/film-view.js';
import { mustReplace } from './html-edit.mjs';

export async function publishFilms(source, snapshot, page, { filmItems, weekLabel, museum }) {
  let cinema = await readFile(join(source, 'pages/cinema.html'), 'utf8');
  cinema = mustReplace(
    cinema,
    /(<div class="cinema-list" id="cinema-list">)[\s\S]*?(<\/div>)/,
    (_, open, close) => open + filmRows(filmItems.slice(0, 20)) + close
  );
  // 以穩定的 id 定位並更新放映週期，保留英文的語言標示，不依賴原型的純文字內容。
  cinema = mustReplace(
    cinema,
    /(<p\b[^>]*\bid="film-period"[^>]*>)[\s\S]*?(<\/p>)/,
    (_, open, close) => `${open}<bdi lang="en">THIS WEEK</bdi> · ${escapeHtml(weekLabel)}${close}`
  );
  cinema = mustReplace(cinema, '※ 場次為示意資料', '實際放映資訊請以最新公告為準。');
  // 與館舍詳情共用同一份快照與週表，不另外保存開放時間。
  const visit = museum
    ? `<div class="museum-info"><div><p class="eyebrow">VISIT THE MUSEUM</p><h2>${escapeHtml(museum.name)}</h2><p>${[
        museum.address,
        ...openingHoursLines(museum.openingHours, museum.hours),
      ]
        .filter(Boolean)
        .map(text => escapeHtml(text).replaceAll('\n', '<br>'))
        .join(
          '<br>'
        )}</p></div><a class="text-link" href="/museum.html">查看館舍與交通資訊 <span>↗</span></a></div>`
    : '<div class="museum-info"><div><p class="eyebrow">VISIT THE MUSEUM</p><h2>博物館參觀資訊</h2><p>博物館介紹即將更新。</p></div><a class="text-link" href="/museum.html">查看館舍介紹 <span>↗</span></a></div>';
  cinema = mustReplace(cinema, /<!-- MUSEUM_VISIT_START -->[\s\S]*?<!-- MUSEUM_VISIT_END -->/, () => visit);
  await page(
    '/cinema.html',
    cinema,
    '本週映演｜映畫竹塹',
    '查看影像博物館本週放映日期、時間、電影介紹與海報。'
  );
  // 電影頁採用原型的電影專用頁殼，保留「本週映演」導覽，不沿用城市散策頁。
  const filmTemplate = await readFile(join(source, 'templates/film-detail.html'), 'utf8');
  for (const film of snapshot.films) {
    if (!/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(film.slug)) throw new Error('Invalid film slug');
    const html = mustReplace(
      filmTemplate,
      /<section class="section-pad detail-page">[\s\S]*?<\/section>/,
      () => filmDetail(film)
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
