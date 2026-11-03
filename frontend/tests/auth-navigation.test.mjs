import test from 'node:test';
import assert from 'node:assert/strict';
import { loadWithSandbox } from './support/sandbox-module.mjs';

// 執行實際認證／session 模組；替換 DOM、提交外框及 HTTP 邊界。
const settle = async () => {
  for (let i = 0; i < 5; i++) await new Promise(resolve => setImmediate(resolve));
};
const unauthorized = () => Object.assign(new Error('請先登入'), { status: 401 });
function deferred() {
  let resolve;
  const promise = new Promise(yes => {
    resolve = yes;
  });
  return { resolve, promise };
}
async function fixture({
  kind = 'member',
  mode = 'login',
  identity,
  query = '',
  navigation = false,
  realSubmit = false,
} = {}) {
  if (identity?.admin && !identity.permissions)
    identity = { ...identity, permissions: [{ module: 'dashboard', action: 'view', allowed: true }] };
  const state = {
    redirects: [],
    calls: [],
    submits: 0,
    logouts: 0,
    clock: 1000,
    timers: [],
    captcha: async () => ({ challengeToken: 'challenge', imageDataUrl: 'data:image/png;base64,test' }),
    write: async () => {},
    logout: async () => {},
    me: async () => {
      if (identity) return identity;
      throw unauthorized();
    },
  };
  const submit = { disabled: false, hidden: false };
  const message = { textContent: '', dataset: {}, focus() {} };
  const link = {
    href: kind === 'admin' ? '/admin/index.html' : '/index.html',
    textContent: kind === 'admin' ? '回後台' : '回首頁',
    hidden: false,
  };
  const logout = {
    hidden: true,
    disabled: false,
    addEventListener(type, callback) {
      this.listener = callback;
    },
  };
  const switchLink = { href: mode === 'login' ? '/register.html' : '/login.html' };
  const form = {
    elements: {
      email: { value: 'fixture@example.test', reportValidity: () => true },
      answer: { value: '', focus() {} },
      acceptTerms: { checked: true },
    },
    attrs: {},
    listeners: {},
    querySelector: selector =>
      selector === '[type="submit"]' ? submit : selector === '.form-status' ? message : null,
    querySelectorAll: () => [],
    addEventListener(type, callback) {
      this.listeners[type] = callback;
    },
    setAttribute(key, value) {
      this.attrs[key] = value;
    },
    removeAttribute(key) {
      delete this.attrs[key];
    },
    reset() {},
  };
  const node = navigation
    ? {
        className: '',
        children: [],
        setAttribute() {},
        removeAttribute() {},
        replaceChildren(...items) {
          this.children = items;
        },
        append(item) {
          this.children.push(item);
        },
      }
    : null;
  const captchaNodes = new Map(
    ['#refresh-captcha', '#email-captcha', '#captcha-image', '#captcha-status', '[data-terms-version]'].map(
      selector => [
        selector,
        {
          disabled: false,
          hidden: false,
          dataset: {},
          addEventListener(type, callback) {
            this.listener = callback;
          },
        },
      ]
    )
  );
  const sandbox = {
    URL,
    URLSearchParams,
    Date: { now: () => state.clock },
    FormData: class {
      *[Symbol.iterator]() {}
    },
    window: {
      setTimeout(callback) {
        state.timers.push(callback);
      },
    },
    location: {
      origin: 'http://localhost:8080',
      pathname: kind === 'admin' ? '/admin/' + mode + '.html' : '/' + mode + '.html',
      search: query,
      hash: '',
      replace(path) {
        state.redirects.push(path);
      },
      assign(path) {
        state.redirects.push(path);
      },
    },
    history: { replaceState() {} },
    document: {
      body: { dataset: { authKind: kind, authMode: mode } },
      querySelector(selector) {
        if (selector === '#auth-form') return form;
        if (selector === '.account-switch a') return switchLink;
        if (selector === '[data-auth-return]') return mode === 'change-password' ? link : null;
        if (selector === '[data-auth-logout]')
          return kind === 'admin' && mode === 'change-password' ? logout : null;
        if (selector === '#member-navigation') return node;
        if (captchaNodes.has(selector)) return captchaNodes.get(selector);
        return null;
      },
    },
    routes: new Set([
      '/',
      '/index.html',
      '/explore.html',
      '/planner.html',
      '/admin/index.html',
      '/admin/admin-edit.html',
      '/admin/login.html',
      '/login.html',
      '/admin/change-password.html',
    ]),
    initializeMenus() {},
    toast() {},
    status(target, value) {
      target.textContent = value;
    },
    async authConfiguration() {
      return { termsVersion: 'fixture-v1', ...(state.termsLabel ? { termsLabel: state.termsLabel } : {}) };
    },
    element(tag, attrs, label) {
      return { tag, ...attrs, textContent: label, addEventListener() {} };
    },
    authApi(namespace = 'member') {
      return {
        async me() {
          state.calls.push(namespace);
          return state.me(namespace);
        },
        async login() {
          state.submits++;
          await state.write();
          return identity || { admin: {} };
        },
        async change() {
          state.submits++;
          await state.write();
          state.me = async () => {
            throw unauthorized();
          };
        },
        async logout() {
          state.logouts++;
          await state.logout();
        },
        async register(body) {
          state.registered = body;
          state.submits++;
          await state.write();
        },
        async captcha() {
          return state.captcha();
        },
        async emailCaptcha() {
          return state.captcha();
        },
      };
    },
    async submitForm(target, action) {
      await action();
    },
  };
  // 需要真正的送出流程時不提供 submitForm 替身，改用 ui.js 的實作。
  if (realSubmit) delete sandbox.submitForm;
  Object.assign(sandbox, await loadWithSandbox('core/session.js', sandbox));
  const initialize = () => loadWithSandbox('auth.js', sandbox);
  const send = async () => {
    form.listeners.submit({ preventDefault() {} });
    await settle();
  };
  return {
    state,
    sandbox,
    submit,
    message,
    link,
    switchLink,
    logout,
    form,
    node,
    captchaNodes,
    initialize,
    send,
  };
}

test('登入與註冊互切保留安全行程待辦，拒絕外站及管理頁目的地', async () => {
  for (const mode of ['login', 'register'])
    for (const target of [
      '/planner.html?place=03000000-0000-4000-8000-000000000002',
      'https://attacker.test',
      '/admin/index.html',
    ]) {
      const f = await fixture({ mode, query: '?returnTo=' + encodeURIComponent(target) });
      await f.initialize();
      const link = new URL(f.switchLink.href, 'http://localhost:8080');
      assert.equal(link.pathname, mode === 'login' ? '/register.html' : '/login.html');
      assert.equal(
        link.searchParams.get('returnTo'),
        target.startsWith('/planner.html') ? target : '/index.html'
      );
      assert.equal(f.state.submits, 0);
    }
});

test('已登入會員開登入頁返回合法原頁，拒絕跨站目的地', async () => {
  for (const [query, destination] of [
    ['?returnTo=%2Fexplore.html', '/explore.html'],
    ['?returnTo=https%3A%2F%2Fattacker.test', '/index.html'],
  ]) {
    const f = await fixture({ identity: { member: { id: 'member' } }, query });
    await f.initialize();
    assert.deepEqual(f.state.redirects, [destination]);
    assert.equal(f.submit.disabled, true);
  }
});
test('已登入會員開註冊頁直接返回，不建立新驗證碼', async () => {
  const f = await fixture({ mode: 'register', identity: { member: { id: 'member' } } });
  await f.initialize();
  assert.deepEqual(f.state.redirects, ['/index.html']);
});
test('後台登入頁使用管理員 session，不借會員身分判斷', async () => {
  const f = await fixture({ kind: 'admin', identity: { admin: {} } });
  await f.initialize();
  assert.deepEqual(f.state.calls, ['admin']);
  assert.deepEqual(f.state.redirects, ['/admin/index.html']);
});
test('管理員與唯讀模擬登入後都回到後台', async () => {
  for (const [preview, destination] of [
    [false, '/admin/index.html'],
    [true, '/admin/index.html'],
  ]) {
    const f = await fixture({
      kind: 'admin',
      identity: { admin: {}, preview: { active: preview } },
    });
    await f.initialize();
    assert.deepEqual(f.state.redirects, [destination]);
  }
});
test('匿名401保持登入表單；其他API錯誤仍顯示並可重試登入', async () => {
  for (const failure of [unauthorized(), Object.assign(new Error('連線暫時失敗'), { status: 503 })]) {
    const f = await fixture();
    f.state.me = async () => {
      throw failure;
    };
    await f.initialize();
    assert.deepEqual(f.state.redirects, []);
    assert.equal(f.submit.disabled, false);
    if (failure.status === 503) assert.equal(f.message.textContent, failure.message);
  }
});
test('登入身分確認未完成時不提交另一筆登入', async () => {
  const f = await fixture();
  const pending = deferred();
  f.state.me = () => pending.promise;
  const ready = f.initialize();
  await ready.evaluating;
  await f.send();
  assert.equal(f.state.submits, 0);
  pending.resolve({ member: { id: 'member' } });
  await ready;
  assert.deepEqual(f.state.redirects, ['/index.html']);
});
test('未更改密碼的正常會員／管理員保留返回首頁／後台', async () => {
  for (const kind of ['member', 'admin']) {
    const f = await fixture({
      kind,
      mode: 'change-password',
      identity: { member: {}, admin: {} },
    });
    await f.initialize();
    assert.equal(f.link.hidden, false);
    assert.equal(f.link.href, kind === 'admin' ? '/admin/index.html' : '/index.html');
  }
});
test('管理員改密碼前可返回，成功後顯示重新登入連結', async () => {
  const f = await fixture({
    kind: 'admin',
    mode: 'change-password',
    identity: { admin: {} },
  });
  await f.initialize();
  assert.equal(f.link.hidden, false);
  await f.send();
  assert.equal(f.link.hidden, false);
  assert.equal(f.link.href, '/admin/login.html');
  assert.equal(f.link.textContent, '重新登入');
});

test('改密碼頁可登出換帳號，等待登出時不更改密碼或重送', async () => {
  const f = await fixture({
    kind: 'admin',
    mode: 'change-password',
    identity: { admin: {} },
  });
  const identity = deferred();
  f.state.me = () => identity.promise;
  const ready = f.initialize();
  await ready.evaluating;
  await f.logout.listener();
  assert.equal(f.state.logouts, 0);
  identity.resolve({ admin: {} });
  await ready;
  assert.equal(f.link.hidden, false);
  assert.equal(f.logout.hidden, false);
  assert.equal(f.logout.disabled, false);
  const pending = deferred();
  f.state.logout = () => pending.promise;
  const leaving = f.logout.listener();
  await f.logout.listener();
  await f.send();
  assert.equal(f.state.logouts, 1);
  assert.equal(f.state.submits, 0);
  assert.equal(f.submit.disabled, true);
  assert.equal(f.form.attrs['aria-busy'], 'true');
  pending.resolve();
  await leaving;
  assert.deepEqual(f.state.redirects, ['/admin/login.html']);
});

test('改密碼期間不能登出，登出失敗可重試，失效session則回登入', async () => {
  const f = await fixture({
    kind: 'admin',
    mode: 'change-password',
    identity: { admin: {} },
    realSubmit: true,
  });
  await f.initialize();
  f.state.logout = async () => {
    throw new Error('連線暫時失敗');
  };
  await f.logout.listener();
  assert.equal(f.message.textContent, '連線暫時失敗');
  assert.equal(f.logout.disabled, false);
  assert.equal(f.submit.disabled, false);
  const pending = deferred();
  f.state.write = () => pending.promise;
  await f.send();
  await f.logout.listener();
  assert.equal(f.state.logouts, 1);
  assert.equal(f.logout.disabled, true);
  pending.resolve();
  await settle();
  assert.equal(f.logout.hidden, true);
  const expired = await fixture({
    kind: 'admin',
    mode: 'change-password',
    identity: { admin: {} },
  });
  await expired.initialize();
  expired.state.logout = async () => {
    throw unauthorized();
  };
  await expired.logout.listener();
  assert.deepEqual(expired.state.redirects, ['/admin/login.html']);
});
test('更改密碼身分尚未讀取時拒絕提交，載入後可正常更新', async () => {
  const f = await fixture({ mode: 'change-password' });
  const pending = deferred();
  f.state.me = () => pending.promise;
  const ready = f.initialize();
  await ready.evaluating;
  await f.send();
  assert.equal(f.state.submits, 0);
  pending.resolve({ member: { id: 'member' } });
  await ready;
  await f.send();
  assert.equal(f.link.href, '/login.html');
  assert.equal(f.link.textContent, '重新登入');
  assert.equal(f.submit.hidden, true);
  const failed = await fixture({ mode: 'change-password' });
  failed.state.me = async () => {
    throw new Error('服務暫時失敗');
  };
  await failed.initialize();
  await failed.send();
  assert.equal(failed.state.submits, 0);
  assert.equal(failed.submit.disabled, true);
});

test('登入與更改密碼的請求未完成時不重複提交，成功後不再送出', async () => {
  for (const mode of ['login', 'change-password']) {
    const f = await fixture({ mode, identity: mode === 'change-password' ? { member: {} } : undefined });
    await f.initialize();
    const pending = deferred();
    f.state.write = () => pending.promise;
    await f.send();
    await f.send();
    assert.equal(f.state.submits, 1);
    pending.resolve();
    await settle();
    await f.send();
    assert.equal(f.state.submits, 1);
  }
});

test('註冊驗證碼準備中或讀取失敗時禁止提交，重試取得後恢復', async () => {
  const f = await fixture({ mode: 'register' });
  const pending = deferred();
  f.state.captcha = () => pending.promise;
  const ready = f.initialize();
  await ready.evaluating;
  await f.send();
  assert.equal(f.state.submits, 0);
  assert.equal(f.submit.disabled, true);
  pending.resolve({ challengeToken: 'challenge', imageDataUrl: 'data:image/png;base64,test' });
  await ready;
  assert.equal(f.submit.disabled, false);
  f.state.captcha = async () => {
    throw new Error('驗證碼服務暫時失敗');
  };
  await f.captchaNodes.get('#refresh-captcha').listener();
  assert.equal(f.submit.disabled, true);
  f.state.captcha = async () => ({ challengeToken: 'retry', imageDataUrl: 'data:image/png;base64,test' });
  await f.captchaNodes.get('#refresh-captcha').listener();
  assert.equal(f.submit.disabled, false);
});

test('註冊429等待期間更新驗證碼仍不能提前重送，到期才恢復', async () => {
  const f = await fixture({ mode: 'register', realSubmit: true });
  await f.initialize();
  f.state.write = async () => {
    throw Object.assign(new Error('請稍後再試'), { retryAfter: 30 });
  };
  await f.send();
  assert.equal(f.state.submits, 1);
  assert.equal(f.submit.disabled, true);
  await f.captchaNodes.get('#refresh-captcha').listener();
  await f.send();
  assert.equal(f.state.submits, 1);
  assert.equal(f.submit.disabled, true);
  f.state.clock += 30000;
  f.state.timers.at(-1)();
  assert.equal(f.submit.disabled, false);
});
test('過期會員導覽恢復登入按鈕，舊me回應不能恢復已登出名稱', async () => {
  const f = await fixture({ navigation: true });
  const pending = deferred();
  f.state.me = () => pending.promise;
  const old = f.sandbox.memberNavigation();
  f.state.me = async () => {
    throw unauthorized();
  };
  await f.sandbox.memberNavigation();
  pending.resolve({ member: { displayName: '舊身分' } });
  await old;
  const shown = f.node.children;
  assert.equal(shown.length, 1);
  assert.equal(shown[0].href, '/login.html');
});

test('註冊頁顯示後台設定的條款版本名稱，送出同意時仍用內部版本代碼', async () => {
  const f = await fixture({ mode: 'register' });
  f.state.termsLabel = '2026 年版會員條款';
  f.state.captcha = async () => ({ challengeToken: 'challenge', imageDataUrl: 'data:image/png;base64,test' });
  await f.initialize();
  assert.equal(f.captchaNodes.get('[data-terms-version]').textContent, '2026 年版會員條款');
  await f.send();
  assert.equal(f.state.registered?.termsVersion, 'fixture-v1');
});
