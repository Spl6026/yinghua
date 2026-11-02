import test from 'node:test';
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { mkdtemp, readFile, realpath, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { emptyOpeningHours } from '../assets/js/core/opening-hours.js';

// 真正執行 renderer；檢查產物，不以 source substring 當功能測試。
const script = fileURLToPath(new URL('../scripts/publish.mjs', import.meta.url));
const sample = {
  id: '03000000-0000-4000-8000-000000000001',
  slug: 'test-place',
  name: '測試「景點」',
  category: '景點',
  district: '北區',
  typeLabel: '測試',
  summary: '摘要',
  description: '<script>alert(1)</script>\n\n第二段',
  hours: '09:00–17:00',
  address: '測試地址',
  transport: '',
  websiteUrl: '',
  mapUrl: '',
  image: '/media/test.png',
  imageAlt: '安全圖片',
  featured: true,
  isMuseum: false,
  updatedAt: '2026-10-17T16:30:00+00:00',
};
const snapshot = (generation, items) => ({
  origin: 'https://example.test',
  generation: generation.repeat(64),
  items,
  aliases: {},
});
function publish(directory, data) {
  return spawnSync(process.execPath, [script, directory], { input: JSON.stringify(data), encoding: 'utf8' });
}

test('公開 HTML 含可讀詳情、正確 canonical 與安全編碼', async () => {
  const directory = await mkdtemp(join(tmpdir(), 'spl-publish-'));
  try {
    assert.equal(publish(directory, snapshot('a', [sample])).status, 0);
    const html = await readFile(join(directory, 'current/places/test-place.html'), 'utf8');
    assert.match(html, /&lt;script&gt;alert\(1\)&lt;\/script&gt;/);
    assert.doesNotMatch(html, /<script>alert\(1\)<\/script>/);
    assert.match(html, /rel="canonical" href="https:\/\/example.test\/places\/test-place.html"/);
    assert.match(html, /name="description" content="摘要"/);
    assert.match(html, /更新日期：2026-10-18/);
    assert.match(html, /data-managed-attractions="true"/);
    assert.match(html, /src="\/assets\/js\/pages\/attractions.js"/);
    const listing = await readFile(join(directory, 'current/explore.html'), 'utf8');
    assert.match(listing, /class="place-image"[^>]*role="group"/);
  } finally {
    await rm(directory, { recursive: true, force: true });
  }
});

test('空白介紹即使舊資料標為 google-pending，也不產生獨立 Google 參考區', async () => {
  const directory = await mkdtemp(join(tmpdir(), 'spl-import-empty-'));
  try {
    const item = { ...sample, description: '', contentSource: { description: 'google-pending' } };
    assert.equal(publish(directory, snapshot('f', [item])).status, 0);
    const html = await readFile(join(directory, 'current/places/test-place.html'), 'utf8');
    assert.match(html, /尚未提供景點介紹/);
    assert.doesNotMatch(html, /data-google-summary|components\/google-summary\.js|google-summary-reference/);
  } finally {
    await rm(directory, { recursive: true, force: true });
  }
});

test('失敗不切換 current；下架後新版本不含舊詳情', async () => {
  const directory = await mkdtemp(join(tmpdir(), 'spl-publish-'));
  try {
    assert.equal(publish(directory, snapshot('b', [sample])).status, 0);
    const active = await realpath(join(directory, 'current'));
    assert.notEqual(publish(directory, snapshot('c', [{ ...sample, slug: '../invalid' }])).status, 0);
    assert.equal(await realpath(join(directory, 'current')), active);
    assert.equal(publish(directory, snapshot('d', [])).status, 0);
    await assert.rejects(readFile(join(directory, 'current/places/test-place.html'), 'utf8'), {
      code: 'ENOENT',
    });
    assert.equal(
      JSON.parse(await readFile(join(directory, 'current/.snapshot.json'), 'utf8')).items.length,
      0
    );
  } finally {
    await rm(directory, { recursive: true, force: true });
  }
});

test('選填介紹與行政區留空可發布，詳情和列表不留下空段落或孤立分隔符號', async () => {
  const directory = await mkdtemp(join(tmpdir(), 'spl-optional-view-'));
  try {
    const item = { ...sample, district: '', summary: '', description: '' };
    assert.equal(publish(directory, snapshot('f', [item])).status, 0);
    const detail = await readFile(join(directory, 'current/places/test-place.html'), 'utf8');
    const list = await readFile(join(directory, 'current/explore.html'), 'utf8');
    assert.match(detail, /<div class="detail-meta">測試<\/div>/);
    assert.doesNotMatch(detail, /<p class="detail-lead"><\/p>|<p><\/p>|測試・<\/div>/);
    assert.match(list, /<span class="place-area">在地推薦<\/span>/);
    assert.doesNotMatch(list, /<p><\/p>|　\/　在地推薦/);
    assert.doesNotMatch(detail, /Google 簡介|尚未提供介紹/);
  } finally {
    await rm(directory, { recursive: true, force: true });
  }
});

test('週表詳情含午休、跨日、公休及安全備註，卡片保留短摘要', async () => {
  const directory = await mkdtemp(join(tmpdir(), 'spl-hours-'));
  try {
    const openingHours = emptyOpeningHours('<script>備註</script>');
    openingHours.days[1] = {
      day: 1,
      status: 'periods',
      periods: [
        { opens: '09:00', closes: '12:00', nextDay: false },
        { opens: '22:00', closes: '02:00', nextDay: true },
      ],
    };
    openingHours.days[2].status = 'closed';
    assert.equal(
      publish(directory, snapshot('e', [{ ...sample, hours: '每週時段不同，詳見介紹', openingHours }]))
        .status,
      0
    );
    const html = await readFile(join(directory, 'current/places/test-place.html'), 'utf8');
    assert.match(html, /週一：09:00–12:00、22:00–次日 02:00/);
    assert.match(html, /週二：公休/);
    assert.match(html, /&lt;script&gt;備註&lt;\/script&gt;/);
    const list = await readFile(join(directory, 'current/explore.html'), 'utf8');
    assert.match(list, /◷ 每週時段不同，詳見介紹/);
  } finally {
    await rm(directory, { recursive: true, force: true });
  }
});
