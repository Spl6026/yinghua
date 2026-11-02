import test from 'node:test';
import assert from 'node:assert/strict';
import { TripEditorState } from '../assets/js/core/trip-editor-state.js';

function fixture() {
  const data = { member: { id: 'a' }, currentId: 't', writes: [], lists: 0 };
  const original = {
    id: 't',
    title: '原名稱',
    tripDate: '2026-11-05',
    version: 3,
    isCurrent: true,
    items: [
      { attractionId: 'a', startTime: '10:30', note: '先走', snapshotName: '甲' },
      { attractionId: 'b', startTime: null, note: '', snapshotName: '乙' },
    ],
  };
  data.trip = structuredClone(original);
  const api = {
    list: async () => {
      data.lists++;
      if (data.listError) throw new Error('清單讀取失敗');
      return { items: [data.trip], total: 1, page: 1, currentId: data.currentId };
    },
    get: async () => ({ itinerary: data.trip }),
    save: async (id, body) => {
      data.writes.push({ id, body });
      if (data.error) throw data.error;
      data.trip = { ...data.trip, ...body, version: body.version + 1 };
      return { itinerary: data.trip };
    },
    create: async body => {
      data.writes.push(body);
      if (data.error) throw data.error;
      data.trip = { ...original, id: body.id };
      return { itinerary: data.trip };
    },
    select: async () => ({ itinerary: data.trip }),
    delete: async () => {
      data.currentId = null;
    },
    apply: async () => ({ itinerary: { ...data.trip, title: '推薦名稱', items: [] } }),
  };
  const state = new TripEditorState(
    api,
    async () => data.member,
    () => 'stable-id'
  );
  return { data, api, state, original };
}
test('站點排序保留時間備註且只在保存後改server版本', async () => {
  const f = fixture();
  await f.state.load();
  assert.equal(f.state.move(0, -1), false);
  f.state.move(1, -1);
  assert.equal(f.data.writes.length, 0);
  await f.state.save();
  assert.deepEqual(
    f.data.writes[0].body.items.map(i => i.attractionId),
    ['b', 'a']
  );
  assert.equal(f.data.writes[0].body.items[1].startTime, '10:30');
  assert.equal(f.data.writes[0].body.items[1].note, '先走');
  assert.equal(f.data.writes[0].body.version, 3);
  assert.equal(f.state.dirty, false);
});
test('409保留草稿且不能重送直到明確重新載入', async () => {
  const f = fixture();
  await f.state.load();
  f.state.draft.title = '保留';
  f.state.dirty = true;
  f.data.error = Object.assign(new Error('衝突'), { status: 409 });
  await assert.rejects(f.state.save());
  assert.equal(f.state.draft.title, '保留');
  assert.equal(f.state.stale, true);
  await assert.rejects(f.state.save());
  assert.equal(f.data.writes.length, 1);
  f.data.error = null;
  await f.state.load();
  assert.equal(f.state.draft.title, '原名稱');
  assert.equal(f.state.stale, false);
});
test('保存後清單讀取失敗仍保留已保存版本，不冒稱保存失敗', async () => {
  const f = fixture();
  await f.state.load();
  f.data.listError = true;
  await assert.rejects(f.state.save(), error => error.saved === true);
  assert.equal(f.state.trip.version, 4);
  assert.equal(f.state.dirty, false);
});
test('新增網路結果未知重試使用同一ID', async () => {
  const f = fixture();
  f.data.error = Object.assign(new Error('斷線'), { status: 0 });
  await assert.rejects(f.state.create());
  await assert.rejects(f.state.create());
  assert.deepEqual(
    f.data.writes.map(body => body.id),
    ['stable-id', 'stable-id']
  );
  f.data.error = null;
  await f.state.create();
  assert.equal(f.state.trip.id, 'stable-id');
});
test('寫入途中換帳號清除原私人草稿', async () => {
  const f = fixture();
  await f.state.load();
  f.api.save = async () => {
    f.data.member = { id: 'b' };
    return { itinerary: f.original };
  };
  await assert.rejects(f.state.save());
  assert.equal(f.state.draft, null);
  assert.equal(f.state.member, null);
});
test('同帳號登入到期保留草稿，恢復後由使用者再次保存', async () => {
  const f = fixture();
  await f.state.load();
  f.state.draft.title = '未存';
  f.state.dirty = true;
  f.data.member = null;
  await assert.rejects(f.state.save(), e => e.status === 401);
  assert.equal(f.state.draft.title, '未存');
  assert.equal(f.data.writes.length, 0);
  f.data.member = { id: 'a' };
  await f.state.save();
  assert.equal(f.state.trip.title, '未存');
});
test('新增景點去重，移除與邊界移動不破壞其餘站點', async () => {
  const f = fixture();
  await f.state.load();
  assert.equal(f.state.add({ id: 'a', name: '甲' }), false);
  f.state.add({ id: 'c', name: '丙' });
  assert.equal(f.state.draft.items.length, 3);
  f.state.remove(1);
  assert.deepEqual(
    f.state.draft.items.map(i => i.attractionId),
    ['a', 'c']
  );
  assert.equal(f.state.move(1, 1), false);
});
test('讀取行程不會自動套用推薦或寫入', async () => {
  const f = fixture();
  await f.state.load();
  assert.equal(f.data.writes.length, 0);
  assert.equal(f.state.draft.items.length, 2);
});
