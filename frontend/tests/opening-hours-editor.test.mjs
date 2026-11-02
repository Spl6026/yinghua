import test from 'node:test';
import assert from 'node:assert/strict';
import { emptyOpeningHours, openingWeek, openingDayNames } from '../assets/js/core/opening-hours.js';
import { loadWithSandbox } from './support/sandbox-module.mjs';

class Node {
  constructor(tag) {
    this.tag = tag;
    this.children = [];
    this.events = {};
    this.value = '';
    this.checked = false;
    this.disabled = false;
    this.attrs = {};
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
  setAttribute(key, value) {
    this.attrs[key] = value;
    this[key] = value;
  }
  addEventListener(type, fn) {
    this.events[type] = fn;
  }
  dispatchEvent(event) {
    this.events[event.type]?.(event);
  }
  emit(type) {
    this.events[type]?.({ type });
  }
  remove() {
    this.parent.children = this.parent.children.filter(node => node !== this);
  }
  focus() {
    this.focused = true;
  }
}
async function fixture() {
  const container = new Node('div'),
    events = [];
  container.addEventListener('input', () => events.push('input'));
  const element = (tag, attrs = {}, text) => {
    const node = new Node(tag);
    for (const [key, value] of Object.entries(attrs)) node.setAttribute(key, value);
    node.textContent = text;
    return node;
  };
  const context = {
    element,
    emptyOpeningHours,
    openingWeek,
    openingDayNames,
    Event: class {
      constructor(type) {
        this.type = type;
      }
    },
    document: { createTextNode: text => ({ text }) },
  };
  Object.assign(context, await loadWithSandbox('components/opening-hours-editor.js', context));
  const editor = context.openingHoursEditor(container);
  const all = () => {
    const walk = node => [node, ...(node.children || []).flatMap(walk)];
    return walk(container);
  };
  const find = name => all().find(node => node.name === name || node.attrs?.['aria-label'] === name);
  return { editor, events, all, find };
}
const plain = value => JSON.parse(JSON.stringify(value));

test('手動週表可填午休與跨日，讀取為 API 的同一格式', async () => {
  const f = await fixture();
  const state = f.find('openingDay1');
  state.value = 'periods';
  state.emit('change');
  f.find('opening-1-1-opens').value = '09:00';
  f.find('opening-1-1-closes').value = '12:00';
  f.find('週一新增營業時段').emit('click');
  f.find('opening-1-2-opens').value = '22:00';
  f.find('opening-1-2-closes').value = '02:00';
  f.find('opening-1-2-nextDay').checked = true;
  assert.deepEqual(plain(f.editor.read().days[1].periods), [
    { opens: '09:00', closes: '12:00', nextDay: false },
    { opens: '22:00', closes: '02:00', nextDay: true },
  ]);
  assert.equal(f.events.length, 2);
});
test('舊文字只進備註；Google 結構資料讀回後可人工修改', async () => {
  const f = await fixture();
  f.editor.load(null, '原始時間');
  assert.equal(f.editor.read().note, '原始時間');
  assert.ok(f.editor.read().days.every(day => day.status === 'unknown'));
  const imported = emptyOpeningHours();
  imported.days[2] = {
    day: 2,
    status: 'periods',
    periods: [{ opens: '10:00', closes: '16:00', nextDay: false }],
  };
  f.editor.load(imported);
  f.find('opening-2-1-closes').value = '17:00';
  assert.equal(f.editor.read().days[2].periods[0].closes, '17:00');
});
test('改為公休不送出隱藏時段，也不讓隱藏必填阻止保存', async () => {
  const f = await fixture();
  const state = f.find('openingDay1');
  state.value = 'periods';
  state.emit('change');
  state.value = 'closed';
  state.emit('change');
  assert.deepEqual(plain(f.editor.read().days[1]), { day: 1, status: 'closed', periods: [] });
  assert.equal(f.find('opening-1-1-opens').disabled, true);
});
test('唯讀及儲存鎖定不能新增或編輯；重載後仍保持鎖定', async () => {
  const f = await fixture();
  f.find('openingDay1').value = 'periods';
  f.find('openingDay1').emit('change');
  f.editor.setDisabled(true);
  f.editor.load(f.editor.read());
  assert.ok(
    f
      .all()
      .filter(node => ['input', 'select', 'textarea', 'button'].includes(node.tag))
      .every(node => node.disabled)
  );
  f.find('週一新增營業時段').emit('click');
  assert.equal(f.editor.read().days[1].periods.length, 1);
  f.editor.setDisabled(false);
  assert.equal(f.find('openingDay1').disabled, false);
});
test('每日最多四段；移除時段後可再新增並保留焦點', async () => {
  const f = await fixture();
  f.find('openingDay1').value = 'periods';
  f.find('openingDay1').emit('change');
  const add = f.find('週一新增營業時段');
  for (let i = 0; i < 5; i++) add.emit('click');
  assert.equal(f.editor.read().days[1].periods.length, 4);
  assert.equal(add.disabled, true);
  f.find('週一移除此營業時段').emit('click');
  assert.equal(f.editor.read().days[1].periods.length, 3);
  assert.equal(add.disabled, false);
  assert.equal(add.focused, true);
});
