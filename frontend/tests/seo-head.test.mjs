import test from 'node:test';
import assert from 'node:assert/strict';
import { resolveSeo, canonicalPath } from '../assets/js/core/seo-resolver.js';
import { renderSeoHead } from '../scripts/seo-head.mjs';

const catalog = [
  { path: '/food.html', title: '新竹美食｜映畫竹塹', description: '走訪巷弄美食。' },
  { path: '/planner.html', protected: true },
];
const seo = {
  defaults: {
    siteName: '測試網站',
    titleSuffix: '｜測試網站',
    canonicalOrigin: 'https://example.test',
    description: '共用摘要',
    ogImageUrl: 'https://example.test/public.jpg',
  },
  overrides: [],
};
test('逐欄繼承優先級，明確空關鍵字不被 fallback 覆蓋', () => {
  const overrides = [
    {
      target: { type: 'page', path: '/places/*.html' },
      title: '範本',
      description: '範本摘要',
      keywords: '範本詞',
    },
    { target: { type: 'attraction', id: 'a' }, title: '單筆覆寫', description: null, keywords: '' },
  ];
  const meta = resolveSeo({
    path: '/places/park.html',
    title: '公園｜映畫竹塹',
    description: '公園正文',
    item: { id: 'a', seoTitle: '內容標題', seoDescription: '內容摘要' },
    seo: { ...seo, overrides },
  });
  assert.equal(meta.title, '單筆覆寫');
  assert.equal(meta.description, '內容摘要');
  assert.equal(meta.keywords, '');
  assert.equal(meta.canonical, 'https://example.test/places/park.html');
});
test('後綴僅加一次，逐頁預設摘要保持主題而不複製全站摘要', () => {
  const meta = resolveSeo({ path: '/food.html', title: '新竹美食｜映畫竹塹', seo, catalog });
  assert.equal(meta.title, '新竹美食｜測試網站');
  assert.equal(meta.description, '走訪巷弄美食。');
  assert.equal(resolveSeo({ path: '/food.html', title: meta.title, seo, catalog }).title, meta.title);
});
test('追蹤及私人參數不進 canonical，分頁與分類保留必要參數', () => {
  assert.equal(canonicalPath('/?utm_source=x'), '/index.html');
  assert.equal(
    canonicalPath('/events.html?utm_source=x&page=2&type=event&token=secret'),
    '/events.html?type=event&page=2'
  );
  assert.equal(canonicalPath('/offers.html?page=1&type=bad'), '/offers.html');
  assert.equal(canonicalPath('/planner.html?place=private&returnTo=secret'), '/planner.html');
  const meta = resolveSeo({ path: '/events.html?page=2', title: '新竹活動', description: '活動消息', seo });
  assert.match(meta.title, /第 2 頁/);
  assert.match(meta.description, /第 2 頁/);
});
test('所有保護頁忽略索引覆寫，head 不含私人 query', () => {
  assert.equal(
    resolveSeo({ path: '/admin/admin-add.html', title: '新增管理員｜映畫竹塹後台', seo }).title,
    '新增管理員｜映畫竹塹後台'
  );
  for (const path of [
    '/admin/settings.html',
    '/login.html',
    '/register.html',
    '/search.html',
    '/planner.html',
    '/favorites.html',
    '/reset-password.html',
  ]) {
    const meta = resolveSeo({
      path: path + '?token=secret',
      title: '通用標題',
      seo: { ...seo, overrides: [{ target: { type: 'page', path }, robots: 'index,follow' }] },
      catalog,
    });
    assert.ok(meta.robots.startsWith('noindex'));
    assert.ok(!meta.canonical.includes('secret'));
  }
});
test('本站未公開媒體不因設定 URL 而取得公開存取', () => {
  const data = {
    path: '/food.html',
    seo: { ...seo, defaults: { ...seo.defaults, ogImageUrl: 'https://example.test/media/private.jpg' } },
  };
  assert.equal(resolveSeo(data).imageUrl, 'https://example.test/assets/image-placeholder.svg');
  assert.equal(
    resolveSeo({ ...data, publicImages: ['/media/private.jpg'] }).imageUrl,
    'https://example.test/media/private.jpg'
  );
});
test('真實 head 只留單一標籤，惡意字串被編碼，空驗證碼省略', () => {
  const html = renderSeoHead(
    '<html><head><title>舊標題</title><meta name="robots" content="index"><meta property="og:title" content="old"></head></html>',
    {
      path: '/food.html',
      title: '主題',
      seo: {
        ...seo,
        defaults: { ...seo.defaults, googleVerification: '"><script>bad</script>' },
        overrides: [{ target: { type: 'page', path: '/food.html' }, title: '"><script>alert(1)</script>' }],
      },
    }
  );
  assert.equal((html.match(/<title>/g) || []).length, 1);
  assert.equal((html.match(/name="robots"/g) || []).length, 1);
  assert.equal((html.match(/property="og:title"/g) || []).length, 1);
  assert.doesNotMatch(html, /<script>/);
  assert.match(html, /&lt;script&gt;/);
  assert.doesNotMatch(html, /msvalidate\.01/);
});
