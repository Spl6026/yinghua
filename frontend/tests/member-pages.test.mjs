import test from 'node:test';
import assert from 'node:assert/strict';
import { TripEditorState } from '../assets/js/core/trip-editor-state.js';
import { loadWithSandbox } from './support/sandbox-module.mjs';

// 執行真正頁面程式，只替換 DOM、session 與 HTTP 邊界；不代表瀏覽器排版驗收。
const settle = async () => {
  for (let i = 0; i < 8; i++) await new Promise(resolve => setImmediate(resolve));
};
const deferred = () => {
  let resolve;
  const promise = new Promise(done => (resolve = done));
  return { promise, resolve };
};
class Node {
  constructor(tag = 'div') {
    this.tag = tag;
    this.children = [];
    this.listeners = new Map();
    this.dataset = {};
    this.attrs = {};
    this.hidden = false;
    this.disabled = false;
    this.isConnected = true;
    this.classList = { add() {} };
  }
  append(...items) {
    this.children.push(...items);
  }
  replaceChildren(...items) {
    this.children = items;
  }
  setAttribute(name, value) {
    this.attrs[name] = value;
  }
  removeAttribute(name) {
    delete this.attrs[name];
  }
  toggleAttribute(name, value) {
    if (name === 'disabled') this.disabled = value;
  }
  querySelectorAll() {
    return [];
  }
  addEventListener(name, fn) {
    this.listeners.set(name, fn);
  }
  emit(name, event = {}) {
    return this.listeners.get(name)?.({ preventDefault() {}, ...event });
  }
  before() {}
  remove() {
    this.isConnected = false;
  }
  focus() {
    this.focused = true;
  }
}
async function fixture(page, search = '', preset = {}) {
  const nodes = new Map(),
    node = id => {
      if (!nodes.has(id)) nodes.set(id, new Node());
      return nodes.get(id);
    };
  const data = {
    creates: 0,
    member: { id: 'a', displayName: '甲' },
    writes: [],
    trip: null,
    replacements: [],
    assigned: [],
    reads: 0,
    ...preset,
  };
  const docEvents = new Map(),
    windowEvents = new Map();
  const api = {
    favorites: async () => ({ items: [], nextCursor: null }),
    itinerary: async () => {
      data.reads++;
      return { itinerary: data.trip, nextCursor: null };
    },
    addStop: async (id, version) => {
      data.writes.push({ id, version });
      return { version: version + 1 };
    },
    removeStop: async (id, version) => {
      data.writes.push({ id, version });
      return { version: version + 1 };
    },
  };
  const document = {
    hidden: false,
    querySelector: node,
    addEventListener: (name, fn) => docEvents.set(name, fn),
  };
  const trips = {
    create: async () => {
      data.creates++;
      data.trip = { id: 'new-trip', title: '', tripDate: null, items: [], version: 1, isCurrent: true };
      return { itinerary: data.trip };
    },
    list: async () => {
      data.reads++;
      return {
        items: data.trip ? [data.trip] : [],
        total: data.trip ? 1 : 0,
        page: 1,
        currentId: data.trip?.id || null,
      };
    },
    get: async () => ({ itinerary: data.trip }),
    routes: async () => ({ items: [] }),
    save: async (id, body) => {
      data.writes.push(body);
      data.trip = { ...data.trip, ...body, version: body.version + 1 };
      return { itinerary: data.trip };
    },
  };
  const sandbox = {
    attractionPicker: (_host, options) => {
      data.picker = options;
      return {
        open: async () => {
          data.pickerOpen = true;
        },
      };
    },
    document,
    URLSearchParams,
    TripEditorState,
    itinerariesApi: trips,
    memberResourcesApi: api,
    readMember: async () => data.member,
    requireSession: async () => data.member,
    location: { search, replace: url => data.replacements.push(url), assign: url => data.assigned.push(url) },
    loginLocation: () => '/login.html',
    handleSessionExpiry: () => data.replacements.push('/login.html'),
    window: { addEventListener: (name, fn) => windowEvents.set(name, fn) },
    clearMemberIntent: () => {
      data.intentCleared = true;
    },
    resolvePublicAttraction: async () => ({ id: 'place-a', name: '公開地點' }),
    element: (tag, attrs = {}, text) => {
      const n = new Node(tag);
      Object.assign(n, attrs);
      if (attrs.id) nodes.set('#' + attrs.id, n);
      n.textContent = text;
      return n;
    },
    status: (n, text, error) => {
      n.textContent = text;
      n.error = error;
    },
    formErrors() {
      return false;
    },
    unsavedChanges() {},
    toast: message => {
      data.toast = message;
    },
    // 一秒內合併重複事件的行為由 ui-page-return 測試驗證；這裡逐一觸發，模擬分開的回到頁面。
    onPageReturn: callback => {
      sandbox.window.addEventListener('pageshow', event => {
        if (event?.persisted) callback();
      });
      sandbox.window.addEventListener('focus', callback);
      document.addEventListener('visibilitychange', () => {
        if (!document.hidden) callback();
      });
    },
    confirmAction: async () => true,
    tripStopEditor: item => Object.assign(new Node('article'), { item }),
    memberItineraryStop: item => Object.assign(new Node(), { item }),
    placeCard: item => Object.assign(new Node(), { item }),
    attractionCardData: item => item,
    mountMemberActions: async () => {},
  };
  await loadWithSandbox(`pages/${page}.js`, sandbox);
  await settle();
  return { data, api, trips, node, document, docEvents, windowEvents };
}

test('收藏頁切回分頁先清除舊帳號資料，較慢舊回應不能蓋回', async () => {
  const f = await fixture('favorites');
  const old = deferred(),
    fresh = deferred();
  let calls = 0;
  f.api.favorites = () => (++calls === 1 ? old.promise : fresh.promise);
  f.windowEvents.get('focus')();
  await settle();
  f.node('#favorites-list').append(new Node());
  f.data.member = { id: 'b' };
  f.docEvents.get('visibilitychange')();
  assert.equal(f.node('#favorites-list').children.length, 0);
  await settle();
  fresh.resolve({ items: [{ attraction: { name: '乙收藏' } }], nextCursor: null });
  await settle();
  old.resolve({ items: [{ attraction: { name: '甲收藏' } }], nextCursor: null });
  await settle();
  assert.equal(f.node('#favorites-list').children[0].item.name, '乙收藏');
});

const tripA = (items = []) => ({
  ...{ id: 'trip-a', version: 1, title: '甲行程', tripDate: null, isCurrent: true, items: [] },
  items,
});

test('行程待辦網址先到總覽選擇行程，沒有 GET 自動寫入', async () => {
  const f = await fixture('planner', '?place=place-a', { trip: tripA() });
  assert.equal(f.data.writes.length, 0);
  assert.equal(f.data.creates, 0);
  assert.equal(f.data.reads, 1);
  assert.equal(f.node('#trip-overview').hidden, false);
  assert.match(f.node('#trip-overview-intent-text').textContent, /公開地點/);
  const card = f.node('#trip-overview-list').children[0];
  const action = card.children.at(-1).children[0];
  assert.equal(action.href, '/planner.html?edit=trip-a&place=place-a');
});

test('行程總覽列出本人行程，新增行程後帶著待辦進入編輯畫面', async () => {
  const f = await fixture('planner', '?route=route-a', { trip: tripA([{ attractionId: 'x' }]) });
  f.trips.route = async () => ({ route: { title: '老城散步' } });
  assert.equal(f.node('#trip-overview-list').children.length, 1);
  await f.node('#overview-new-trip').emit('click');
  await settle();
  assert.equal(f.data.creates, 1);
  assert.deepEqual(f.data.assigned, ['/planner.html?edit=new-trip&route=route-a']);
});

test('行程內容只讀取不寫入，編輯按鈕連到同一份行程的編輯畫面', async () => {
  const f = await fixture('planner', '?trip=trip-a', {
    trip: tripA([{ attractionId: 'x', snapshotName: '甲地點', startTime: '10:00', note: '午餐' }]),
  });
  assert.equal(f.data.writes.length, 0);
  assert.equal(f.node('#trip-detail').hidden, false);
  assert.equal(f.node('#trip-detail-title').textContent, '甲行程');
  assert.equal(f.node('#trip-detail-stops').children.length, 1);
  assert.equal(f.node('#trip-detail-edit').href, '/planner.html?edit=trip-a');
  assert.equal(f.node('#trip-detail-edit').hidden, false);
});

test('行程保存成功後讀取失敗，仍提示已保存而非重送', async () => {
  const f = await fixture('planner', '?edit=trip-a', { trip: tripA([{ attractionId: 'x' }]) });
  f.trips.list = async () => {
    throw new Error('讀取失敗');
  };
  f.node('#trip-form').emit('submit');
  await settle();
  assert.equal(f.data.writes.length, 1);
  assert.match(f.node('#trip-status').textContent, /已保存.*讀取失敗/);
  assert.equal(f.node('#itinerary-list').children.length, 1);
  assert.equal(f.node('#reload-trip').disabled, false);
});

test('行程換帳號會清除原私人站點，不以另一帳號覆蓋草稿', async () => {
  const f = await fixture('planner', '?edit=trip-a', { trip: tripA([{ attractionId: 'x' }]) });
  assert.equal(f.node('#itinerary-list').children.length, 1);
  f.data.member = { id: 'b' };
  f.docEvents.get('visibilitychange')();
  await settle();
  assert.equal(f.node('#itinerary-list').children.length, 0);
  assert.match(f.node('#trip-status').textContent, /登入身分已變更/);
});

test('編輯畫面新增地點只放進草稿，按儲存才送出，且不另外建立行程', async () => {
  const f = await fixture('planner', '?edit=trip-a', { trip: tripA() });
  await f.node('#add-stop').emit('click');
  assert.equal(f.data.pickerOpen, true);
  await f.data.picker.onChoose({ id: 'place-a', name: '公開景點' });
  assert.equal(f.data.creates, 0);
  assert.equal(f.data.writes.length, 0);
  assert.equal(f.data.picker.isSelected('place-a'), true);
  f.node('#trip-form').emit('submit');
  await settle();
  assert.equal(f.data.writes.length, 1);
  assert.equal(f.data.writes[0].items[0].attractionId, 'place-a');
  assert.equal(f.data.replacements.length, 0);
});

test('儲存成功與重新載入都不把焦點移到狀態訊息，畫面不會自己捲動', async () => {
  const f = await fixture('planner', '?edit=trip-a', { trip: tripA([{ attractionId: 'x' }]) });
  f.node('#trip-form').emit('submit');
  await settle();
  assert.match(f.node('#trip-status').textContent, /已儲存/);
  assert.notEqual(f.node('#trip-status').focused, true);
  await f.node('#reload-trip').emit('click');
  await settle();
  assert.notEqual(f.node('#trip-status').focused, true);
});

test('初次載入、重新聚焦與背景讀取失敗不把焦點送到頁尾', async () => {
  const f = await fixture('planner', '?edit=trip-a', { trip: tripA([{ attractionId: 'x' }]) });
  assert.notEqual(f.node('#trip-status').focused, true);
  f.windowEvents.get('focus')();
  await settle();
  assert.notEqual(f.node('#trip-status').focused, true);
  f.trips.list = async () => {
    throw new Error('背景讀取失敗');
  };
  f.windowEvents.get('focus')();
  await settle();
  assert.match(f.node('#trip-status').textContent, /背景讀取失敗/);
  assert.notEqual(f.node('#trip-status').focused, true);
});
