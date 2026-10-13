import test from 'node:test';
import assert from 'node:assert/strict';
import { passwordError, passwordConfirmationError } from '../assets/js/core/password-policy.js';
test('Unicode 密碼以字元而非 UTF-16 code unit 計數，保留空白', () => {
  assert.notEqual(passwordError('😀'.repeat(8)), '');
  assert.equal(passwordError('😀'.repeat(15)), '');
  assert.equal(passwordError(' '.repeat(15)), '');
  assert.equal(passwordError('😀'.repeat(128)), '');
  assert.notEqual(passwordError('😀'.repeat(129)), '');
});

test('確認密碼不能省略或不一致，保留 Unicode 與空白比較', () => {
  assert.notEqual(passwordConfirmationError('Demo123!', ''), '');
  assert.notEqual(passwordConfirmationError('Demo123!', 'different'), '');
  assert.notEqual(passwordConfirmationError(' 密碼12345', '密碼12345 '), '');
  assert.equal(passwordConfirmationError(' 密碼12345', ' 密碼12345'), '');
});
