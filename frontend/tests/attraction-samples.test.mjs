import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';
import { applyAttractionSample } from '../assets/js/core/attraction-sample.js';

const source = (
  await readFile(new URL('../assets/js/components/attraction-samples.js', import.meta.url), 'utf8')
)
  .replace(/^import\b[\s\S]*?;[ \t]*\r?\n/gm, '')
  .replace(/\bexport /g, '');
class Control {
  constructor() {
    this.value = '';
    this.children = [];
    this.listeners = new Map();
    this.disabled = false;
  }
  append(child) {
    this.children.push(child);
  }
  replaceChildren(...children) {
    this.children = children;
    this.value = children[0]?.value || '';
  }
  addEventListener(type, handler) {
    this.listeners.set(type, handler);
  }
  emit(type) {
    return this.listeners.get(type)?.();
  }
  focus() {
    this.focused = true;
  }
}
function setup() {
  const names = [
    'name',
    'category',
    'district',
    'typeLabel',
    'recommendationLabel',
    'summary',
    'description',
    'address',
    'phone',
    'transport',
    'websiteUrl',
    'mapUrl',
    'latitude',
    'longitude',
    'hours',
    'googlePlaceId',
    'slug',
    'mediaId',
    'isMuseum',
    'status',
  ];
  const form = {
    elements: Object.fromEntries(
      names.map(name => [name, Object.assign(new Control(), { value: 'original-' + name })])
    ),
  };
  const select = new Control(),
    apply = new Control(),
    reload = new Control(),
    message = new Control();
  const nodes = {
    select,
    '#apply-place-sample': apply,
    '#load-place-samples': reload,
    '[role="status"]': message,
  };
  const item = {
    id: 'sample',
    name: '既有範例',
    category: '景點',
    district: '東區',
    summary: '目前 DB 文案',
    phone: '03-1234567',
    openingHours: { days: [] },
    googlePlaceId: 'do-not-copy',
    slug: 'do-not-copy',
    mediaId: 'do-not-copy',
    isMuseum: true,
    status: 'published',
  };
  const state = { allowed: true, confirm: true, applied: 0, requests: 0 };
  const sandbox = {
    applyAttractionSample,
    attractionsApi: {
      samples: async () => {
        state.requests++;
        return state.read ? state.read() : { items: [item] };
      },
    },
    element: (tag, attrs, text) => ({ ...attrs, textContent: text }),
    status: (node, text, error) => {
      node.textContent = text;
      node.error = error;
    },
    handleAdminError: (error, node) => {
      node.textContent = error.message;
      node.error = true;
    },
    confirmAction: async () => (typeof state.confirm === 'function' ? state.confirm() : state.confirm),
  };
  vm.createContext(sandbox);
  vm.runInContext(source, sandbox);
  const picker = sandbox.attractionSamplePicker(
    { querySelector: key => nodes[key] },
    form,
    {
      load: (...args) => {
        state.hours = args;
      },
    },
    () => state.allowed,
    () => {
      state.applied++;
    }
  );
  picker.setDisabled(false);
  return { form, select, apply, reload, message, state, picker, item };
}

test('範例經明確選取及確認後才試填，不複製識別、圖片與發布設定', async () => {
  const f = setup();
  await f.picker.load();
  assert.equal(f.state.applied, 0);
  assert.equal(f.apply.disabled, true);
  f.select.value = 'sample';
  f.select.emit('change');
  await f.apply.emit('click');
  assert.equal(f.form.elements.name.value, '既有範例');
  assert.equal(f.form.elements.summary.value, '目前 DB 文案');
  assert.equal(f.form.elements.phone.value, '03-1234567');
  assert.equal(f.form.elements.googlePlaceId.value, '');
  for (const name of ['slug', 'mediaId', 'isMuseum', 'status'])
    assert.equal(f.form.elements[name].value, 'original-' + name);
  assert.deepEqual(f.state.hours, [f.item.openingHours, undefined]);
  assert.equal(f.state.applied, 1);
  assert.equal(f.form.elements.name.focused, true);
  assert.match(f.message.textContent, /尚未儲存/);
});
test('取消確認保留原輸入與關聯', async () => {
  const f = setup();
  await f.picker.load();
  f.select.value = 'sample';
  f.state.confirm = false;
  await f.apply.emit('click');
  assert.equal(f.state.applied, 0);
  assert.equal(f.form.elements.name.value, 'original-name');
  assert.equal(f.form.elements.googlePlaceId.value, 'original-googlePlaceId');
});
test('載入失敗可重試，空清單不阻擋人工輸入', async () => {
  const f = setup();
  f.state.read = async () => {
    throw new Error('讀取失敗');
  };
  await f.picker.load();
  assert.equal(f.apply.disabled, true);
  assert.equal(f.reload.disabled, false);
  assert.equal(f.message.error, true);
  f.state.read = async () => ({ items: [] });
  await f.reload.emit('click');
  assert.match(f.message.textContent, /請直接填寫/);
  assert.equal(f.state.requests, 2);
  assert.equal(f.form.elements.name.value, 'original-name');
});
test('唯讀、忙碌或確認途中失去可寫狀態都不套用', async () => {
  const f = setup();
  await f.picker.load();
  f.select.value = 'sample';
  f.picker.setDisabled(true);
  await f.apply.emit('click');
  assert.equal(f.state.applied, 0);
  f.picker.setDisabled(false);
  f.state.allowed = false;
  await f.apply.emit('click');
  assert.equal(f.state.applied, 0);
  f.state.allowed = true;
  f.state.confirm = async () => {
    f.picker.setDisabled(true);
    return true;
  };
  await f.apply.emit('click');
  assert.equal(f.state.applied, 0);
  assert.equal(f.form.elements.name.value, 'original-name');
});
test('確認期間連點不會重複套用', async () => {
  const f = setup();
  await f.picker.load();
  f.select.value = 'sample';
  let resolve;
  f.state.confirm = () =>
    new Promise(done => {
      resolve = done;
    });
  const first = f.apply.emit('click');
  await f.apply.emit('click');
  resolve(true);
  await first;
  assert.equal(f.state.applied, 1);
});
