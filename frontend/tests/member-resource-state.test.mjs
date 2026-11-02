import test from 'node:test';
import assert from 'node:assert/strict';
import { MemberResourceState } from '../assets/js/core/member-resource-state.js';

const deferred = () => {
  let resolve;
  const promise = new Promise(done => (resolve = done));
  return { promise, resolve };
};
function fixture() {
  const data = { member: { id: 'a' }, saved: new Set(), writes: [] };
  const api = {
    favoriteStatus: async ids => ({
      statuses: ids.map(id => ({ attractionId: id, favorited: data.saved.has(id) })),
    }),
    saveFavorite: async (id, saved) => {
      data.writes.push({ id, saved });
      if (saved) data.saved.add(id);
      else data.saved.delete(id);
    },
  };
  const state = new MemberResourceState(api, async () => data.member);
  return { data, api, state };
}
test('匿名操作只要求登入，不寫入收藏', async () => {
  const { data, state } = fixture();
  data.member = null;
  await state.refresh(['x']);
  assert.deepEqual(await state.favorite('x'), { login: true });
  assert.equal(data.writes.length, 0);
});
test('收藏成功才重新讀回狀態；連點不發第二次寫入', async () => {
  const { data, api, state } = fixture();
  await state.refresh(['x']);
  const wait = deferred();
  api.saveFavorite = async (id, saved) => {
    data.writes.push({ id, saved });
    await wait.promise;
    data.saved.add(id);
  };
  const saving = state.favorite('x');
  assert.equal(state.statuses.get('x'), false);
  assert.deepEqual(await state.favorite('x'), { busy: true });
  wait.resolve();
  assert.equal((await saving).changed, true);
  assert.equal(state.statuses.get('x'), true);
  assert.equal(data.writes.length, 1);
});
test('請求逾時但服務端已保存，讀回後確認狀態而不重送', async () => {
  const { data, api, state } = fixture();
  await state.refresh(['x']);
  api.saveFavorite = async (id, saved) => {
    data.writes.push({ id, saved });
    data.saved.add(id);
    throw Object.assign(new Error('未知結果'), { status: 0 });
  };
  assert.equal((await state.favorite('x')).reconciled, true);
  assert.equal(state.statuses.get('x'), true);
  assert.equal(data.writes.length, 1);
});
test('讀取失敗維持未知，不當成未收藏', async () => {
  const { api, state } = fixture();
  await state.refresh(['x']);
  api.favoriteStatus = async () => {
    throw new Error('無法讀取');
  };
  await assert.rejects(state.refresh(['x']));
  assert.equal(state.member, undefined);
  assert.equal(state.statuses.has('x'), false);
  assert.deepEqual(await state.favorite('x'), { retry: true });
});
test('較慢舊身分回應不能蓋過新身分', async () => {
  const { state } = fixture();
  const old = deferred();
  let calls = 0;
  state.readIdentity = () => (++calls === 1 ? old.promise : Promise.resolve({ id: 'b' }));
  const loading = state.refresh(['x']);
  await state.refresh(['y']);
  old.resolve({ id: 'a' });
  assert.equal(await loading, false);
  assert.equal(state.member.id, 'b');
  assert.equal(state.statuses.has('x'), false);
  assert.equal(state.statuses.has('y'), true);
});
test('保存中換帳號不顯示上一位會員操作結果', async () => {
  const { data, api, state } = fixture();
  await state.refresh(['x']);
  const wait = deferred();
  api.saveFavorite = async () => {
    await wait.promise;
  };
  const saving = state.favorite('x');
  data.member = { id: 'b' };
  await state.refresh(['x']);
  wait.resolve();
  assert.equal((await saving).changed, false);
  assert.equal(state.member.id, 'b');
});
test('超過一批狀態分批查詢，每個頁面只用當下所見 ID', async () => {
  const { api, state } = fixture();
  const batches = [];
  api.favoriteStatus = async ids => {
    batches.push(ids.length);
    return { statuses: ids.map(id => ({ attractionId: id, favorited: false })) };
  };
  await state.refresh(Array.from({ length: 205 }, (_, i) => String(i)));
  assert.deepEqual(batches, [100, 100, 5]);
});
test('不完整批次回應拒絕，不默認缺項為未收藏', async () => {
  const { api, state } = fixture();
  api.favoriteStatus = async () => ({ statuses: [] });
  await assert.rejects(state.refresh(['x']));
  assert.equal(state.statuses.has('x'), false);
});
