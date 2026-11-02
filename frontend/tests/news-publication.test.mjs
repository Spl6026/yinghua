import test from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { mkdtemp, readFile, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { newsCards, newsDetail } from '../assets/js/core/news-view.js';

const script = new URL('../scripts/publish.mjs', import.meta.url);
const item = {
  id: 'news-test',
  slug: 'news-test',
  title: '消息 $& <script>title</script>',
  summary: '摘要 $1 </script>',
  type: 'event',
  body: '<p><strong>已淨化正文</strong></p>',
  eventStartAt: null,
  eventEndAt: null,
  eventLabel: '',
  eventLocation: '',
  image: '',
  imageAlt: '',
  publishAt: '2026-12-31T16:30:00+00:00',
  url: '/news/news-test.html',
  seoTitle: '',
  seoDescription: '',
};

test('消息文字編碼、跨年日期與安全正文各自呈現', () => {
  assert.match(newsCards([item]), /2027/);
  assert.match(newsCards([item]), /消息 \$&amp; &lt;script&gt;title&lt;\/script&gt;/);
  const html = newsDetail(item);
  assert.match(html, /<strong>已淨化正文<\/strong>/);
  assert.doesNotMatch(html, /<script>title<\/script>/);
  assert.doesNotMatch(html, /1970|undefined|null/);
});

test('生成各分類深頁、canonical、可讀正文與安全 JSON，不回填原型', async () => {
  const directory = await mkdtemp(join(tmpdir(), 'spl-news-'));
  const rows = Array.from({ length: 21 }, (_, n) => ({
    ...item,
    id: 'news-' + n,
    slug: 'news-' + n,
    title: item.title + ' ' + n,
    url: '/news/news-' + n + '.html',
  }));
  const publish = (generation, news) =>
    spawnSync(process.execPath, [script.pathname, directory], {
      encoding: 'utf8',
      input: JSON.stringify({
        origin: 'https://example.test',
        generation: generation.repeat(64),
        items: [],
        aliases: {},
        news,
        newsAliases: {},
      }),
    });
  try {
    const result = publish('c', rows);
    assert.equal(result.status, 0, result.stderr);
    const html = await readFile(join(directory, 'current/_content_pages/events/all/2.html'), 'utf8');
    const state = JSON.parse(html.match(/id="public-news-state">([\s\S]*?)<\/script>/)[1]);
    assert.equal(state.items.length, 1);
    assert.equal(state.page, 2);
    assert.equal(state.items[0].id, 'news-20');
    assert.match(html, /rel="canonical" href="https:\/\/example.test\/events.html\?page=2"/);
    assert.match(html, /id="news-list"/);
    assert.match(html, /body data-managed-attractions="true" data-managed-news="true"/);
    assert.doesNotMatch(html, /風城慢慢市集：帶一張空白地圖/);
    assert.equal(state.items[0].summary, item.summary);
    const detail = await readFile(join(directory, 'current/news/news-0.html'), 'utf8');
    assert.match(detail, /<strong>已淨化正文<\/strong>/);
    assert.doesNotMatch(detail, /article-neighbor-grid/);
    assert.equal(publish('d', []).status, 0);
    await assert.rejects(readFile(join(directory, 'current/news/news-0.html'), 'utf8'), { code: 'ENOENT' });
    assert.match(
      await readFile(join(directory, 'current/_content_pages/events/all/1.html'), 'utf8'),
      /目前沒有符合分類的消息/
    );
  } finally {
    await rm(directory, { recursive: true, force: true });
  }
});
