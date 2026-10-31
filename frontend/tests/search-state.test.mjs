import test from 'node:test';
import assert from 'node:assert/strict';
import {searchState,searchUrl,latestSearch} from '../assets/js/core/search-state.js';

test('中文、特殊字元及分類分頁可由網址往返，不變成HTML或其他網址',()=>{
  const state={q:'竹 %_ <img>&',type:'food',page:'2'};
  const url=searchUrl(state);assert.ok(url.startsWith('/search.html?'));assert.ok(!url.includes('<img>'));
  assert.deepEqual(searchState(new URL(url,'https://example.test').search),state);
  assert.equal(searchUrl({q:'',type:'',page:1}),'/search.html');
});
test('過時成功不能覆蓋新搜尋，即使先前請求較慢',async()=>{
  const pending=[];const seen=[];const run=latestSearch(()=>new Promise(resolve=>pending.push(resolve)));
  const first=run({q:'old'},x=>seen.push(x),()=>{});const second=run({q:'new'},x=>seen.push(x),()=>{});
  pending[1]('new');await second;pending[0]('old');await first;assert.deepEqual(seen,['new']);
});
test('過時錯誤不蓋掉新結果，清空搜尋也會淘汰舊請求',async()=>{
  let reject;const seen=[];const run=latestSearch(state=>state.q?new Promise((_,r)=>reject=r):Promise.resolve('empty'));
  const old=run({q:'old'},x=>seen.push(x),x=>seen.push(x));await run({q:''},x=>seen.push(x),()=>{});
  reject('obsolete');await old;assert.deepEqual(seen,['empty']);
});
test('目前錯誤交給重試流程，後續成功可以恢復',async()=>{
  let failed=true;const seen=[];const run=latestSearch(()=>failed?Promise.reject('offline'):Promise.resolve('restored'));
  await run({},x=>seen.push(x),e=>seen.push(e));failed=false;await run({},x=>seen.push(x),e=>seen.push(e));assert.deepEqual(seen,['offline','restored']);
});
