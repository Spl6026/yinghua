import test from 'node:test';
import assert from 'node:assert/strict';
import { applyPlaceCandidate } from '../assets/js/core/place-candidate.js';

function fixture(){
  const form={elements:Object.fromEntries(['name','address','latitude','longitude','hours','websiteUrl','mapUrl','googlePlaceId','district','description','summary','phone'].map(name=>[name,{value:'原值'}]))};
  const loaded=[];return {form,loaded,editor:{load:(...args)=>loaded.push(args)}};
}
test('候選行政區更新與 HTTP 官網保留，週表走相同編輯器',()=>{
  const f=fixture(),openingHours={days:[]};
  const notes=applyPlaceCandidate(f.form,{name:'市場',address:'新竹市東區',district:'東區',latitude:0,longitude:120,websiteUrl:'http://example.test',openingHours},f.editor);
  assert.equal(f.form.elements.district.value,'東區');assert.equal(f.form.elements.latitude.value,'0');assert.equal(f.form.elements.websiteUrl.value,'http://example.test');assert.deepEqual(f.loaded,[[openingHours,undefined]]);assert.match(notes.join(''),/HTTP/);
});
test('候選無法確認行政區時清空原值，不能沿用北區',()=>{
  const f=fixture();f.form.elements.district.value='北區';
  const notes=applyPlaceCandidate(f.form,{name:'其他地點',district:''},f.editor);
  assert.equal(f.form.elements.district.value,'');assert.match(notes.join(''),/人工選擇/);assert.equal(f.form.elements.description.value,'原值');assert.equal(f.form.elements.phone.value,'');
});
