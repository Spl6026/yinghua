import test from 'node:test';
import assert from 'node:assert/strict';
import { PendingWrites } from '../assets/js/core/pending-writes.js';
test('收藏尚未回應時保持pending，全部確認後才允許離頁', async () => {
  const writes = new PendingWrites();
  let complete;
  const pending = writes.run(
    () =>
      new Promise(resolve => {
        complete = resolve;
      })
  );
  assert.equal(writes.active, true);
  let navigated = false;
  const navigation = writes.confirmed().then(value => {
    navigated = value;
  });
  await Promise.resolve();
  assert.equal(navigated, false);
  complete(true);
  await pending;
  await navigation;
  assert.equal(navigated, true);
  assert.equal(writes.active, false);
});
test('任一寫入失敗或拒絕，不假裝收藏已保存', async () => {
  for (const action of [() => false, () => Promise.reject(new Error('network'))]) {
    const writes = new PendingWrites();
    const pending = writes.run(action);
    const confirmed = writes.confirmed();
    await pending.catch(() => {});
    assert.equal(await confirmed, false);
    assert.equal(writes.active, false);
  }
});
