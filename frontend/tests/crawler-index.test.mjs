import test from 'node:test';
import assert from 'node:assert/strict';
import { mkdtemp, readFile, rm, realpath } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { crawlerEntry, buildSitemaps, robotsText } from '../scripts/crawler-index.mjs';
import { resolveSeo } from '../assets/js/core/seo-resolver.js';

test('只收成功發布且可索引canonical，lastmod不採build時間', () => {
  const context = {
    path: '/news/a.html',
    published: true,
    origin: 'https://example.test',
    item: { updatedAt: '2026-10-20T00:00:00Z' },
    seo: { updatedAt: '2026-10-21 00:00:00' },
  };
  assert.deepEqual(crawlerEntry(context, resolveSeo(context)), {
    url: 'https://example.test/news/a.html',
    lastmod: '2026-10-21T00:00:00.000Z',
  });
  const fixed = { ...context, path: '/index.html' };
  assert.ok(!crawlerEntry(fixed, resolveSeo(fixed)).lastmod);
  for (const change of [
    { published: false },
    { unavailable: true },
    { path: '/planner.html' },
    { path: '/search.html' },
    { seo: { defaults: { robots: 'noindex,follow' } } },
  ]) {
    const input = { ...context, ...change };
    assert.equal(crawlerEntry(input, resolveSeo(input)), null);
  }
});

test('XML query安全編碼、去重、排序及穩定修改日', () => {
  const entries = [
    { url: 'https://example.test/events.html?type=city&page=2', lastmod: '2026-10-21T00:00:00Z' },
    { url: 'https://example.test/index.html' },
    { url: 'https://example.test/index.html' },
  ];
  const xml = buildSitemaps(entries, 'https://example.test').get('sitemap.xml');
  assert.equal((xml.match(/<url>/g) || []).length, 2);
  assert.match(xml, /type=city&amp;page=2/);
  assert.match(xml, /<lastmod>2026-10-21T00:00:00.000Z/);
  assert.equal(xml, buildSitemaps([...entries].reverse(), 'https://example.test').get('sitemap.xml'));
  assert.throws(
    () => buildSitemaps([{ url: 'https://other.test/a.html' }], 'https://example.test'),
    /Cross-origin/
  );
});

test('超過URL或UTF8位元組容量產完整分片，不截斷', () => {
  const entries = Array.from({ length: 5 }, (_, i) => ({ url: 'https://example.test/' + i + '.html' }));
  const maps = buildSitemaps(entries, 'https://example.test', { maxUrls: 2 });
  assert.equal(maps.size, 4);
  assert.match(maps.get('sitemap.xml'), /<sitemapindex/);
  assert.equal(
    [...maps]
      .filter(([name]) => name !== 'sitemap.xml')
      .reduce((total, [, xml]) => total + (xml.match(/<url>/g) || []).length, 0),
    5
  );
  const small = buildSitemaps(entries, 'https://example.test', { maxBytes: 245 });
  for (const [name, xml] of small) if (name !== 'sitemap.xml') assert.ok(Buffer.byteLength(xml) <= 245);
  assert.throws(() => buildSitemaps(entries, 'https://example.test', { maxUrls: 50001 }), /capacity/);
});

test('robots僅排除admin並使用設定origin，不靠Host或遮住noindex頁', () => {
  const text = robotsText('https://example.test');
  assert.equal(text, 'User-agent: *\nDisallow: /admin/\nSitemap: https://example.test/sitemap.xml\n');
  assert.throws(() => robotsText('https://example.test/path'), /Invalid/);
  assert.throws(() => robotsText('https://user:password@example.test'), /Invalid/);
  assert.throws(() => buildSitemaps([], 'https://example.test/path'), /Invalid/);
});

test('實際50,000 URL標準邊界保持每份容量並保留第50,001筆', () => {
  const entries = Array.from({ length: 50001 }, (_, i) => ({
    url: 'https://example.test/places/' + String(i).padStart(5, '0') + '.html',
  }));
  const files = buildSitemaps(entries, 'https://example.test');
  assert.equal(files.size, 3);
  assert.equal((files.get('sitemap-1.xml').match(/<url>/g) || []).length, 50000);
  assert.equal((files.get('sitemap-2.xml').match(/<url>/g) || []).length, 1);
  assert.match(files.get('sitemap-2.xml'), /50000.html/);
});

const renderer = fileURLToPath(new URL('../scripts/publish.mjs', import.meta.url));
const place = {
  id: '03000000-0000-4000-8000-000000000001',
  slug: 'real-place',
  name: '真景點',
  category: '景點',
  summary: '真摘要',
  description: '真介紹',
  address: '新竹市',
  image: '',
  updatedAt: '2026-10-20T12:00:00Z',
};
const snapshot = (letter, items = [place], seo = {}) => ({
  origin: 'https://example.test',
  generation: letter.repeat(64),
  items,
  aliases: {},
  seo,
});
const publish = (dir, data) =>
  spawnSync(process.execPath, [renderer, dir], { input: JSON.stringify(data), encoding: 'utf8' });

test('真正renderer同版產JSON-LD、XML、robots，排除佔位／noindex／舊slug', async () => {
  const dir = await mkdtemp(join(tmpdir(), 'spl-index-'));
  try {
    assert.equal(publish(dir, snapshot('a')).status, 0);
    let xml = await readFile(join(dir, 'current/sitemap.xml'), 'utf8');
    assert.match(xml, /real-place.html/);
    assert.doesNotMatch(xml, /museum.html|planner.html|search.html|_content_pages|admin\//);
    const html = await readFile(join(dir, 'current/places/real-place.html'), 'utf8');
    const graph = JSON.parse(html.match(/<script type="application\/ld\+json">([\s\S]*?)<\/script>/)[1]);
    assert.equal(graph['@graph'].at(-1).name, place.name);
    assert.equal((html.match(/application\/ld\+json/g) || []).length, 1);
    assert.match(
      await readFile(join(dir, 'current/robots.txt'), 'utf8'),
      /Sitemap: https:\/\/example.test\/sitemap.xml/
    );
    const noindex = {
      overrides: [{ target: { type: 'attraction', id: place.id }, robots: 'noindex,follow' }],
    };
    assert.equal(publish(dir, snapshot('b', [place], noindex)).status, 0);
    xml = await readFile(join(dir, 'current/sitemap.xml'), 'utf8');
    assert.doesNotMatch(xml, /real-place.html/);
    assert.doesNotMatch(
      await readFile(join(dir, 'current/places/real-place.html'), 'utf8'),
      /application\/ld\+json/
    );
    assert.equal(publish(dir, snapshot('c', [{ ...place, slug: 'new-place' }])).status, 0);
    xml = await readFile(join(dir, 'current/sitemap.xml'), 'utf8');
    assert.match(xml, /new-place.html/);
    assert.doesNotMatch(xml, /real-place.html/);
    const before = await realpath(join(dir, 'current'));
    assert.notEqual(publish(dir, snapshot('d', [{ ...place, slug: '../invalid' }])).status, 0);
    assert.equal(await realpath(join(dir, 'current')), before);
    assert.match(await readFile(join(dir, 'current/sitemap.xml'), 'utf8'), /new-place.html/);
    assert.equal(publish(dir, snapshot('e', [])).status, 0);
    assert.doesNotMatch(await readFile(join(dir, 'current/sitemap.xml'), 'utf8'), /new-place.html/);
  } finally {
    await rm(dir, { recursive: true, force: true });
  }
});
