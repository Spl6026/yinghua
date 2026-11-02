import test from 'node:test';
import assert from 'node:assert/strict';
import { searchCard } from '../assets/js/components/search-card.js';
class Node {
  constructor(tag) {
    this.tag = tag;
    this.children = [];
    this.attributes = {};
  }
  setAttribute(k, v) {
    this.attributes[k] = v;
  }
  append(...nodes) {
    this.children.push(...nodes);
  }
}
globalThis.document = { createElement: tag => new Node(tag) };
test('搜尋卡片保留惡意字串為文字，連結可辨並只到相同類型詳情', () => {
  const title = '<img src=x onerror=alert(1)>';
  const card = searchCard(
    { title, summary: '<script>unsafe</script>', type: 'news', url: '/news/safe.html' },
    '消息'
  );
  assert.equal(card.children[1].textContent, title);
  assert.equal(card.children[2].textContent, '<script>unsafe</script>');
  const link = card.children[3];
  assert.equal(link.tag, 'a');
  assert.equal(link.attributes.href, '/news/safe.html');
  assert.equal(link.attributes['aria-label'], '閱讀詳情：' + title);
  assert.equal(link.children[0].attributes['aria-hidden'], 'true');
});
test('不建立外部／javascript／不相同主題或內部路徑的連結；館舍維持實際網址', () => {
  for (const url of [
    'javascript:alert(1)',
    '//evil.test',
    'https://evil.test',
    '/_content_pages/news/a.html',
    '/places/wrong.html',
  ])
    assert.equal(searchCard({ type: 'news', url, title: '名稱', summary: '' }, '消息').children.length, 3);
  assert.equal(
    searchCard({ type: 'place', url: '/museum.html', title: '館舍', summary: '' }, '景點').children[3]
      .attributes.href,
    '/museum.html'
  );
});
