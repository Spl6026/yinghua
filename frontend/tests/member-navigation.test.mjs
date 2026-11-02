import test from 'node:test';
import assert from 'node:assert/strict';
import { loadWithSandbox } from './support/sandbox-module.mjs';
class Node {
  constructor(tag, text = '') {
    this.tag = tag;
    this.textContent = text;
    this.children = [];
    this.attrs = {};
    this.listeners = {};
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
  focus() {
    this.focused = true;
  }
  texts() {
    return [this.textContent, ...this.children.flatMap(n => n.texts())];
  }
}
async function fixture(read) {
  const node = new Node('span');
  node.append(new Node('a', '會員登入'));
  const navigation = new Node('nav');
  navigation.querySelector = () => null;
  const sandbox = {
    initializeMenus() {},
    document: {
      querySelector: selector =>
        selector === '#member-navigation' ? node : selector === '#primary-nav' ? navigation : null,
    },
    element: (tag, attrs = {}, text) => {
      const n = new Node(tag, text);
      Object.assign(n, attrs);
      return n;
    },
    authApi: () => ({ me: read }),
    toast() {},
  };
  const { memberNavigation } = await loadWithSandbox('core/session.js', sandbox);
  return { node, navigation, run: () => memberNavigation() };
}
const visibleTexts = node => node.children.flatMap(child => child.texts());

test('等待帳號回應時不先顯示訪客；成功才顯示會員連結', async () => {
  let resolve;
  const f = await fixture(
    () =>
      new Promise(done => {
        resolve = done;
      })
  );
  const pending = f.run();
  assert.deepEqual(visibleTexts(f.node), ['確認帳號中…']);
  assert.equal(f.node.attrs['aria-busy'], 'true');
  resolve({ member: { displayName: '本人' } });
  await pending;
  assert.ok(visibleTexts(f.node).includes('本人'));
  assert.ok(!visibleTexts(f.node).includes('確認帳號中…'));
  assert.equal(f.node.attrs['aria-busy'], undefined);
});
test('401才顯示登入；網路失敗提供重試，不冒稱訪客', async () => {
  const guest = await fixture(async () => {
    throw { status: 401 };
  });
  await guest.run();
  assert.ok(visibleTexts(guest.node).includes('會員登入 ↗'));
  const failure = await fixture(async () => {
    throw { status: 0 };
  });
  await failure.run();
  assert.ok(visibleTexts(failure.node).includes('重新確認帳號'));
  assert.ok(!visibleTexts(failure.node).includes('會員登入 ↗'));
});
test('舊帳號回應不得蓋過較新的身分確認', async () => {
  let first,
    second,
    calls = 0;
  const f = await fixture(
    () =>
      new Promise(resolve => {
        if (++calls === 1) first = resolve;
        else second = resolve;
      })
  );
  const old = f.run(),
    fresh = f.run();
  second({ member: { displayName: '新帳號' } });
  await fresh;
  first({ member: { displayName: '舊帳號' } });
  await old;
  assert.ok(visibleTexts(f.node).includes('新帳號'));
  assert.ok(!visibleTexts(f.node).includes('舊帳號'));
});

test('已登入時沿用原本的名稱、收藏、更改密碼與登出文字連結', async () => {
  const f = await fixture(async () => ({ member: { displayName: '本人' } }));
  await f.run();
  assert.equal(f.node.className, 'member-menu');
  assert.deepEqual(
    f.node.children.map(n => n.textContent),
    ['本人', '我的收藏', '更改密碼', '登出']
  );
  assert.deepEqual(
    f.node.children.slice(1, 3).map(n => n.href),
    ['/favorites.html', '/change-password.html']
  );
});
test('手機漢堡選單另附收藏、更改密碼與登出，頁首不需擠入全部連結', async () => {
  const f = await fixture(async () => ({ member: { displayName: '本人' } }));
  await f.run();
  const account = f.navigation.children.find(n => n.className === 'nav-account');
  assert.ok(account);
  assert.deepEqual(
    account.children.map(n => n.textContent),
    ['我的收藏', '更改密碼', '登出']
  );
});
