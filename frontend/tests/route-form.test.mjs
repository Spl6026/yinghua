import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';
const code = (
  await Promise.all(
    ['core/admin-form.js', 'pages/route-form.js'].map(path =>
      readFile(new URL('../assets/js/' + path, import.meta.url), 'utf8')
    )
  )
)
  .join('\n')
  .replace(/^import\b[\s\S]*?;[ \t]*\r?\n/gm, '')
  .replace(/\bexport /g, '');
const settle = async () => {
  for (let i = 0; i < 8; i++) await new Promise(resolve => setImmediate(resolve));
};
class Node {
  constructor(tag = 'div') {
    this.tag = tag;
    this.children = [];
    this.dataset = {};
    this.value = '';
    this.disabled = false;
    this.attrs = {};
    this.events = {};
  }
  append(...nodes) {
    this.children.push(...nodes);
  }
  replaceChildren(...nodes) {
    this.children = nodes;
  }
  addEventListener(type, fn) {
    this.events[type] = fn;
  }
  emit(type, event = {}) {
    return this.events[type]?.({ preventDefault() {}, ...event });
  }
  querySelectorAll(selector) {
    const all = this.children.flatMap(n => [n, ...(n.querySelectorAll?.('*') || [])]);
    if (selector === '*') return all;
    if (selector === '[data-move]') return all.filter(n => n.dataset?.move);
    if (selector === 'button') return all.filter(n => n.tag === 'button');
    return all.filter(n => ['input', 'select', 'textarea', 'button'].includes(n.tag));
  }
  querySelector(selector) {
    return this.querySelectorAll('*').find(n => n.tag === selector);
  }
  focus() {
    this.focused = true;
  }
}
function fixture({ creating = false, preview = false, view = true } = {}) {
  const nodes = new Map(),
    get = (key, tag = 'div') => {
      if (!nodes.has(key)) nodes.set(key, new Node(tag));
      return nodes.get(key);
    };
  const form = get('#route-form', 'form');
  form.elements = { namedItem: key => form.elements[key] };
  for (const name of ['title', 'theme', 'summary', 'durationMinutes', 'sortOrder', 'status']) {
    const input = new Node('input');
    input.name = name;
    input.value = name === 'status' ? 'draft' : name === 'sortOrder' ? '0' : '';
    form.elements[name] = input;
    form.append(input);
  }
  const save = new Node('button');
  save.type = 'submit';
  form.append(save);
  for (const key of [
    '#route-stops',
    '#route-candidates',
    '#route-place-query',
    '#route-place-previous',
    '#route-place-next',
    '#route-place-search',
    '#route-reload',
  ])
    form.append(
      get(
        key,
        key.includes('query')
          ? 'input'
          : key.includes('stops') || key.includes('candidates')
            ? 'div'
            : 'button'
      )
    );
  const state = { confirm: true, saves: [], gets: 0, assignments: [], hasNext: false };
  const record = {
    id: 'route',
    title: '已存路線',
    theme: '',
    summary: '',
    sortOrder: 0,
    status: 'draft',
    durationMinutes: null,
    version: 1,
    stops: [
      { attractionId: 'a', name: '第一站', suggestedTime: null, note: '' },
      { attractionId: 'b', name: '第二站', suggestedTime: '10:30', note: '休息' },
    ],
  };
  const sandbox = {
    URLSearchParams,
    Number,
    location: { search: '?id=route', assign: path => state.assignments.push(path) },
    document: { querySelector: key => get(key) },
    service: {
      get: async () => {
        state.gets++;
        if (state.getError) throw new Error('讀取失敗');
        return { record };
      },
      candidates: async () => ({
        items: [
          { id: 'a', name: '第一站' },
          { id: 'c', name: '第三站' },
        ],
        hasNext: state.hasNext,
      }),
      save: async (id, body) => {
        state.saves.push({ id, body });
        if (state.error) throw new Error('版本衝突');
        return {
          record: { ...record, id: 'saved' },
          publication: { status: state.pending ? 'failed' : 'succeeded', message: '已保存' },
        };
      },
    },
    hasPermission: (_, module, action) => action !== 'view' || view,
    adminDestination: () => '/admin/route-add.html',
    handleAdminError: (error, node) => {
      state.handled = error;
      node.focus();
    },
    element: (tag, attrs = {}, text) => {
      const n = new Node(tag);
      n.textContent = text;
      for (const [k, v] of Object.entries(attrs)) {
        if (k.startsWith('data-')) n.dataset[k.slice(5)] = v;
        else n[k] = v;
      }
      return n;
    },
    status: (n, text) => {
      n.textContent = text;
    },
    formErrors() {},
    unsavedChanges() {},
    confirmAction: async () => state.confirm,
    submitForm: async (_, action, options = {}) => {
      try {
        await action();
      } catch (error) {
        options.onError?.(error);
        get('#route-form-status').textContent = error.message;
      }
    },
  };
  vm.createContext(sandbox);
  vm.runInContext(code, sandbox);
  return {
    state,
    form,
    save,
    get,
    initialize: () =>
      sandbox.initializeRouteForm({ preview: { active: preview } }, creating ? 'route-add' : 'route-edit'),
  };
}
test('站點上移保留時間備註，邊界禁用且送出新次序', async () => {
  const f = fixture();
  await f.initialize();
  const stops = f.get('#route-stops');
  assert.equal(stops.children[0].querySelectorAll('button')[0].disabled, true);
  await stops.children[1].querySelectorAll('button')[0].emit('click');
  await f.form.emit('submit');
  assert.deepEqual(
    Array.from(f.state.saves[0].body.stops, x => x.attractionId),
    ['b', 'a']
  );
  assert.equal(f.state.saves[0].body.stops[0].suggestedTime, '10:30');
  assert.equal(f.state.saves[0].body.stops[0].note, '休息');
});
test('共同控制項刷新後景點首末頁仍禁用，重複景點不能再加', async () => {
  const f = fixture();
  await f.initialize();
  assert.equal(f.get('#route-place-previous').disabled, true);
  assert.equal(f.get('#route-place-next').disabled, true);
  assert.equal(f.get('#route-candidates').children[0].disabled, true);
  await f.get('#route-candidates').children[1].emit('click');
  assert.equal(f.get('#route-place-previous').disabled, true);
  assert.equal(f.get('#route-place-next').disabled, true);
  await f.form.emit('submit');
  assert.equal(f.state.saves[0].body.stops.length, 3);
});
test('202 保存後不能再次新增，只有新增權限返回可用頁', async () => {
  const f = fixture({ creating: true, view: false });
  f.state.pending = true;
  await f.initialize();
  await f.get('#route-candidates').children[0].emit('click');
  await f.form.emit('submit');
  await f.form.emit('submit');
  assert.equal(f.state.saves.length, 1);
  assert.equal(f.save.disabled, true);
  assert.equal(f.get('#route-cancel').href, '/index.html');
});
test('新增已保存但發布失敗後重載同一筆，再送出必須更新而不重複新增', async () => {
  const f = fixture({ creating: true });
  f.state.pending = true;
  await f.initialize();
  await f.get('#route-candidates').children[0].emit('click');
  await f.form.emit('submit');
  await f.get('#route-reload').emit('click');
  assert.equal(f.form.elements.title.value, '已存路線');
  assert.equal(f.save.disabled, false);
  await f.form.emit('submit');
  assert.equal(f.state.saves[1].id, 'saved');
  assert.equal(f.state.saves[1].body.version, 1);
});
test('409 保留輸入，取消重載不遺失；同意後讀取伺服器版本', async () => {
  const f = fixture();
  await f.initialize();
  f.form.elements.title.value = '保留修改';
  await f.form.emit('input');
  f.state.error = true;
  await f.form.emit('submit');
  assert.equal(f.form.elements.title.value, '保留修改');
  assert.equal(f.save.disabled, false);
  f.state.confirm = false;
  await f.get('#route-reload').emit('click');
  assert.equal(f.state.gets, 1);
  f.state.confirm = true;
  await f.get('#route-reload').emit('click');
  assert.equal(f.form.elements.title.value, '已存路線');
});
test('唯讀模擬不允許修改，讀取失敗鎖定後可重試', async () => {
  const ro = fixture({ preview: true });
  await ro.initialize();
  await ro.form.emit('submit');
  assert.equal(ro.state.saves.length, 0);
  assert.equal(ro.save.disabled, true);
  const f = fixture();
  f.state.getError = true;
  await f.initialize();
  assert.equal(f.save.disabled, true);
  f.state.getError = false;
  await f.get('#route-reload').emit('click');
  assert.equal(f.save.disabled, false);
});

test('儲存遇到錯誤時交給後台共用處理（登入逾時會導回登入頁），與其他表單一致', async () => {
  const f = fixture();
  await f.initialize();
  f.form.elements.title.value = '修改';
  await f.form.emit('input');
  f.state.error = true;
  await f.form.emit('submit');
  assert.equal(f.state.handled?.message, '版本衝突');
});
