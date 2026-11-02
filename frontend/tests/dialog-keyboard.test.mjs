import test from 'node:test';
import assert from 'node:assert/strict';
import { loadWithSandbox } from './support/sandbox-module.mjs';

async function setup() {
  const handlers = new Map();
  const document = {
    activeElement: null,
    querySelector: selector => (selector === '#confirm-dialog' ? dialog : { textContent: '' }),
  };
  const button = name => ({
    name,
    focus() {
      document.activeElement = this;
    },
  });
  const cancel = button('cancel'),
    confirm = button('confirm'),
    trigger = button('trigger');
  const dialog = {
    returnValue: '',
    querySelectorAll: () => [cancel, confirm],
    addEventListener(name, fn) {
      handlers.set(name, fn);
    },
    removeEventListener(name) {
      handlers.delete(name);
    },
    showModal() {
      cancel.focus();
    },
  };
  const context = { document, Promise };
  Object.assign(context, await loadWithSandbox('components/dialog.js', context));
  const result = context.confirmAction('確認操作', '測試提示', trigger);
  return { document, dialog, handlers, cancel, confirm, trigger, result };
}
test('確認框的正反向最外側 Tab 保留在框內', async () => {
  const s = await setup();
  let prevented = 0;
  s.handlers.get('keydown')({
    key: 'Tab',
    shiftKey: true,
    preventDefault() {
      prevented++;
    },
  });
  assert.equal(s.document.activeElement, s.confirm);
  s.handlers.get('keydown')({
    key: 'Tab',
    shiftKey: false,
    preventDefault() {
      prevented++;
    },
  });
  assert.equal(s.document.activeElement, s.cancel);
  assert.equal(prevented, 2);
});
test('正常框內 Tab 與 Escape 仍交給瀏覽器處理', async () => {
  const s = await setup();
  let prevented = 0;
  for (const key of ['Tab', 'Escape'])
    s.handlers.get('keydown')({
      key,
      shiftKey: false,
      preventDefault() {
        prevented++;
      },
    });
  assert.equal(prevented, 0);
});
test('取消關閉會移除鍵盤監聽並返回觸發元素，不確認刪除', async () => {
  const s = await setup();
  s.handlers.get('close')();
  assert.equal(await s.result, false);
  assert.equal(s.document.activeElement, s.trigger);
  assert.equal(s.handlers.has('keydown'), false);
});
