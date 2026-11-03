import test from 'node:test';
import assert from 'node:assert/strict';

const windowEvents = new Map(),
  documentEvents = new Map();
let now = 0;
globalThis.window = {
  addEventListener: (name, fn) => windowEvents.set(name, fn),
  setTimeout: () => 0,
  clearTimeout() {},
};
globalThis.document = { hidden: false, addEventListener: (name, fn) => documentEvents.set(name, fn) };
Date.now = () => now;
const { onPageReturn } = await import('../assets/js/core/ui.js');

test('切回分頁時同時發生的事件只重新確認一次，之後再回來仍會確認', () => {
  let calls = 0;
  onPageReturn(() => calls++);
  now = 10_000;
  documentEvents.get('visibilitychange')();
  windowEvents.get('focus')();
  assert.equal(calls, 1);
  now = 12_000;
  windowEvents.get('focus')();
  assert.equal(calls, 2);
  windowEvents.get('pageshow')({ persisted: false });
  assert.equal(calls, 2);
  now = 20_000;
  windowEvents.get('pageshow')({ persisted: true });
  assert.equal(calls, 3);
});
