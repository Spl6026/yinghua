import test from 'node:test';
import assert from 'node:assert/strict';
import {formatUtcDateTime} from '../assets/js/core/utc-display.js';
test('UTC資料庫時間轉台北並正確跨日跨年',()=>assert.equal(formatUtcDateTime('2026-12-31 18:05:07'),'2027-01-01 02:05:07'));
test('有明確offset的時間不重複加八小時',()=>assert.equal(formatUtcDateTime('2026-11-01T21:00:00+08:00'),'2026-11-01 21:00:00'));
test('未知或不合法時間不虛構日期',()=>{assert.equal(formatUtcDateTime(''),'—');assert.equal(formatUtcDateTime('not a date'),'—');});
