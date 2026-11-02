import test from 'node:test';
import assert from 'node:assert/strict';
import { SessionDeadline } from '../assets/js/core/session-deadline.js';

test('伺服器與使用者時鐘相差多年仍以剩餘時間預警', () => {
  let now = 500;
  const d = new SessionDeadline(() => now);
  d.update({ serverTime: 1700000000, expiresAt: 1700043200, idleExpiresAt: 1700001800 });
  assert.equal(d.state().mode, 'active');
  now += 1680000;
  assert.equal(d.state().mode, 'warn');
  assert.equal(d.state().idle, 120000);
  now += 120000;
  assert.equal(d.state().mode, 'expired');
});
test('至少十次有效延長都不改變同一 session 的絕對上限', () => {
  let now = 0;
  const d = new SessionDeadline(() => now);
  for (let i = 0; i <= 10; i++) {
    d.update({ serverTime: now / 1000, expiresAt: 43200, idleExpiresAt: now / 1000 + 1800 });
    assert.equal(d.absolute, 43200000);
    assert.equal(d.state().mode, 'active');
    now += 1680000;
    assert.equal(d.state().mode, 'warn');
  }
});
test('接近絕對上限要求重新驗證，不把長 idle 值當作可延長', () => {
  let now = 0;
  const d = new SessionDeadline(() => now);
  d.update({ serverTime: 1000, expiresAt: 1100, idleExpiresAt: 2800 });
  assert.equal(d.state().mode, 'reauth');
  assert.equal(d.state().idle, 100000);
  now = 100001;
  assert.equal(d.state().mode, 'expired');
});
