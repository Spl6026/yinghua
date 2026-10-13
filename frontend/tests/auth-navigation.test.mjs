import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';

// 執行實際認證／session 模組；替換 DOM、提交外框及 HTTP 邊界。
const strip = source => source.replace(/^import .*;\r?\n/gm, '').replace(/\bexport /g, '');
const sessionSource = strip(await readFile(new URL('../assets/js/core/session.js', import.meta.url), 'utf8'));
const authSource = strip(await readFile(new URL('../assets/js/auth.js', import.meta.url), 'utf8'));
const uiSource = strip(await readFile(new URL('../assets/js/core/ui.js', import.meta.url), 'utf8'));
const settle = async () => { for (let i = 0; i < 5; i++) await new Promise(resolve => setImmediate(resolve)); };
const unauthorized = () => Object.assign(new Error('請先登入'), { status: 401 });
function deferred() { let resolve; const promise = new Promise(yes => { resolve = yes; }); return { resolve, promise }; }
function fixture({ kind = 'member', mode = 'login', identity, query = '', navigation = false, realSubmit = false } = {}) {
  const state = { redirects: [], calls: [], submits: 0, clock: 1000, timers: [], captcha: async () => ({ challengeToken: 'challenge', imageDataUrl: 'data:image/png;base64,test' }), write: async () => {}, me: async () => { if (identity) return identity; throw unauthorized(); } };
  const submit = { disabled: false, hidden: false };
  const message = { textContent: '', dataset: {}, focus() {} };
  const link = { href: kind === 'admin' ? '/admin/index.html' : '/index.html', textContent: kind === 'admin' ? '回後台' : '回首頁', hidden: false };
  const form = {
    elements: { email: { value: 'fixture@example.test', reportValidity: () => true }, answer: { value: '', focus() {} }, acceptTerms: { checked: true } }, attrs: {}, listeners: {},
    querySelector: selector => selector === '[type="submit"]' ? submit : selector === '.form-status' ? message : null,
    querySelectorAll: () => [],
    addEventListener(type, callback) { this.listeners[type] = callback; },
    setAttribute(key, value) { this.attrs[key] = value; }, removeAttribute(key) { delete this.attrs[key]; }, reset() {},
  };
  const node = navigation ? { className: 'member-menu', children: [], replaceChildren(...items) { this.children = items; }, append(item) { this.children.push(item); } } : null;
  const captchaNodes = new Map(['#refresh-captcha', '#email-captcha', '#captcha-image', '#captcha-status'].map(selector => [selector, { disabled: false, hidden: false, dataset: {}, addEventListener(type, callback) { this.listener = callback; } }]));
  const sandbox = {
    URL, URLSearchParams, Date: { now: () => state.clock }, FormData: class { *[Symbol.iterator]() {} },
    window: { setTimeout(callback) { state.timers.push(callback); } },
    location: { origin: 'http://localhost:8080', pathname: kind === 'admin' ? '/admin/' + mode + '.html' : '/' + mode + '.html', search: query, hash: '',
      replace(path) { state.redirects.push(path); }, assign(path) { state.redirects.push(path); } },
    history: { replaceState() {} },
    document: { body: { dataset: { authKind: kind, authMode: mode } }, querySelector(selector) {
      if (selector === '#auth-form') return form;
      if (selector === '[data-auth-return]') return mode === 'change-password' ? link : null;
      if (selector === '#member-navigation') return node;
      if (captchaNodes.has(selector)) return captchaNodes.get(selector);
      return null;
    } },
    routes: new Set(['/', '/index.html', '/explore.html', '/admin/index.html', '/admin/admin-edit.html', '/admin/login.html', '/login.html', '/admin/change-password.html']),
    initializeMenus() {}, toast() {}, status(target, value) { target.textContent = value; },
    async authConfiguration() { return { termsVersion: 'fixture-v1' }; },
    element(tag, attrs, label) { return { tag, ...attrs, textContent: label, addEventListener() {} }; },
    authApi(namespace = 'member') { return {
      async me() { state.calls.push(namespace); return state.me(namespace); },
      async login() { state.submits++; await state.write(); return identity || { admin: { mustChangePassword: false } }; },
      async change() { state.submits++; await state.write(); state.me = async () => { throw unauthorized(); }; },
      async register() { state.submits++; await state.write(); },
      async captcha() { return state.captcha(); },
      async emailCaptcha() { return state.captcha(); },
    }; },
    async submitForm(target, action) { await action(); },
  };
  vm.createContext(sandbox);
  if (realSubmit) vm.runInContext(uiSource, sandbox);
  vm.runInContext(sessionSource, sandbox);
  const initialize = () => vm.runInContext('(async () => {\n' + authSource + '\n})()', sandbox);
  const send = async () => { form.listeners.submit({ preventDefault() {} }); await settle(); };
  return { state, sandbox, submit, message, link, form, node, captchaNodes, initialize, send };
}

test('已登入會員開登入頁返回合法原頁，拒絕跨站目的地', async () => {
  for (const [query, destination] of [['?returnTo=%2Fexplore.html', '/explore.html'], ['?returnTo=https%3A%2F%2Fattacker.test', '/index.html']]) {
    const f = fixture({ identity: { member: { id: 'member' } }, query });
    await f.initialize(); assert.deepEqual(f.state.redirects, [destination]); assert.equal(f.submit.disabled, true);
  }
});
test('已登入會員開註冊頁直接返回，不建立新驗證碼', async () => {
  const f = fixture({ mode: 'register', identity: { member: { id: 'member' } } });
  await f.initialize(); assert.deepEqual(f.state.redirects, ['/index.html']);
});
test('後台登入頁使用管理員 session，不借會員身分判斷', async () => {
  const f = fixture({ kind: 'admin', identity: { admin: { mustChangePassword: false } } });
  await f.initialize(); assert.deepEqual(f.state.calls, ['admin']); assert.deepEqual(f.state.redirects, ['/admin/index.html']);
});
test('首登管理員回更改密碼，唯讀模擬不進密碼循環', async () => {
  for (const [preview, destination] of [[false, '/admin/change-password.html'], [true, '/admin/index.html']]) {
    const f = fixture({ kind: 'admin', identity: { admin: { mustChangePassword: true }, preview: { active: preview } } });
    await f.initialize(); assert.deepEqual(f.state.redirects, [destination]);
  }
});
test('匿名401保持登入表單；其他API錯誤仍顯示並可重試登入', async () => {
  for (const failure of [unauthorized(), Object.assign(new Error('連線暫時失敗'), { status: 503 })]) {
    const f = fixture(); f.state.me = async () => { throw failure; };
    await f.initialize(); assert.deepEqual(f.state.redirects, []); assert.equal(f.submit.disabled, false);
    if (failure.status === 503) assert.equal(f.message.textContent, failure.message);
  }
});
test('登入身分確認未完成時不提交另一筆登入', async () => {
  const f = fixture(); const pending = deferred(); f.state.me = () => pending.promise;
  const ready = f.initialize(); await f.send(); assert.equal(f.state.submits, 0);
  pending.resolve({ member: { id: 'member' } }); await ready;
  assert.deepEqual(f.state.redirects, ['/index.html']);
});
test('登入請求未完成時不重複提交，成功後不再送出', async () => {
  for (const mode of ['login']) {
    const f = fixture({ mode, identity: mode === 'change-password' ? { member: {} } : undefined });
    await f.initialize(); const pending = deferred(); f.state.write = () => pending.promise;
    await f.send(); await f.send(); assert.equal(f.state.submits, 1);
    pending.resolve(); await settle(); await f.send(); assert.equal(f.state.submits, 1);
  }
});

test('註冊驗證碼準備中或讀取失敗時禁止提交，重試取得後恢復', async () => {
  const f = fixture({ mode: 'register' }); const pending = deferred(); f.state.captcha = () => pending.promise;
  const ready = f.initialize(); await f.send(); assert.equal(f.state.submits, 0); assert.equal(f.submit.disabled, true);
  pending.resolve({ challengeToken: 'challenge', imageDataUrl: 'data:image/png;base64,test' }); await ready;
  assert.equal(f.submit.disabled, false);
  f.state.captcha = async () => { throw new Error('驗證碼服務暫時失敗'); };
  await f.captchaNodes.get('#refresh-captcha').listener(); assert.equal(f.submit.disabled, true);
  f.state.captcha = async () => ({ challengeToken: 'retry', imageDataUrl: 'data:image/png;base64,test' });
  await f.captchaNodes.get('#refresh-captcha').listener(); assert.equal(f.submit.disabled, false);
});

test('註冊429等待期間更新驗證碼仍不能提前重送，到期才恢復', async () => {
  const f = fixture({ mode: 'register', realSubmit: true }); await f.initialize();
  f.state.write = async () => { throw Object.assign(new Error('請稍後再試'), { retryAfter: 30 }); };
  await f.send(); assert.equal(f.state.submits, 1); assert.equal(f.submit.disabled, true);
  await f.captchaNodes.get('#refresh-captcha').listener(); await f.send();
  assert.equal(f.state.submits, 1); assert.equal(f.submit.disabled, true);
  f.state.clock += 30000; f.state.timers.at(-1)(); assert.equal(f.submit.disabled, false);
});
test('過期會員導覽恢復登入按鈕，舊me回應不能恢復已登出名稱', async () => {
  const f = fixture({ navigation: true }); const pending = deferred(); f.state.me = () => pending.promise;
  const old = f.sandbox.memberNavigation();
  f.state.me = async () => { throw unauthorized(); }; await f.sandbox.memberNavigation();
  pending.resolve({ member: { displayName: '舊身分' } }); await old;
  assert.equal(f.node.children.length, 1); assert.equal(f.node.children[0].href, '/login.html'); assert.equal(f.node.className, '');
});
