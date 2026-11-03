import test from 'node:test';
import assert from 'node:assert/strict';
import { adminNavigation, adminDestination, adminReturnTo } from '../dist/assets/js/core/session.js';

const identity = (...rights) => ({
  permissions: rights.map(([module, action]) => ({ module, action, allowed: true })),
});

test('獨立新增權限提供可用側欄與登入目的頁，不賦予檢視權', () => {
  for (const [module, page] of [
    ['attractions', 'place-add'],
    ['films', 'film-add'],
    ['admins', 'admin-add'],
  ]) {
    const user = identity([module, 'create']);
    assert.equal(adminNavigation(user, module).href, `/admin/${page}.html`);
    assert.equal(adminDestination(user), `/admin/${page}.html`);
    globalThis.location = { origin: 'http://localhost:8080', search: '?returnTo=/admin/index.html' };
    assert.equal(adminReturnTo(user), `/admin/${page}.html`);
  }
  assert.equal(adminNavigation(identity(['films', 'edit']), 'films'), null);
  assert.equal(adminDestination(identity()), '/index.html');
});

test('有檢視權才回列表；內容有編輯權即可恢復編輯深連結，管理員帳號仍需檢視', () => {
  globalThis.location = {
    origin: 'http://localhost:8080',
    search: '?returnTo=' + encodeURIComponent('/admin/film-edit.html?id=target'),
  };
  assert.equal(
    adminReturnTo(identity(['films', 'view'], ['films', 'edit'])),
    '/admin/film-edit.html?id=target'
  );
  assert.equal(adminReturnTo(identity(['films', 'view'])), '/admin/films.html');
  // 只有編輯權限也能回到電影編輯頁（後端讀取單筆接受檢視或編輯權限）。
  assert.equal(adminReturnTo(identity(['films', 'edit'])), '/admin/film-edit.html?id=target');
  globalThis.location.search = '?returnTo=' + encodeURIComponent('/admin/admin-edit.html?id=target');
  assert.notEqual(adminReturnTo(identity(['admins', 'edit'])), '/admin/admin-edit.html?id=target');
  for (const target of ['//attacker.test/', '/admin/admins.html', '/admin/login.html', '/unknown.html']) {
    globalThis.location.search = '?returnTo=' + encodeURIComponent(target);
    assert.equal(adminReturnTo(identity(['films', 'create'])), '/admin/film-add.html');
  }
});
