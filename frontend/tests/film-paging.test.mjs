import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';
import * as taipeiTime from '../assets/js/core/taipei-time.js';

const code = (
  await Promise.all(
    ['core/ui.js', 'core/content-view.js', 'core/film-view.js', 'pages/films.js'].map(path =>
      readFile(new URL('../assets/js/' + path, import.meta.url), 'utf8')
    )
  )
).map(text => text.replace(/^import\b[\s\S]*?;[ \t]*\r?\n/gm, '').replace(/\bexport /g, ''));
const settle = async () => {
  for (let i = 0; i < 6; i++) await new Promise(resolve => setImmediate(resolve));
};
class Node {
  constructor() {
    this.children = [];
    this.dataset = {};
    this.listeners = {};
    this.textContent = '';
    this.innerHTML = 'SSR 原有片單';
  }
  setAttribute(key, value) {
    (this.attributes ||= {})[key] = value;
  }
  append(...nodes) {
    this.children.push(...nodes);
  }
  replaceChildren(...nodes) {
    this.children = nodes;
    this.textContent = nodes.map(node => (typeof node === 'string' ? node : node.textContent)).join('');
  }
  after(...nodes) {
    this.afterNodes = nodes;
  }
  addEventListener(type, fn) {
    this.listeners[type] = fn;
  }
  emit(type) {
    return this.listeners[type]?.();
  }
  focus() {
    this.focused = true;
  }
}
function fixture(total = 21) {
  const list = new Node(),
    snapshot = new Node(),
    label = new Node();
  snapshot.textContent = JSON.stringify({ total });
  const state = {
    calls: [],
    result: {
      items: [],
      total: 21,
      period: { from: '2026-12-27T16:00:00+00:00', to: '2027-01-03T16:00:00+00:00' },
    },
  };
  const sandbox = {
    ...taipeiTime,
    document: {
      querySelector: s => ({ '#cinema-list': list, '#film-publication': snapshot, '#film-period': label })[s],
      querySelectorAll: () => [],
      createElement: () => new Node(),
    },
    api: async path => {
      state.calls.push(path);
      if (state.error) throw new Error('公開頁面更新中');
      return state.result;
    },
    Image: class {},
    passwordError: () => '',
  };
  vm.createContext(sandbox);
  code.forEach(s => vm.runInContext(s, sandbox));
  const notice = list.afterNodes[0],
    pager = list.afterNodes[1];
  return { state, list, label, notice, pager, previous: pager.children[0], next: pager.children[1] };
}
test('真實頁面模組換頁、回上一頁，並以 API 回傳期間更新跨年週標示', async () => {
  const f = fixture();
  assert.equal(f.previous.disabled, true);
  assert.equal(f.next.disabled, false);
  const pending = f.next.emit('click');
  assert.equal(f.next.disabled, true);
  await pending;
  await settle();
  assert.match(f.state.calls[0], /page=2/);
  assert.equal(f.label.textContent, 'THIS WEEK · 12.28 — 01.03');
  assert.equal(f.label.children[0].attributes.lang, 'en');
  assert.equal(f.previous.disabled, false);
  assert.equal(f.next.disabled, true);
  await f.previous.emit('click');
  await settle();
  assert.match(f.state.calls[1], /page=1/);
  assert.equal(f.previous.disabled, true);
});
test('換頁失敗保留 SSR 片單、恢復按鈕且聚焦錯誤，不回填原型電影', async () => {
  const f = fixture();
  f.state.error = true;
  await f.next.emit('click');
  await settle();
  assert.equal(f.list.innerHTML, 'SSR 原有片單');
  assert.match(f.notice.textContent, /更新中/);
  assert.equal(f.notice.focused, true);
  assert.equal(f.next.disabled, false);
});

test('單頁與空清單不顯示分頁按鈕，多頁仍可換頁', () => {
  for (const total of [0, 1, 20]) assert.equal(fixture(total).pager.hidden, true);
  assert.equal(fixture(21).pager.hidden, false);
});
