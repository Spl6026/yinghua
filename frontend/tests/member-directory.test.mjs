import test from 'node:test';
import assert from 'node:assert/strict';
import { formatUtcDateTime } from '../assets/js/core/utc-display.js';
import { apiModules } from './support/api-modules.mjs';
import { loadWithSandbox } from './support/sandbox-module.mjs';
class Node {
  constructor() {
    this.children = [];
    this.listeners = {};
    this.value = '';
    this.disabled = false;
    this.textContent = '';
  }
  append(...items) {
    this.children.push(...items);
  }
  replaceChildren(...items) {
    this.children = items;
  }
  setAttribute() {}
  removeAttribute() {}
  addEventListener(type, fn) {
    this.listeners[type] = fn;
  }
  emit(type, event = {}) {
    return this.listeners[type]?.({ preventDefault() {}, ...event });
  }
  focus() {
    this.focused = true;
  }
  showModal() {
    this.open = true;
  }
}
const settle = async () => {
  for (let i = 0; i < 5; i++) await new Promise(resolve => setImmediate(resolve));
};
const result = name => ({
  items: [
    {
      memberId: 'uuid',
      displayName: name,
      email: 'm＊＊＊@e＊＊＊',
      joinedAt: '2026-01-01',
      status: 'active',
    },
  ],
  total: 1,
});
async function fixture() {
  const nodes = Object.fromEntries(
    [
      'member-submit',
      'member-status',
      'member-rows',
      'member-query',
      'member-previous',
      'member-next',
      'member-summary',
      'member-summary-text',
      'member-page',
      'member-search',
      'member-retry',
    ].map(id => ['#' + id, new Node()])
  );
  const state = { calls: 0, response: async () => result('王＊＊＊') };
  const sandbox = {
    formatUtcDateTime,
    URLSearchParams,
    document: { querySelector: key => nodes[key] },
    setTimeout,
    clearTimeout,
    api: async () => {
      state.calls++;
      return state.response();
    },
    element: (_tag, attrs, text) => Object.assign(new Node(), attrs, { textContent: text }),
    status: (node, text) => {
      node.textContent = text;
    },
    handleAdminError: (error, node) => {
      node.textContent = error.message;
    },
  };
  Object.assign(
    sandbox,
    apiModules((...args) => sandbox.api(...args))
  );
  Object.assign(sandbox, await loadWithSandbox('pages/member-directory.js', sandbox));
  await sandbox.initializeMemberDirectory();
  return { nodes, state };
}
test('查看只使用遮罩資料，關閉摘要後焦點返回原按鈕', async () => {
  const f = await fixture(),
    row = f.nodes['#member-rows'].children[0],
    view = row.children[4].children[0];
  view.emit('click');
  assert.equal(f.nodes['#member-summary'].open, true);
  assert.match(f.nodes['#member-summary-text'].textContent, /王＊＊＊/);
  f.nodes['#member-summary'].emit('close');
  assert.equal(view.focused, true);
});
test('較晚完成的舊搜尋不得覆蓋新結果', async () => {
  const f = await fixture();
  let resolve;
  f.state.response = () =>
    new Promise(done => {
      resolve = done;
    });
  f.nodes['#member-search'].emit('submit');
  await settle();
  f.state.response = async () => result('新＊＊＊');
  f.nodes['#member-search'].emit('submit');
  await settle();
  resolve(result('舊＊＊＊'));
  await settle();
  assert.equal(f.nodes['#member-rows'].children[0].children[0].textContent, '新＊＊＊');
});
test('載入失敗清除舊列表，可使用重新載入恢復', async () => {
  const f = await fixture();
  f.state.response = async () => {
    throw new Error('載入失敗');
  };
  f.nodes['#member-retry'].emit('click');
  await settle();
  assert.equal(f.nodes['#member-rows'].children.length, 0);
  f.state.response = async () => result('恢＊＊＊');
  f.nodes['#member-retry'].emit('click');
  await settle();
  assert.equal(f.nodes['#member-rows'].children[0].children[0].textContent, '恢＊＊＊');
});
