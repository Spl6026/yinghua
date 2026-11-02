import test from 'node:test';
import assert from 'node:assert/strict';
import { contentNeighbors } from '../assets/js/core/content-neighbors.js';

test('沒有相鄰內容不產生空導覽，單側保留正確標籤', () => {
  assert.equal(contentNeighbors({ previous: null, next: null }), '');
  const html = contentNeighbors({
    previous: null,
    next: { url: '/news/test.html', title: '下一則', image: '' },
  });
  assert.match(html, /下一篇：下一則/);
  assert.doesNotMatch(html, /上一篇/);
  assert.match(html, /alt=""/);
});
test('卡片的文字及圖片編碼，拒絕外部或腳本導覽', () => {
  assert.equal(contentNeighbors({ next: { url: 'javascript:alert(1)', title: '危險' } }), '');
  assert.equal(contentNeighbors({ next: { url: 'https://example.com/', title: '跨站' } }), '');
  const html = contentNeighbors({
    next: {
      url: '/offers/test.html',
      title: '<img onerror=alert(1)>',
      image: 'https://example.com/" onerror="x',
    },
  });
  assert.match(html, /&lt;img onerror=alert\(1\)&gt;/);
  assert.doesNotMatch(html, /src="[^"]*" onerror=/);
});
test('博物館及過期優惠仍可使用真實頁面導覽', () => {
  const html = contentNeighbors({
    previous: { url: '/museum.html', title: '館舍' },
    next: { url: '/offers/expired.html', title: '歷史優惠' },
  });
  assert.match(html, /href="\/museum.html"/);
  assert.match(html, /href="\/offers\/expired.html"/);
});
