import test from 'node:test';
import assert from 'node:assert/strict';
import {
  formatTaipeiDateTime,
  fromTaipeiInput,
  taipeiEnglish,
  taipeiParts,
  toTaipeiInput,
} from '../assets/js/core/taipei-time.js';

test('後台日期時間欄位與伺服器時間互轉都以台北時間計算', () => {
  assert.equal(toTaipeiInput('2026-10-28T18:30:00+00:00'), '2026-10-29T02:30:00');
  assert.equal(fromTaipeiInput('2026-10-29T02:30'), '2026-10-29T02:30:00+08:00');
  assert.equal(fromTaipeiInput('2026-10-29T02:30:45.123'), '2026-10-29T02:30:45+08:00');
  assert.equal(fromTaipeiInput(''), '');
  assert.equal(toTaipeiInput(null), '');
});

test('跨日與跨年仍依台北時區，午夜顯示為 00 而不是 24', () => {
  assert.deepEqual(taipeiParts('2026-12-31T16:00:00Z'), {
    year: '2027',
    month: '01',
    day: '01',
    hour: '00',
    minute: '00',
    second: '00',
    literal: taipeiParts('2026-12-31T16:00:00Z').literal,
  });
  assert.equal(formatTaipeiDateTime('2026-11-02T07:04:05Z'), '2026-11-02 15:04:05');
  assert.equal(taipeiEnglish('2026-11-02T00:00:00+08:00', { month: 'short' }), 'NOV');
  assert.equal(taipeiEnglish('2026-11-02T00:00:00+08:00', { weekday: 'short' }), 'MON');
});
