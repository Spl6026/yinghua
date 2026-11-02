import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';

const source = (await readFile(new URL('../assets/js/pages/attractions.js', import.meta.url), 'utf8'))
  .replace(/^import\b[\s\S]*?;[ \t]*\r?\n/gm, '')
  .replace(/^export .*;\r?\n/gm, '');
const template = await readFile(new URL('../pages/food.html', import.meta.url), 'utf8');
const settle = () => new Promise(resolve => setImmediate(resolve));

test('美食原型沒有分類數字仍載入完成，切換在地店家可使用正確 API 分類', async () => {
  const node = () => ({
    dataset: {},
    children: [],
    listeners: {},
    classList: { toggle() {} },
    setAttribute() {},
    removeAttribute() {},
    querySelector() {
      return null;
    },
    append(...items) {
      this.children.push(...items);
    },
    before() {},
    after() {},
    replaceChildren(...items) {
      this.children = items;
    },
    addEventListener(name, fn) {
      this.listeners[name] = fn;
    },
  });
  const tabs = [...template.matchAll(/<button class="guide-tab"([^>]*)>/g)].map(([, attributes]) => {
    assert.match(attributes, /role="tab"/);
    const tab = node();
    tab.dataset.category = attributes.match(/data-category="([^"]+)"/)[1];
    return tab;
  });
  assert.equal(tabs.length, 4);
  const grid = node(),
    group = node(),
    messages = [],
    requests = [];
  let mounted = 0;
  const sandbox = {
    URLSearchParams,
    location: { pathname: '/food.html', search: '' },
    document: {
      body: { dataset: { managedAttractions: 'true' } },
      querySelector: selector => ({ '#place-grid': grid, '.guide-tabs': group })[selector] || null,
      querySelectorAll: selector => (selector === '.guide-tab' ? tabs : []),
    },
    element: () => node(),
    status: (_node, text, error) => messages.push({ text, error }),
    mountMemberActions: async () => {
      mounted++;
    },
    api: async url => {
      requests.push(new URL(url, 'http://localhost').searchParams);
      return { items: [], total: 0, counts: { all: 0, 美食: 0, 在地店家: 0 } };
    },
  };
  vm.createContext(sandbox);
  await vm.runInContext(`(async()=>{${source}\n})()`, sandbox);
  assert.equal(requests[0].get('category'), '美食');
  assert.equal(mounted, 1);
  assert.equal(messages.at(-1).text, '找到 0 筆景點。');
  tabs[3].listeners.click();
  await settle();
  assert.equal(requests[1].get('category'), '在地店家');
  assert.equal(mounted, 2);
  assert.ok(messages.every(message => !message.error));
});
