import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';
import { PendingWrites } from '../assets/js/core/pending-writes.js';
import { apiModules } from './support/api-modules.mjs';

const source = (await readFile(new URL('../assets/js/core/member-actions.js', import.meta.url), 'utf8'))
  .replace(/^import\b[\s\S]*?;[ \t]*\r?\n/gm, '')
  .replace(/\bexport /g, '');
const settle = () => new Promise(resolve => setImmediate(resolve));
function fixture() {
  let resolve,
    section = null;
  const waiting = new Promise(done => (resolve = done)),
    navigations = [];
  const main = {
    prepend: node => {
      section = node;
    },
  };
  const sandbox = {
    PendingWrites,
    URLSearchParams,
    memberResourcesApi: {},
    readMember: async () => null,
    location: {
      search: '?pendingFavorite=06000000-0000-4000-8000-000000000001',
      assign: target => navigations.push(target),
    },
    authApi() {},
    MemberResourceState: class {
      async refresh() {
        return true;
      }
    },
    document: {
      querySelector: selector => (selector === 'main' ? main : section?.isConnected ? section : null),
      querySelectorAll: () => [],
    },
    api: () => waiting,
    status: (node, text) => (node.textContent = text),
    toast() {},
    history: { replaceState() {} },
    URL,
    element: (tag, attrs = {}, text) => ({
      tag,
      ...attrs,
      textContent: text,
      children: [],
      isConnected: true,
      listeners: new Map(),
      append(...nodes) {
        this.children.push(...nodes);
      },
      addEventListener(name, fn) {
        this.listeners.set(name, fn);
      },
      remove() {
        this.isConnected = false;
      },
    }),
  };
  Object.assign(
    sandbox,
    apiModules((...args) => sandbox.api(...args))
  );
  vm.createContext(sandbox);
  vm.runInContext(source, sandbox);
  vm.runInContext('clearMemberIntent=()=>{}', sandbox);
  return {
    start: () => vm.runInContext('showFavoriteIntent()', sandbox),
    resolve,
    section: () => section,
    navigations,
    beginWrite: action => {
      sandbox.writeAction = action;
      return vm.runInContext('favoriteWrites.run(writeAction)', sandbox);
    },
    navigate: () => vm.runInContext("navigateAfterFavorites('/favorites.html')", sandbox),
  };
}

test('列表重畫重複掛載時不作廢正在讀取的收藏待辦', async () => {
  const f = fixture();
  const first = f.start();
  await settle();
  await f.start();
  f.resolve({ items: [{ id: '06000000-0000-4000-8000-000000000001', name: '公開地點' }], total: 1 });
  await first;
  assert.match(f.section().children[0].textContent, /是否將.*公開地點/);
  assert.equal(f.section().children.length, 3);
});

test('收藏地點載入中即可取消，晚回應不補上確認按鈕', async () => {
  const f = fixture();
  const first = f.start();
  await settle();
  f.section().children[1].listeners.get('click')();
  f.resolve({ items: [], total: 0 });
  await first;
  assert.equal(f.section().isConnected, false);
  assert.equal(f.section().children.length, 2);
});

test('收藏確認完成才前往收藏清單，不讓導覽中斷寫入', async () => {
  const f = fixture();
  let finish;
  const write = f.beginWrite(() => new Promise(resolve => (finish = resolve)));
  const navigate = f.navigate();
  await settle();
  assert.equal(f.navigations.length, 0);
  finish(true);
  await write;
  await navigate;
  assert.deepEqual(f.navigations, ['/favorites.html']);
});

test('收藏儲存無法確認時停留原頁，不假裝已保存並離頁', async () => {
  const f = fixture();
  const write = f.beginWrite(() => false);
  const navigate = f.navigate();
  await write;
  await navigate;
  assert.deepEqual(f.navigations, []);
});
