import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import { readFile } from 'node:fs/promises';

const sources = await Promise.all(
  ['core/ui.js', 'components/google-place-link.js', 'components/place-reference.js'].map(path =>
    readFile(new URL('../assets/js/' + path, import.meta.url), 'utf8')
  )
);
class Node {
  constructor(tag = 'div') {
    this.tag = tag;
    this.children = [];
    this.attrs = {};
    this.dataset = {};
    this.value = '';
    this.events = {};
  }
  setAttribute(key, value) {
    this.attrs[key] = value;
    if (key === 'id') this.id = value;
  }
  getAttribute(key) {
    return this.attrs[key];
  }
  removeAttribute(key) {
    delete this.attrs[key];
  }
  append(...children) {
    for (const child of children) {
      child.parent = this;
      this.children.push(child);
    }
  }
  replaceChildren(...children) {
    this.children = [];
    this.append(...children);
  }
  addEventListener(name, fn) {
    this.events[name] = fn;
  }
  emit(name) {
    return this.events[name]?.({ type: name, currentTarget: this });
  }
  dispatchEvent(event) {
    this.events[event.type]?.(event);
  }
  focus() {
    this.focused = true;
  }
  closest() {
    return this.parent;
  }
  remove() {
    this.parent.children = this.parent.children.filter(node => node !== this);
  }
}
function fixture() {
  const group = new Node(),
    reference = new Node(),
    parent = new Node(),
    form = new Node('form');
  parent.append(group);
  group.id = 'place-link';
  group.dataset.fieldName = 'googlePlaceId';
  form.elements = Object.fromEntries(
    Object.entries({
      name: '原名稱',
      address: '原地址',
      latitude: '24.8',
      longitude: '120.97',
      googlePlaceId: 'ChIJ_original',
    }).map(([name, value]) => {
      const node = new Node('input');
      node.value = value;
      return [name, node];
    })
  );
  form.elements.namedItem = name => form.elements[name];
  form.querySelectorAll = selector => (selector === '[data-field-name]' ? [group] : []);
  const state = { confirm: true, detached: 0, events: 0 };
  group.addEventListener('input', () => state.events++);
  const sandbox = {
    HTMLElement: Node,
    document: { createElement: tag => new Node(tag) },
    Event: class {
      constructor(type) {
        this.type = type;
      }
    },
    confirmAction: async () => (state.wait ? state.wait() : state.confirm),
  };
  vm.createContext(sandbox);
  sources.forEach(source =>
    vm.runInContext(source.replace(/^import\b[\s\S]*?;[ \t]*\r?\n/gm, '').replace(/\bexport /g, ''), sandbox)
  );
  const link = sandbox.googlePlaceLink(form, group, () => state.detached++);
  link.setDisabled(false);
  link.load();
  return {
    link,
    form,
    group,
    reference,
    state,
    sandbox,
    confirm: group.children[1],
    details: group.children[0],
    message: group.children[0].children[0].children[0],
    detach: group.children[0].children[1].children[1],
  };
}
test('改地址後必須確認；取消保留待確認狀態，確認後命令為 true', async () => {
  const f = fixture();
  assert.equal(f.link.canSave(), true);
  f.form.elements.address.value = '新地址';
  f.form.elements.address.emit('input');
  assert.equal(f.link.canSave(), false);
  f.state.confirm = false;
  await f.confirm.emit('click');
  assert.equal(f.link.canSave(), false);
  f.state.confirm = true;
  await f.confirm.emit('click');
  assert.equal(f.link.canSave(), true);
  assert.equal(f.link.confirmed(), true);
  assert.equal(f.state.events, 1);
});
test('修改再還原以及座標相同表示法，不會誤判為另個地點', () => {
  const f = fixture();
  f.form.elements.latitude.value = '24.8000000';
  f.form.elements.latitude.emit('input');
  assert.equal(f.link.canSave(), true);
  f.form.elements.name.value = '新名稱';
  f.form.elements.name.emit('input');
  assert.equal(f.link.canSave(), false);
  f.form.elements.name.value = '原名稱';
  f.form.elements.name.emit('input');
  assert.equal(f.link.canSave(), true);
});
test('唯讀不能解除，解除後不送舊 ID 並清除參考區', () => {
  const f = fixture();
  f.link.setDisabled(true);
  f.detach.emit('click');
  assert.equal(f.form.elements.googlePlaceId.value, 'ChIJ_original');
  assert.equal(f.state.detached, 0);
  f.link.setDisabled(false);
  f.detach.emit('click');
  assert.equal(f.form.elements.googlePlaceId.value, '');
  assert.equal(f.link.confirmed(), false);
  assert.equal(f.state.detached, 1);
  assert.equal(f.group.hidden, true);
  assert.equal(f.form.elements.name.focused, true);
});
test('確認對話框等待時資料改動，不能把未看過的變更算成已確認', async () => {
  const f = fixture();
  f.form.elements.address.value = '地址一';
  f.form.elements.address.emit('input');
  let resolve;
  f.state.wait = () =>
    new Promise(yes => {
      resolve = yes;
    });
  const pending = f.confirm.emit('click');
  f.form.elements.address.value = '地址二';
  f.form.elements.address.emit('input');
  resolve(true);
  await pending;
  assert.equal(f.link.canSave(), false);
  assert.equal(f.link.confirmed(), false);
});
test('簡介以文字原文呈現，清除後不留內容，也不寫入網站介紹', () => {
  const f = fixture();
  const text = ' 原文 <script>測試</script>\n保留換行 ';
  f.sandbox.renderPlaceReference(f.reference, {
    name: '地點',
    address: '地址',
    editorialSummary: text,
    attributions: [],
  });
  assert.equal(f.reference.children[3].textContent, text);
  assert.equal(f.reference.children[0].attrs.translate, 'no');
  assert.equal(Object.hasOwn(f.form.elements, 'description'), false);
  f.sandbox.renderPlaceReference(f.reference, null);
  assert.equal(f.reference.hidden, true);
  assert.equal(f.reference.children.length, 0);
});
test('關聯驗證錯誤優先聚焦可見群組，避免落在隱藏 Place ID', () => {
  const f = fixture();
  const ok = f.sandbox.formErrors(f.form, { fieldErrors: { googlePlaceId: '請重新確認' } });
  assert.equal(ok, true);
  assert.equal(f.group.focused, true);
  assert.equal(f.group.attrs['aria-invalid'], 'true');
  assert.equal(f.form.elements.googlePlaceId.focused, undefined);
});

test('人工景點隱藏關聯區；選用候選才出現，識別碼預設收合', () => {
  const f = fixture();
  assert.equal(f.group.hidden, false);
  assert.equal(Boolean(f.details.open), false);
  f.form.elements.googlePlaceId.value = '';
  f.link.load();
  assert.equal(f.group.hidden, true);
  assert.equal(f.message.textContent, '');
  f.form.elements.googlePlaceId.value = 'ChIJ_selected';
  f.link.acceptCandidate();
  assert.equal(f.group.hidden, false);
  assert.equal(f.link.confirmed(), true);
  f.form.elements.name.value = '修改名稱';
  f.form.elements.name.emit('input');
  assert.equal(f.group.hidden, false);
  assert.equal(f.confirm.hidden, false);
  assert.equal(f.message.dataset.error, 'true');
  f.details.open = true;
  f.detach.emit('click');
  assert.equal(f.details.open, false);
});
