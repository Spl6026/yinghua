import test from 'node:test';
import assert from 'node:assert/strict';
import { applyPlaceCandidate } from '../assets/js/core/place-candidate.js';

function fixture(){
  const form={elements:Object.fromEntries(['name','address','latitude','longitude','hours','websiteUrl','mapUrl','googlePlaceId','district','description','summary','phone'].map(name=>[name,{value:'原值'}]))};
  const loaded=[];return {form,loaded,editor:{load:(...args)=>loaded.push(args)}};
}

for (const [summary, description] of [['',''],['自行摘要',''],['','自行介紹'],['自行摘要','自行介紹'],[' \n','　']]) {
  test(`Google 簡介逐欄補空白並保留人工內容 ${JSON.stringify([summary,description])}`,()=>{
    const f=fixture();f.form.elements.summary.value=summary;f.form.elements.description.value=description;
    applyPlaceCandidate(f.form,{editorialSummary:'Google 原文簡介'},f.editor);
    assert.equal(f.form.elements.summary.value,summary.trim()?summary:'Google 原文簡介');
    assert.equal(f.form.elements.description.value,description.trim()?description:'Google 原文簡介');
  });
}
for (const editorialSummary of [undefined,null,'','　\n',123]) {
  test(`沒有可用 Google 簡介時不填值 ${JSON.stringify(editorialSummary)}`,()=>{
    const f=fixture();f.form.elements.summary.value='';f.form.elements.description.value='';
    applyPlaceCandidate(f.form,{editorialSummary},f.editor);
    assert.equal(f.form.elements.summary.value,'');assert.equal(f.form.elements.description.value,'');
  });
}
test('重複匯入不覆寫已帶入內容，超長原文不截斷',()=>{
  const f=fixture();f.form.elements.summary.value='';f.form.elements.description.value='';
  f.form.elements.summary.maxLength=5;
  const notes=applyPlaceCandidate(f.form,{editorialSummary:'完整原文不可截斷'},f.editor);
  assert.equal(f.form.elements.summary.value,'');assert.equal(f.form.elements.description.value,'完整原文不可截斷');
  assert.match(notes.join(''),/超過欄位長度/);
  applyPlaceCandidate(f.form,{editorialSummary:'第二筆'},f.editor);
  assert.equal(f.form.elements.summary.value,'第二筆');assert.equal(f.form.elements.description.value,'完整原文不可截斷');
});
test('候選行政區更新與 HTTP 官網保留，週表走相同編輯器',()=>{
  const f=fixture(),openingHours={days:[]};
  const notes=applyPlaceCandidate(f.form,{name:'市場',address:'新竹市東區',district:'東區',latitude:0,longitude:120,websiteUrl:'http://example.test',openingHours},f.editor);
  assert.equal(f.form.elements.district.value,'東區');assert.equal(f.form.elements.latitude.value,'0');assert.equal(f.form.elements.websiteUrl.value,'http://example.test');assert.deepEqual(f.loaded,[[openingHours,undefined]]);assert.match(notes.join(''),/HTTP/);
});
test('候選無法確認行政區時清空原值，不能沿用北區',()=>{
  const f=fixture();f.form.elements.district.value='北區';
  const notes=applyPlaceCandidate(f.form,{name:'其他地點',district:''},f.editor);
  assert.equal(f.form.elements.district.value,'');assert.match(notes.join(''),/可稍後補填/);assert.equal(f.form.elements.description.value,'原值');assert.equal(f.form.elements.phone.value,'');
});
