import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import { taipeiInput, taipeiOutput } from '../dist/assets/js/pages/content-form.js';

test('台北編輯時間可跨UTC日期往返，空日期保持選填', () => {
  assert.equal(taipeiInput('2026-10-28T18:30:00+00:00'), '2026-10-29T02:30:00');
  assert.equal(taipeiOutput('2026-10-29T02:30'), '2026-10-29T02:30:00+08:00');
  assert.equal(taipeiOutput(''), '');
  assert.equal(taipeiInput(null), '');
});

test('編輯表單接受伺服器發布時間的秒數，不被原生分鐘步進擋送', async () => {
  for (const page of ['news-add', 'news-edit', 'offer-add', 'offer-edit']) {
    const html = await readFile(new URL(`../pages/admin/${page}.html`, import.meta.url), 'utf8');
    const dates = html.match(/<input\b[^>]*type="datetime-local"[^>]*>/g) || [];
    assert.ok(dates.length > 0);
    for (const input of dates) assert.match(input, /step="1"/);
  }
});
