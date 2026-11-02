import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';

// 執行實際頁面模組；只替換 DOM、導航及 API 邊界，驗證非同步控制狀態。
const sources = await Promise.all(
  ['core/ui.js', 'core/session.js', 'pages/admin-form.js', 'pages/permissions.js'].map(async path =>
    (await readFile(new URL('../assets/js/' + path, import.meta.url), 'utf8'))
      .replace(/^import\b[\s\S]*?;[ \t]*\r?\n/gm, '')
      .replace(/\bexport /g, '')
  )
);
const settle = async () => {
  for (let i = 0; i < 5; i++) await new Promise(resolve => setImmediate(resolve));
};
function deferred() {
  let resolve;
  let reject;
  const promise = new Promise((yes, no) => {
    resolve = yes;
    reject = no;
  });
  return { promise, resolve, reject };
}
class DomNode {
  constructor(tag = 'div') {
    this.tag = tag;
    this.attrs = {};
    this.listeners = new Map();
    this.children = [];
    this.disabled = false;
    this.checked = false;
    this.textContent = '';
    this.dataset = {};
    this._value = '';
    this.classList = { add() {}, remove() {}, toggle() {} };
  }
  setAttribute(key, value) {
    this.attrs[key] = value;
    if (key === 'value') this.value = value;
    if (key === 'type') this.type = value;
  }
  removeAttribute(key) {
    delete this.attrs[key];
  }
  append(...children) {
    children.forEach(child => {
      child.parent = this;
      this.children.push(child);
    });
    if (this.tag === 'select' && !this._value && this.children.length) this.value = this.children[0].value;
  }
  replaceChildren(...children) {
    this.children = [];
    this.append(...children);
  }
  addEventListener(type, callback) {
    this.listeners.set(type, callback);
  }
  emit(type) {
    return this.listeners.get(type)?.({ preventDefault() {}, currentTarget: this });
  }
  get value() {
    return this._value;
  }
  set value(value) {
    this._value = value;
  }
  get selectedOptions() {
    return this.children.filter(child => child.value === this.value);
  }
  descendants() {
    return this.children.flatMap(child => [child, ...child.descendants()]);
  }
  querySelectorAll(selector) {
    if (selector === 'input,select')
      return this.descendants().filter(node => ['input', 'select'].includes(node.tag));
    if (selector === '[aria-invalid]') return this.descendants().filter(node => node.attrs['aria-invalid']);
    if (selector === '.field-error')
      return this.descendants().filter(node => node.className === 'field-error');
    return [];
  }
  closest() {
    return this.parent;
  }
  focus() {
    this.focused = true;
  }
  remove() {
    this.parent.children = this.parent.children.filter(child => child !== this);
  }
}
function fixture(module, identity = {}, creating = false) {
  const nodes = new Map();
  const node = (selector, tag = 'div') => {
    if (!nodes.has(selector)) nodes.set(selector, new DomNode(tag));
    return nodes.get(selector);
  };
  const form = node(module === 'admins' ? '#admin-form' : '#permissions-form', 'form');
  const save = node('#save', 'button');
  const message = node('#message', 'p');
  const reload = node(module === 'admins' ? '#reload-admin' : '#reload-matrix', 'button');
  const target = node('#target', 'select');
  const template = node('#template', 'select');
  node('#permission-rows', 'tbody');
  node('#apply-template', 'button');
  node('#permission-title');
  node('#permission-summary');
  node('#root-note');
  node('#toast');
  node('#admin-cancel');
  node('#admin-another');
  form.elements = {};
  for (const name of ['account', 'displayName', 'email', 'roleId', 'status']) {
    const input = new DomNode(name === 'roleId' || name === 'status' ? 'select' : 'input');
    input.name = name;
    input.id = name;
    form.elements[name] = input;
    form.append(input);
  }
  Object.defineProperty(form.elements, 'namedItem', { value: name => form.elements[name] });
  form.querySelector = selector =>
    selector === '[type="submit"]' ? save : selector === '.form-status' ? message : null;
  const rights = ['view', 'create', 'edit', 'delete'].map(action => ({ module: 'permissions', action }));
  const matrix = (id, version = 1) => ({
    adminId: id,
    version,
    isRoot: id === 'root',
    permissions: rights.map(right => ({ ...right, allowed: true })),
  });
  const admin = (displayName = '原名稱', version = 1) => ({
    id: 'a',
    account: 'editor',
    displayName,
    email: 'editor@example.test',
    roleId: 'viewer',
    status: 'active',
    version,
    isRoot: false,
  });
  const state = {
    confirmation: true,
    confirmations: [],
    getCalls: 0,
    reads: [],
    saves: [],
    replacements: [],
    assignments: [],
    windowEvents: new Map(),
    timers: [],
    get: null,
    read: null,
    write: null,
  };
  const sandbox = {
    URLSearchParams,
    HTMLElement: DomNode,
    recoverSession(kind) {
      state.recoveredKind = kind;
      return Boolean(state.recoverable);
    },
    window: {
      setTimeout(callback) {
        state.timers.push(callback);
      },
      addEventListener(type, callback) {
        state.windowEvents.set(type, callback);
      },
    },
    document: {
      createElement: tag => new DomNode(tag),
      querySelector: selector => nodes.get(selector) || null,
    },
    location: {
      search: '?id=a',
      pathname: '/admin/example.html',
      replace(path) {
        state.replacements.push(path);
      },
      assign(path) {
        state.assignments.push(path);
      },
    },
    history: { replaceState() {} },
    passwordError: () => '',
    routes: new Set(),
    FormData: class {
      constructor(source) {
        this.source = source;
      }
      *[Symbol.iterator]() {
        for (const input of Object.values(this.source.elements))
          if (!input.disabled) yield [input.name, input.value];
      }
    },
    async confirmAction(title) {
      state.confirmations.push(title);
      return state.confirmation;
    },
    adminApi: {
      async catalog() {
        return {
          permissions: rights,
          targets: [
            { id: 'a', account: 'editor', displayName: '同名', isRoot: false },
            { id: 'root', account: 'admin', displayName: '同名', isRoot: true },
          ],
        };
      },
      async roles() {
        return { roles: [{ id: 'viewer', name: '唯讀', permissions: matrix('a').permissions }] };
      },
      async get() {
        state.getCalls++;
        return state.get ? state.get() : { admin: admin() };
      },
      async matrix(id) {
        state.reads.push(id);
        return state.read ? state.read(id) : matrix(id);
      },
      async saveMatrix(id, body) {
        state.saves.push({ id, body });
        if (state.write) await state.write();
      },
      async save(id, body) {
        state.saves.push(id ? { id, body } : { body });
        if (state.write) await state.write();
      },
    },
  };
  vm.createContext(sandbox);
  sources.forEach(source => vm.runInContext(source, sandbox));
  const who = {
    admin: { id: 'operator' },
    permissions: rights.flatMap(right => [
      { ...right, allowed: true },
      { ...right, module: 'admins', allowed: true },
    ]),
    ...identity,
  };
  const initialize = () =>
    module === 'admins'
      ? sandbox.initializeAdminForm(who, creating ? 'admin-add' : 'admin-edit')
      : sandbox.initializePermissions(who);
  const dirty = () => {
    const event = {
      prevented: false,
      preventDefault() {
        this.prevented = true;
      },
    };
    state.windowEvents.get('beforeunload')?.(event);
    return event.prevented;
  };
  const boxes = () =>
    node('#permission-rows')
      .descendants()
      .filter(child => child.tag === 'input');
  return {
    node,
    form,
    save,
    message,
    reload,
    target,
    state,
    initialize,
    admin,
    matrix,
    dirty,
    boxes,
    sandbox,
  };
}

test('管理員讀取失敗後可重新載入並恢復儲存', async () => {
  const f = fixture('admins');
  f.state.get = async () => {
    throw new Error('暫時無法讀取');
  };
  await f.initialize();
  assert.equal(f.save.disabled, true);
  f.state.get = async () => ({ admin: f.admin() });
  await f.reload.emit('click');
  assert.equal(f.save.disabled, false);
  assert.equal(f.form.elements.displayName.disabled, false);
  assert.equal(f.message.textContent, '資料已載入。');
});

test('一般管理員帳號可修改並連同版本送出，唯讀模擬仍不能修改', async () => {
  const f = fixture('admins');
  await f.initialize();
  assert.notEqual(f.form.elements.account.readOnly, true);
  f.form.elements.account.value = '新帳號';
  await f.form.emit('submit');
  assert.equal(f.state.saves[0].body.account, '新帳號');
  assert.equal(f.state.saves[0].body.version, 1);
});
test('管理員取消重新載入保留輸入及未儲存狀態', async () => {
  const f = fixture('admins');
  await f.initialize();
  f.form.elements.displayName.value = '未儲存';
  f.form.emit('input');
  f.state.confirmation = false;
  await f.reload.emit('click');
  assert.equal(f.form.elements.displayName.value, '未儲存');
  assert.equal(f.state.getCalls, 1);
  assert.equal(f.dirty(), true);
});
test('管理員確認重新載入才清除未儲存輸入', async () => {
  const f = fixture('admins');
  await f.initialize();
  f.form.elements.displayName.value = '未儲存';
  f.form.emit('input');
  await f.reload.emit('click');
  assert.equal(f.form.elements.displayName.value, '原名稱');
  assert.equal(f.dirty(), false);
});

test('管理員保存欄位錯誤後重新載入，清除過期的提示與無效標記', async () => {
  const f = fixture('admins');
  await f.initialize();
  f.form.elements.email.value = 'duplicate@example.test';
  f.form.emit('input');
  f.state.write = async () => {
    throw Object.assign(new Error('電子郵件已使用'), { fieldErrors: { email: '此資料已使用。' } });
  };
  f.form.emit('submit');
  await settle();
  assert.equal(f.form.querySelectorAll('.field-error').length, 1);
  assert.equal(f.form.elements.email.attrs['aria-invalid'], 'true');
  await f.reload.emit('click');
  assert.equal(f.form.elements.email.value, 'editor@example.test');
  assert.equal(f.form.querySelectorAll('.field-error').length, 0);
  assert.equal(f.form.elements.email.attrs['aria-invalid'], undefined);
  assert.equal(f.form.elements.email.attrs['aria-describedby'], undefined);
});
test('管理員舊回應不能覆蓋較新重新載入結果', async () => {
  const f = fixture('admins');
  await f.initialize();
  const old = deferred();
  const recent = deferred();
  let calls = 0;
  f.state.get = () => (++calls === 1 ? old.promise : recent.promise);
  const first = f.reload.emit('click');
  const second = f.reload.emit('click');
  recent.resolve({ admin: f.admin('較新', 3) });
  await second;
  old.resolve({ admin: f.admin('較舊', 2) });
  await first;
  assert.equal(f.form.elements.displayName.value, '較新');
  assert.equal(f.save.disabled, false);
});
test('管理員唯讀模擬重新載入後仍禁止修改和送出', async () => {
  const f = fixture('admins', { preview: { active: true } });
  await f.initialize();
  await f.reload.emit('click');
  assert.equal(f.save.disabled, true);
  assert.ok(Object.values(f.form.elements).every(input => input.disabled));
  f.form.emit('submit');
  await settle();
  assert.equal(f.state.saves.length, 0);
});
test('管理員儲存遇到401使用後台登入導向', async () => {
  const f = fixture('admins');
  await f.initialize();
  f.state.write = async () => {
    throw Object.assign(new Error('請先登入'), { status: 401 });
  };
  f.form.emit('submit');
  await settle();
  assert.match(f.state.replacements[0], /^\/admin\/login\.html\?returnTo=/);
});

test('已登入管理員逾時會保留編輯內容，恢復後不自動重送', async () => {
  const f = fixture('admins');
  await f.initialize();
  f.state.recoverable = true;
  f.form.elements.displayName.value = '尚未儲存的名稱';
  f.state.write = async () => {
    throw Object.assign(new Error('登入已到期'), { status: 401 });
  };
  f.form.emit('submit');
  await settle();
  assert.equal(f.state.recoveredKind, 'admin');
  assert.equal(f.state.replacements.length, 0);
  assert.equal(f.form.elements.displayName.value, '尚未儲存的名稱');
  assert.equal(f.state.saves.length, 1);
});
test('管理員儲存期間重新載入不能開啟第二次送出', async () => {
  const f = fixture('admins');
  await f.initialize();
  const pending = deferred();
  f.state.write = () => pending.promise;
  f.form.emit('submit');
  await settle();
  await f.reload.emit('click');
  const locked = f.save.disabled;
  f.form.emit('submit');
  await settle();
  const requests = f.state.saves.length;
  pending.resolve();
  await settle();
  assert.equal(locked, true);
  assert.equal(requests, 1);
});
test('權限對象顯示帳號以區分同名管理員', async () => {
  const f = fixture('permissions');
  await f.initialize();
  assert.match(f.target.children[0].textContent, /同名（editor）/);
  assert.match(f.target.children[1].textContent, /同名（admin）/);
});
test('權限儲存期間切到root，完成後仍唯讀且不覆蓋新對象', async () => {
  const f = fixture('permissions');
  await f.initialize();
  const save = deferred();
  f.state.write = () => save.promise;
  f.form.emit('submit');
  await settle();
  assert.equal(f.save.disabled, true);
  f.target.value = 'root';
  await f.target.emit('change');
  save.resolve();
  await settle();
  assert.equal(f.target.value, 'root');
  assert.equal(f.save.disabled, true);
  assert.ok(f.boxes().every(input => input.disabled));
  assert.equal(f.state.saves[0].id, 'a');
  assert.equal(f.node('#permission-summary').textContent, '系統管理員｜完整權限');
});
test('權限儲存後讀取失敗保留提示並要求重新載入', async () => {
  const f = fixture('permissions');
  await f.initialize();
  f.state.read = async () => {
    throw new Error('讀取失敗');
  };
  f.form.emit('submit');
  await settle();
  assert.match(f.message.textContent, /已儲存.*無法重新載入/);
  assert.equal(f.message.dataset.error, 'true');
  assert.equal(f.save.disabled, true);
  f.state.read = async id => f.matrix(id, 2);
  await f.reload.emit('click');
  assert.equal(f.save.disabled, false);
});
test('取消權限對象切換保留修改和原對象', async () => {
  const f = fixture('permissions');
  await f.initialize();
  const checkbox = f.boxes()[0];
  checkbox.checked = false;
  checkbox.emit('change');
  f.state.confirmation = false;
  f.target.value = 'root';
  await f.target.emit('change');
  assert.equal(f.target.value, 'a');
  assert.equal(checkbox.checked, false);
  assert.equal(f.dirty(), true);
});
test('權限唯讀模擬與只有查看權限的帳號不能送出', async () => {
  for (const identity of [
    { preview: { active: true } },
    { permissions: [{ module: 'permissions', action: 'view', allowed: true }] },
  ]) {
    const f = fixture('permissions', identity);
    await f.initialize();
    assert.equal(f.save.disabled, true);
    assert.ok(f.boxes().every(input => input.disabled));
    f.form.emit('submit');
    await settle();
    assert.equal(f.state.saves.length, 0);
  }
});
test('權限儲存遇到401使用後台登入導向', async () => {
  const f = fixture('permissions');
  await f.initialize();
  f.state.write = async () => {
    throw Object.assign(new Error('請先登入'), { status: 401 });
  };
  f.form.emit('submit');
  await settle();
  assert.match(f.state.replacements[0], /^\/admin\/login\.html\?returnTo=/);
});

test('直接開固定管理員編輯頁時，替換表單後不再查找已移除的按鈕', async () => {
  const f = fixture('admins');
  f.form.append(f.reload, f.save, f.message);
  const lookup = f.sandbox.document.querySelector;
  f.sandbox.document.querySelector = selector =>
    selector === '#reload-admin' && !f.form.descendants().includes(f.reload) ? null : lookup(selector);
  f.state.get = async () => ({ admin: { ...f.admin(), isRoot: true } });
  await assert.doesNotReject(f.initialize());
  assert.match(f.form.children[0].textContent, /固定管理員/);
  assert.equal(f.state.saves.length, 0);
});

test('只有新增管理員權限時成功留頁且不能重複送出', async () => {
  const f = fixture('admins', { permissions: [{ module: 'admins', action: 'create', allowed: true }] }, true);
  await f.initialize();
  await f.form.emit('submit');
  await settle();
  assert.equal(f.state.assignments.length, 0);
  assert.match(f.message.textContent, /已儲存/);
  assert.equal(f.node('#admin-another').hidden, false);
  assert.equal(f.save.disabled, true);
  await f.form.emit('submit');
  await settle();
  assert.equal(f.state.saves.length, 1);
});
