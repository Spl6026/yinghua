import test from 'node:test';
import assert from 'node:assert/strict';
import { installDom, settle } from './support/fake-dom.mjs';
import { loadModule } from './support/load-module.mjs';

// 測試替身都轉呼叫 harness，個別測試只需改 harness 的內容。
const harness = { confirm: async () => true, list: async () => ({ items: [], total: 0 }), remove: null };
const service = {
  list: query => harness.list(query),
  delete: (id, version) => harness.remove(id, version),
  retry: async () => ({ message: '已重新發布。' }),
};
const stubs = {
  'components/dialog.js': { confirmAction: (...args) => harness.confirm(...args) },
  'api/attractions-api.js': { attractionsApi: service },
  'api/films-api.js': { filmsApi: service },
  'api/content-admin-api.js': { contentAdminApi: () => service },
  'api/recommended-routes-api.js': { recommendedRoutesApi: service },
};
const pages = {
  attractions: [await loadModule('pages/attraction-list.js', stubs), 'initializeAttractionList', 'place'],
  films: [await loadModule('pages/film-list.js', stubs), 'initializeFilmList', 'film'],
  news: [await loadModule('pages/content-list.js', stubs), 'initializeContentList', 'content'],
  offers: [await loadModule('pages/content-list.js', stubs), 'initializeContentList', 'content'],
  routes: [await loadModule('pages/route-list.js', stubs), 'initializeRouteList', 'route'],
};
const { publicationLabel } = await loadModule('core/admin-table.js');

const ids = {
  place: ['place-rows', 'place-list-status', 'place-next'],
  film: ['film-rows', 'film-list-status', 'film-next'],
  content: ['content-rows', 'content-list-status', 'content-next'],
  route: ['route-rows', 'route-list-status', 'route-next'],
};
const record = (n = 1) => ({
  id: 'id-' + n,
  version: 1,
  name: '景點' + n,
  title: '項目' + n,
  status: 'published',
  publishAt: '2026-01-01T00:00:00Z',
  screenings: [],
  stops: [],
  sortOrder: n,
});
const identity = (module, { preview = false, actions = ['view', 'create', 'edit', 'delete'] } = {}) => ({
  admin: { id: 'actor' },
  preview: { active: preview },
  permissions: actions.map(action => ({ module, action, allowed: true })),
});

async function open(module, options) {
  const byId = installDom();
  const [page, init, prefix] = pages[module];
  await page[init](identity(module, options), module);
  const [rows, status, next] = ids[prefix];
  return { byId, rows: byId(rows), status: byId(status), next: byId(next) };
}
const deleteButton = rows => rows.children[0].find(node => node.tagName === 'BUTTON');

for (const module of Object.keys(pages)) {
  test(`${module}：取消確認不刪除，列表保持原樣`, async () => {
    harness.list = async () => ({ items: [record()], total: 1, pageSize: 20 });
    let removed = 0;
    harness.remove = async () => removed++;
    harness.confirm = async () => false;
    const view = await open(module);
    const row = view.rows.children[0];
    await deleteButton(view.rows).dispatch('click');
    assert.equal(removed, 0);
    assert.equal(view.rows.children[0], row);
  });

  test(`${module}：刪除成功後重新載入，焦點交給結果訊息`, async () => {
    let remaining = [record()];
    harness.list = async () => ({ items: remaining, total: remaining.length, pageSize: 20 });
    harness.remove = async () => {
      remaining = [];
      return { publication: { status: 'succeeded', message: '公開頁面已同步。' } };
    };
    harness.confirm = async () => true;
    const view = await open(module);
    await deleteButton(view.rows).dispatch('click');
    await settle();
    assert.match(view.rows.textContent, /目前沒有符合條件的/);
    assert.equal(view.status.textContent, '公開頁面已同步。');
    assert.equal(document.activeElement, view.status);
  });

  test(`${module}：唯讀模擬仍可開啟編輯頁，但刪除按鈕停用`, async () => {
    harness.list = async () => ({ items: [record()], total: 1, pageSize: 20 });
    const view = await open(module, { preview: true });
    assert.ok(view.rows.children[0].find(node => node.tagName === 'A' && node.textContent === '編輯'));
    assert.equal(deleteButton(view.rows).disabled, true);
  });

  test(`${module}：沒有編輯與刪除權限時顯示唯讀`, async () => {
    harness.list = async () => ({ items: [record()], total: 1, pageSize: 20 });
    const view = await open(module, { actions: ['view'] });
    assert.equal(view.rows.children[0].children.at(-1).textContent, '唯讀');
  });
}

test('刪掉最後一頁唯一一筆後退回上一頁，不停在空白頁', async () => {
  let total = 21;
  const requested = [];
  harness.list = async ({ page }) => {
    requested.push(page);
    const pages = Math.max(1, Math.ceil(total / 20));
    return { items: page <= pages ? [record(page)] : [], total, pageSize: 20 };
  };
  harness.remove = async () => {
    total = 20;
    return { publication: { status: 'succeeded', message: '已刪除。' } };
  };
  const view = await open('routes');
  await view.next.dispatch('click');
  await settle();
  assert.deepEqual(requested, [1, 2]);
  await deleteButton(view.rows).dispatch('click');
  await settle();
  assert.deepEqual(requested.slice(2), [2, 1]);
  assert.match(view.rows.textContent, /項目1/);
  assert.equal(view.next.disabled, true);
});

test('較慢的舊回應不會蓋掉較新的篩選結果', async () => {
  const pending = [];
  harness.list = query => new Promise(resolve => pending.push([query, resolve]));
  const byId = installDom();
  const [page] = pages.routes;
  const ready = page.initializeRouteList(identity('routes'));
  byId('route-status').value = 'draft';
  await byId('route-status').dispatch('change');
  const [, older] = pending[0];
  const [newerQuery, newer] = pending[1];
  assert.equal(newerQuery.status, 'draft');
  newer({ items: [record(2)], total: 1 });
  await settle();
  older({ items: [record(1)], total: 1 });
  await ready;
  await settle();
  assert.match(byId('route-rows').textContent, /項目2/);
  assert.doesNotMatch(byId('route-rows').textContent, /項目1/);
});

test('發布狀態文字在所有列表一致', () => {
  const now = Date.parse('2026-06-01T00:00:00Z');
  assert.equal(publicationLabel({ status: 'draft' }, now), '草稿');
  assert.equal(publicationLabel({ status: 'archived' }, now), '已下架');
  assert.equal(publicationLabel({ status: 'published', publishAt: '2026-07-01T00:00:00Z' }, now), '排程發布');
  assert.equal(publicationLabel({ status: 'published', publishAt: '2026-05-01T00:00:00Z' }, now), '已發布');
});
