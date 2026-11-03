import test from 'node:test';
import assert from 'node:assert/strict';
import { loadWithSandbox } from './support/sandbox-module.mjs';
const settle = async () => {
  for (let i = 0; i < 8; i++) await new Promise(resolve => setImmediate(resolve));
};
class Node {
  constructor(tag = 'div') {
    this.tag = tag;
    this.attrs = {};
    this.dataset = {};
    this.children = [];
    this.listeners = new Map();
    this.value = '';
    this.files = [];
    this.disabled = false;
    this.textContent = '';
    this.classList = { add() {}, remove() {} };
  }
  setAttribute(k, v) {
    this.attrs[k] = v;
  }
  removeAttribute(k) {
    delete this.attrs[k];
  }
  append(...children) {
    for (const c of children) {
      c.parent = this;
      this.children.push(c);
    }
  }
  replaceChildren(...children) {
    this.children = [];
    this.append(...children);
  }
  addEventListener(type, fn) {
    this.listeners.set(type, fn);
  }
  emit(type) {
    return this.listeners.get(type)?.({ preventDefault() {}, currentTarget: this });
  }
  querySelectorAll(selector) {
    if (selector.startsWith('input,'))
      return this.children.filter(
        n => ['input', 'select', 'textarea'].includes(n.tag) || n.attrs['data-write'] || n.tag === 'button'
      );
    if (selector === '[aria-invalid]') return this.children.filter(n => n.attrs['aria-invalid']);
    if (selector === '.field-error') return this.children.filter(n => n.className === 'field-error');
    return [];
  }
  focus() {
    this.focused = true;
  }
  closest() {
    return this.parent;
  }
  remove() {
    this.parent.children = this.parent.children.filter(c => c !== this);
  }
}

async function fixture(total = 21) {
  const nodes = new Map(),
    node = id => {
      if (!nodes.has(id)) nodes.set(id, new Node());
      return nodes.get(id);
    };
  const select = new Node('select'),
    state = { calls: [], available: true };
  const sandbox = {
    document: { querySelector: node, createElement: t => new Node(t) },
    hasPermission: () => true,
    handleAdminError: (e, n) => (n.textContent = e.message),
    api: {
      list: async q => {
        state.calls.push(q);
        return state.list ? state.list(q) : { items: [{ id: 'merchant', name: '店家' }], total };
      },
    },
  };
  Object.assign(sandbox, await loadWithSandbox('components/offer-merchants.js', sandbox));
  const component = sandbox.offerMerchants(
    {},
    { elements: { merchantId: select } },
    () => state.available,
    () => {},
    () => {}
  );
  return { component, state, node };
}
test('店家分頁第一頁／最後一頁不能越界，重套表單狀態仍維持邊界', async () => {
  const f = await fixture();
  await f.component.load();
  assert.equal(f.node('#merchant-previous').disabled, true);
  assert.equal(f.node('#merchant-next').disabled, false);
  f.node('#merchant-previous').disabled = false;
  f.component.refresh();
  assert.equal(f.node('#merchant-previous').disabled, true);
  f.node('#merchant-previous').emit('click');
  await settle();
  assert.equal(f.state.calls.length, 1);
  f.node('#merchant-next').emit('click');
  await settle();
  assert.equal(f.state.calls[1].page, '2');
  assert.equal(f.node('#merchant-next').disabled, true);
  assert.equal(f.node('#merchant-previous').disabled, false);
  f.node('#merchant-next').emit('click');
  await settle();
  assert.equal(f.state.calls.length, 2);
});
test('店家只有一頁或零筆時兩端都停用', async () => {
  for (const total of [0, 5, 20]) {
    const f = await fixture(total);
    await f.component.load();
    assert.equal(f.node('#merchant-previous').disabled, true);
    assert.equal(f.node('#merchant-next').disabled, true);
  }
});
test('表單保存或唯讀期間不能由分頁恢復操作', async () => {
  const f = await fixture();
  await f.component.load();
  f.state.available = false;
  f.component.refresh();
  assert.equal(f.node('#merchant-next').disabled, true);
  assert.equal(f.node('#search-merchants').disabled, true);
  f.node('#merchant-next').emit('click');
  await settle();
  assert.equal(f.state.calls.length, 1);
  f.state.available = true;
  f.component.refresh();
  assert.equal(f.node('#merchant-next').disabled, false);
  assert.equal(f.node('#merchant-previous').disabled, true);
});
