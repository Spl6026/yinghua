import test from 'node:test';
import assert from 'node:assert/strict';
import { loadWithSandbox } from './support/sandbox-module.mjs';

async function fixture({ loaded = false } = {}) {
  const calls = { render: 0 },
    listeners = {};
  let writable = true;
  const sandbox = {
    window: { addEventListener: (type, fn) => (listeners[type] = fn) },
    document: { querySelector: () => null },
  };
  const { createFormState } = await loadWithSandbox('core/admin-form.js', sandbox);
  const state = createFormState({ writable: () => writable, loaded, render: () => calls.render++ });
  return { state, calls, listeners, deny: () => (writable = false) };
}

test('編輯表單載入完成前不可操作，載入後可編輯且清除未儲存標記', async () => {
  const { state } = await fixture();
  assert.equal(state.available(), false);
  state.markDirty();
  await state.load(async () => ({ name: 'A' }), { apply: () => {}, onError: assert.fail });
  assert.equal(state.available(), true);
  assert.equal(state.dirty, false);
});

test('連續載入只採用最後一次結果，較慢的舊回應不會套用', async () => {
  const { state } = await fixture();
  const applied = [];
  let releaseFirst;
  const first = state.load(() => new Promise(resolve => (releaseFirst = resolve)), {
    apply: value => applied.push(value),
    onError: assert.fail,
  });
  await state.load(async () => 'second', { apply: value => applied.push(value), onError: assert.fail });
  releaseFirst('first');
  await first;
  assert.deepEqual(applied, ['second']);
  assert.equal(state.busy, false);
});

test('run 期間鎖住表單，失敗後也會解除並重畫', async () => {
  const { state, calls } = await fixture({ loaded: true });
  await assert.rejects(
    state.run(async () => {
      assert.equal(state.available(), false);
      throw new Error('失敗');
    })
  );
  assert.equal(state.busy, false);
  assert.ok(calls.render >= 2);
  assert.equal(state.available(), true);
});

test('儲存完成後停在完成狀態；沒有寫入權限時一律不可操作', async () => {
  const { state, deny } = await fixture({ loaded: true });
  state.complete();
  assert.equal(state.available(), false);
  state.reset();
  assert.equal(state.available(), true);
  deny();
  assert.equal(state.available(), false);
});

test('未儲存的修改會在離開頁面時提醒，不重畫的標記也算', async () => {
  const { state, listeners, calls } = await fixture({ loaded: true });
  const before = calls.render;
  state.markDirty({ refresh: false });
  assert.equal(calls.render, before);
  const event = { preventDefault() {} };
  listeners.beforeunload(event);
  assert.equal(event.returnValue, '');
  state.markSaved();
  const clean = {};
  listeners.beforeunload({ ...clean, preventDefault: assert.fail });
});
