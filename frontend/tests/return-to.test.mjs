import test from 'node:test';
import assert from 'node:assert/strict';
import { returnTo } from '../dist/assets/js/core/session.js';

test('會員 returnTo 只允許已建置的同站公開頁', () => {
  for (const value of ['https://attacker.test/', '//attacker.test/', '/\\attacker.test', '/login.html', '/not-built.html', '/admin/admins.html', '/%0aattacker']) {
    globalThis.location = { origin: 'http://localhost:8080', search: '?returnTo=' + encodeURIComponent(value) };
    assert.equal(returnTo('member'), '/index.html');
  }
});
test('後台 returnTo 保留合法編輯網址，拒絕登入循環與跨 namespace', () => {
  globalThis.location = { origin: 'http://localhost:8080', search: '?returnTo=' + encodeURIComponent('/admin/admin-edit.html?id=target') };
  assert.equal(returnTo('admin'), '/admin/admin-edit.html?id=target');
  for (const value of ['/index.html', '/admin/login.html', '/admin/change-password.html']) {
    globalThis.location.search = '?returnTo=' + encodeURIComponent(value);
    assert.equal(returnTo('admin'), '/admin/index.html');
  }
});
