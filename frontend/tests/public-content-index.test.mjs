import test from 'node:test';
import assert from 'node:assert/strict';
import { renderPublicContentIndex } from '../scripts/public-content-index.mjs';
import { normalizedIndexQuery } from '../assets/js/pages/public-index.js';

test('索引保留所有已發布電影及歷史優惠，不依本週或active濾掉詳情', () => {
  const html = renderPublicContentIndex({
    items: [{ slug: 'museum', name: '館舍', isMuseum: true }],
    films: [{ slug: 'old-film', title: '非當週電影', screenings: [] }],
    news: [],
    offers: [{ slug: 'old-offer', title: '歷史優惠', active: false }],
  });
  assert.match(html, /href="\/museum.html"/);
  assert.match(html, /href="\/films\/old-film.html"/);
  assert.match(html, /href="\/offers\/old-offer.html"/);
  assert.match(html, /已結束或尚未開始/);
});
test('標題只當文字；不允許 slug 越界或產生私人網址', () => {
  const html = renderPublicContentIndex({
    films: [{ slug: 'safe-film', title: '<img src=x onerror=alert(1)>' }],
  });
  assert.equal(html.includes('<img'), false);
  assert.match(html, /&lt;img/);
  assert.throws(() => renderPublicContentIndex({ films: [{ slug: '../admin', title: 'x' }] }), /Invalid/);
});
test('名稱尋找正規化全形英數與大小寫，不把查詢解讀成HTML', () => {
  assert.equal(normalizedIndexQuery(' ＦＩＬＭ '), 'film');
  assert.equal(normalizedIndexQuery('<SCRIPT>'), '<script>');
});
