import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';
import * as taipeiTime from '../assets/js/core/taipei-time.js';

// [S G34；E] 真正執行刪除 handler：重載移除原列後，取消及成功的焦點行為不同。
class Node {
  constructor(tag = 'div') {
    this.tag = tag;
    this.children = [];
    this.events = {};
    this.value = '';
    this.elements = { q: { value: '' }, status: { value: '' } };
  }
  append(...nodes) {
    this.children.push(...nodes);
  }
  get childNodes() {
    return this.children;
  }
  replaceChildren(...nodes) {
    this.children = nodes;
  }
  addEventListener(name, handler) {
    this.events[name] = handler;
  }
  focus() {
    this.focused = true;
  }
}
for (const [file, init, rowsId, messageId, module] of [
  ['attraction-list', 'initializeAttractionList', '#place-rows', '#place-list-status'],
  ['film-list', 'initializeFilmList', '#film-rows', '#film-list-status'],
  ['content-list', 'initializeContentList', '#content-rows', '#content-list-status', 'news'],
  ['content-list', 'initializeContentList', '#content-rows', '#content-list-status', 'offers'],
  ['admin-list', 'initializeAdmins', '#admin-rows', '#page-status'],
]) {
  for (const confirmed of [false, true])
    test(`${file}${module || ''}：${confirmed ? '刪除成功重載後聚焦結果' : '取消不刪除、不重建列表'}`, async () => {
      const source = (await readFile(new URL(`../assets/js/pages/${file}.js`, import.meta.url), 'utf8'))
        .replace(/^import\b[\s\S]*?;[ \t]*\r?\n/gm, '')
        .replace(/\bexport /g, '');
      const nodes = new Map(),
        get = id => {
          if (!nodes.has(id)) nodes.set(id, new Node());
          return nodes.get(id);
        };
      let calls = 0;
      const record = {
        id: 'owned-record',
        version: 1,
        name: '景點',
        title: '電影／消息',
        displayName: '管理員',
        account: 'owned',
        roleId: 'viewer',
        email: 'owned@example.test',
        status: 'published',
        publishAt: '2026-01-01T00:00:00Z',
        screenings: [],
        image: '',
      };
      const service = {
        list: async () => ({ items: calls ? [] : [record], total: calls ? 0 : 1, pageSize: 20 }),
        delete: async () => {
          calls++;
          return { publication: { status: 'succeeded', message: '公開頁面已同步' } };
        },
        remove: async () => {
          calls++;
        },
      };
      const sandbox = {
        ...taipeiTime,
        document: { querySelector: get, createTextNode: text => ({ textContent: text }) },
        location: { pathname: '/admin/list.html', search: '' },
        history: { replaceState() {} },
        URLSearchParams,
        setTimeout,
        clearTimeout,
        attractionsApi: service,
        filmsApi: service,
        adminApi: service,
        contentAdminApi: () => service,
        hasPermission: () => true,
        confirmAction: async () => confirmed,
        handleAdminError: error => {
          throw error;
        },
        status: (node, text) => {
          node.textContent = text;
        },
        element: (tag, attrs = {}, text) => Object.assign(new Node(tag), attrs, { textContent: text }),
      };
      vm.createContext(sandbox);
      vm.runInContext(source, sandbox);
      await sandbox[init]({ admin: { id: 'actor' } }, module);
      const row = get(rowsId).children[0],
        button = row.children.at(-1).children.find(node => node.tag === 'button');
      await button.events.click();
      assert.equal(calls, confirmed ? 1 : 0);
      assert.equal(get(rowsId).children.includes(row), !confirmed);
      assert.equal(Boolean(get(messageId).focused), confirmed);
    });
}
