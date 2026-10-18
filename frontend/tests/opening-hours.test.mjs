import test from 'node:test';
import assert from 'node:assert/strict';
import { emptyOpeningHours, openingHoursLines } from '../assets/js/core/opening-hours.js';

test('舊時間僅當備註，不推測為七天皆營業',()=>{
  const value=emptyOpeningHours('依店家公告');
  assert.equal(value.days.length,7);assert.ok(value.days.every(day=>day.status==='unknown'));
  assert.equal(openingHoursLines(null,'原始 09:00–17:00')[0],'原始 09:00–17:00');
});
test('每週顯示從週一開始，依 day 而不是輸入順序配對',()=>{
  const value=emptyOpeningHours('假日請洽店家');value.days.reverse();
  Object.assign(value.days.find(day=>day.day===1),{status:'periods',periods:[{opens:'09:00',closes:'12:00',nextDay:false},{opens:'22:00',closes:'02:00',nextDay:true}]});
  value.days.find(day=>day.day===2).status='closed';value.days.find(day=>day.day===0).status='all_day';
  const lines=openingHoursLines(value);
  assert.equal(lines[0],'週一：09:00–12:00、22:00–次日 02:00');assert.equal(lines[1],'週二：公休');assert.equal(lines[6],'週日：全天開放');assert.equal(lines[7],'備註：假日請洽店家');
});
