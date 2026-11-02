import test from 'node:test';
import assert from 'node:assert/strict';
import { returnTo } from '../dist/assets/js/core/session.js';

test('會員 returnTo 只允許已建置的同站公開頁', () => {
  for (const value of [
    'https://attacker.test/',
    '//attacker.test/',
    '/\\attacker.test',
    '/login.html',
    '/not-built.html',
    '/admin/admins.html',
    '/%0aattacker',
  ]) {
    globalThis.location = {
      origin: 'http://localhost:8080',
      search: '?returnTo=' + encodeURIComponent(value),
    };
    assert.equal(returnTo('member'), '/index.html');
  }
});
test('後台 returnTo 保留合法編輯網址，拒絕登入循環與跨 namespace', () => {
  globalThis.location = {
    origin: 'http://localhost:8080',
    search: '?returnTo=' + encodeURIComponent('/admin/admin-edit.html?id=target'),
  };
  assert.equal(returnTo('admin'), '/admin/admin-edit.html?id=target');
  for (const value of ['/index.html', '/admin/login.html', '/admin/change-password.html']) {
    globalThis.location.search = '?returnTo=' + encodeURIComponent(value);
    assert.equal(returnTo('admin'), '/admin/index.html');
  }
});

test('會員登入可回到發布的景點網址，仍拒絕非 slug 與跨站網址', () => {
  globalThis.location = {
    origin: 'http://localhost:8080',
    search: '?returnTo=' + encodeURIComponent('/places/hsinchu-park.html?from=explore'),
  };
  assert.equal(returnTo('member'), '/places/hsinchu-park.html?from=explore');
  assert.equal(returnTo('admin'), '/admin/index.html');
  for (const value of [
    '/places/../admin/admins.html',
    '/places/bad_name.html',
    '/places/test.php',
    '/places/%2fadmin.html',
    '//attacker.test/places/test.html',
  ]) {
    globalThis.location.search = '?returnTo=' + encodeURIComponent(value);
    assert.equal(returnTo('member'), '/index.html');
  }
});

test('新電影詳情登入後可返回，拒絕跨站、非法 slug 與後台身分', () => {
  globalThis.location = {
    origin: 'http://localhost:8080',
    search: '?returnTo=' + encodeURIComponent('/films/new-movie.html?from=cinema'),
  };
  assert.equal(returnTo('member'), '/films/new-movie.html?from=cinema');
  assert.equal(returnTo('admin'), '/admin/index.html');
  for (const value of ['/films/bad_name.html', '//attacker.test/films/movie.html', '/films/test.php']) {
    globalThis.location.search = '?returnTo=' + encodeURIComponent(value);
    assert.equal(returnTo('member'), '/index.html');
  }
});
