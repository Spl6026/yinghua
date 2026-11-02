import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';
import { apiModules } from './support/api-modules.mjs';
const source = (
  await readFile(new URL('../assets/js/components/attraction-picker.js', import.meta.url), 'utf8')
)
  .replace(/^import\b[\s\S]*?;[ \t]*\r?\n/gm, '')
  .replace('export function', 'function');
class Node {
  constructor() {
    this.children = [];
    this.listeners = {};
    this.value = '';
    this.attrs = {};
  }
  append(...items) {
    this.children.push(...items);
  }
  replaceChildren(...items) {
    this.children = items;
  }
  setAttribute(key, value) {
    this.attrs[key] = value;
  }
  removeAttribute(key) {
    delete this.attrs[key];
  }
  addEventListener(type, fn) {
    this.listeners[type] = fn;
  }
  emit(type, event = {}) {
    return this.listeners[type]?.({ preventDefault() {}, ...event });
  }
  focus() {
    this.focused = true;
  }
  all() {
    return this.children.flatMap(n => [n, ...n.all()]);
  }
}
async function fixture() {
  const host = new Node(),
    trigger = new Node(),
    selected = new Set(),
    state = { calls: [], chosen: [], disabled: false, fail: false };
  const sandbox = {
    URLSearchParams,
    api: async path => {
      state.calls.push(path);
      if (state.fail) throw new Error('離線');
      return { items: [{ id: 'id', name: '景點一', category: '景點', district: '東區' }], total: 1 };
    },
    element: (_tag, attrs, text) => Object.assign(new Node(), attrs, { textContent: text }),
    status: (node, text) => {
      node.textContent = text;
    },
  };
  Object.assign(
    sandbox,
    apiModules((...args) => sandbox.api(...args))
  );
  vm.createContext(sandbox);
  vm.runInContext(source, sandbox);
  const picker = sandbox.attractionPicker(host, {
    isSelected: id => selected.has(id),
    isDisabled: () => state.disabled,
    onChoose: async place => {
      state.chosen.push(place);
      selected.add(place.id);
    },
  });
  await picker.open(trigger);
  return {
    host,
    trigger,
    state,
    picker,
    selected,
    find: text => host.all().find(n => n.textContent === text),
  };
}
test('原頁選取地點只交給草稿回呼，不能重複加入同一地點', async () => {
  const f = await fixture(),
    button = f.find('加入行程');
  await button.emit('click');
  assert.equal(f.state.chosen.length, 1);
  assert.equal(button.textContent, '已在行程中');
  assert.equal(button.disabled, true);
  await button.emit('click');
  assert.equal(f.state.chosen.length, 1);
  assert.ok(f.state.calls.every(path => path.startsWith('/api/v1/attractions?')));
});
test('收起選擇器保留已選地點，焦點回到新增地點', async () => {
  const f = await fixture();
  await f.find('加入行程').emit('click');
  f.find('收起地點選擇').emit('click');
  assert.equal(f.host.children[0].hidden, true);
  assert.equal(f.trigger.focused, true);
  assert.ok(f.selected.has('id'));
});
test('讀取失敗不保留舊結果，搜尋可重試恢復', async () => {
  const f = await fixture();
  f.state.fail = true;
  await f.picker.open(f.trigger);
  assert.ok(!f.find('加入行程'));
  f.state.fail = false;
  await f.picker.open(f.trigger);
  assert.ok(f.find('加入行程'));
});
