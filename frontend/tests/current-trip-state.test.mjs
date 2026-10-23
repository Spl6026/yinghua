import test from 'node:test';
import assert from 'node:assert/strict';
import {CurrentTripState} from '../assets/js/core/current-trip-state.js';

const deferred=()=>{let resolve;const promise=new Promise(done=>resolve=done);return {promise,resolve};};
function fixture(){
 const data={member:{id:'a'},trip:null,reads:0,writes:[]};
 const api={itinerary:async()=>{data.reads++;return {itinerary:data.trip,nextCursor:null};},addStop:async(id,version)=>{data.writes.push({id,version});return {itineraryId:'trip-a',version:version+1,attractionId:id,changed:true};},removeStop:async(id,version)=>{data.writes.push({id,version});return {itineraryId:'trip-a',version:version+1,attractionId:id,changed:true};}};
 return {data,api,state:new CurrentTripState(api,async()=>data.member)};
}
test('空行程讀取只 GET，首次加入用 version=0',async()=>{
 const {data,state}=fixture();await state.load();assert.equal(state.itinerary,null);assert.equal(data.writes.length,0);await state.change('place');assert.deepEqual(data.writes,[{id:'place',version:0}]);
});
test('載入失敗不能寫入，重試成功才開放',async()=>{
 const {api,data,state}=fixture();const read=api.itinerary;api.itinerary=async()=>{throw new Error('失敗');};await assert.rejects(state.load());assert.deepEqual(await state.change('x'),{busy:true});assert.equal(data.writes.length,0);api.itinerary=read;await state.load();assert.equal(state.loaded,true);
});
test('保存中連點與重載不重複送出',async()=>{
 const {api,data,state}=fixture();await state.load();const wait=deferred();api.addStop=async()=>{data.writes.push('once');await wait.promise;return {version:1};};const saving=state.change('x');assert.deepEqual(await state.change('x'),{busy:true});assert.equal(await state.load(),false);wait.resolve();await saving;assert.equal(data.writes.length,1);
});
test('409 不覆寫現有版本，要求重新載入後再操作',async()=>{
 const {api,state}=fixture();await state.load();api.addStop=async()=>{throw Object.assign(new Error('版本衝突'),{status:409});};await assert.rejects(state.change('x'));assert.equal(state.loaded,false);assert.deepEqual(state.items,[]);
});
test('逾時不假裝保存失敗或成功，也不自動重送',async()=>{
 const {api,data,state}=fixture();await state.load();api.addStop=async()=>{data.writes.push('sent');throw Object.assign(new Error('結果未知'),{status:0});};await assert.rejects(state.change('x'));assert.equal(state.loaded,false);assert.deepEqual(data.writes,['sent']);
});
test('跨頁不同版本不能拼接舊資料',async()=>{
 const {data,api,state}=fixture();data.trip={id:'trip-a',version:1,items:[{attractionId:'x'}]};await state.load();state.nextCursor='next';api.itinerary=async()=>({itinerary:{id:'trip-a',version:2,items:[{attractionId:'y'}]},nextCursor:null});await assert.rejects(state.load(true));assert.equal(state.loaded,false);assert.deepEqual(state.items,[]);
});
test('慢舊回應不蓋過重新登入後的行程',async()=>{
 const {api,data,state}=fixture();const wait=deferred();let calls=0;api.itinerary=()=>++calls===1?wait.promise:Promise.resolve({itinerary:null,nextCursor:null});const old=state.load();await new Promise(done=>setImmediate(done));state.reset();data.member={id:'b'};await state.load();wait.resolve({itinerary:{id:'old',version:1,items:[]},nextCursor:null});assert.equal(await old,false);assert.equal(state.member.id,'b');assert.equal(state.itinerary,null);
});
test('保存後換帳號會清除舊資料，不回報舊身分成功',async()=>{
 const {api,data,state}=fixture();await state.load();api.addStop=async()=>{data.member={id:'b'};return {version:1};};await assert.rejects(state.change('x'));assert.equal(state.member,undefined);assert.equal(state.loaded,false);
});
