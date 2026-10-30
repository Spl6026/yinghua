import test from 'node:test';
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import vm from 'node:vm';
import {CurrentTripState} from '../assets/js/core/current-trip-state.js';
import {TripEditorState} from '../assets/js/core/trip-editor-state.js';

// 執行真正頁面程式，只替換 DOM、session 與 HTTP 邊界；不代表瀏覽器排版驗收。
const sources = Object.fromEntries(await Promise.all(['favorites','planner'].map(async name=>[name,(await readFile(new URL('../assets/js/pages/'+name+'.js',import.meta.url),'utf8')).replace(/^import .*;\r?\n/gm,'').replace(/\bexport /g,'')])));
const settle=async()=>{for(let i=0;i<8;i++)await new Promise(resolve=>setImmediate(resolve));};
const deferred=()=>{let resolve;const promise=new Promise(done=>resolve=done);return {promise,resolve};};
class Node {
  constructor(tag='div'){this.tag=tag;this.children=[];this.listeners=new Map();this.dataset={};this.attrs={};this.hidden=false;this.disabled=false;this.isConnected=true;this.classList={add(){}};}
  append(...items){this.children.push(...items);}
  replaceChildren(...items){this.children=items;}
  setAttribute(name,value){this.attrs[name]=value;}
  removeAttribute(name){delete this.attrs[name];}
  toggleAttribute(name,value){if(name==='disabled')this.disabled=value;}
  querySelectorAll(){return [];}
  addEventListener(name,fn){this.listeners.set(name,fn);}
  emit(name,event={}){return this.listeners.get(name)?.({preventDefault(){},...event});}
  before(){}
  remove(){this.isConnected=false;}
  focus(){this.focused=true;}
}
async function fixture(page,search=''){
  const nodes=new Map(),node=id=>{if(!nodes.has(id))nodes.set(id,new Node());return nodes.get(id);};
  const data={member:{id:'a',displayName:'甲'},writes:[],trip:null,replacements:[],reads:0};
  const docEvents=new Map(),windowEvents=new Map();
  const api={favorites:async()=>({items:[],nextCursor:null}),itinerary:async()=>{data.reads++;return {itinerary:data.trip,nextCursor:null};},addStop:async(id,version)=>{data.writes.push({id,version});return {version:version+1};},removeStop:async(id,version)=>{data.writes.push({id,version});return {version:version+1};}};
  const document={hidden:false,querySelector:node,addEventListener:(name,fn)=>docEvents.set(name,fn)};
  const trips={list:async()=>{data.reads++;return{items:data.trip?[data.trip]:[],total:data.trip?1:0,page:1,currentId:data.trip?.id||null};},get:async()=>({itinerary:data.trip}),routes:async()=>({items:[]}),save:async(id,body)=>{data.writes.push(body);data.trip={...data.trip,...body,version:body.version+1};return{itinerary:data.trip};}};
  const sandbox={document,URLSearchParams,CurrentTripState,TripEditorState,itinerariesApi:trips,memberResourcesApi:api,readMember:async()=>data.member,requireSession:async()=>data.member,
    location:{search,replace:url=>data.replacements.push(url)},loginLocation:()=>'/login.html',handleSessionExpiry:()=>data.replacements.push('/login.html'),
    window:{addEventListener:(name,fn)=>windowEvents.set(name,fn)},clearMemberIntent:()=>{data.intentCleared=true;},resolvePublicAttraction:async()=>({id:'place-a',name:'公開地點'}),
    element:(tag,attrs={},text)=>{const n=new Node(tag);Object.assign(n,attrs);if(attrs.id)nodes.set('#'+attrs.id,n);n.textContent=text;return n;},status:(n,text,error)=>{n.textContent=text;n.error=error;},
    formErrors(){return false;},unsavedChanges(){},confirmAction:async()=>true,tripStopEditor:item=>Object.assign(new Node('article'),{item}),
    memberItineraryStop:item=>Object.assign(new Node(),{item}),placeCard:item=>Object.assign(new Node(),{item}),attractionCardData:item=>item,mountMemberActions:async()=>{}};
  vm.createContext(sandbox);
  const ready=vm.runInContext('(async()=>{'+sources[page]+'})()',sandbox);await ready;await settle();
  return {data,api,trips,node,document,docEvents,windowEvents};
}

test('收藏頁切回分頁先清除舊帳號資料，較慢舊回應不能蓋回',async()=>{
  const f=await fixture('favorites');const old=deferred(),fresh=deferred();let calls=0;
  f.api.favorites=()=>++calls===1?old.promise:fresh.promise;
  f.windowEvents.get('focus')();await settle();
  f.node('#favorites-list').append(new Node());f.data.member={id:'b'};
  f.docEvents.get('visibilitychange')();assert.equal(f.node('#favorites-list').children.length,0);await settle();
  fresh.resolve({items:[{attraction:{name:'乙收藏'}}],nextCursor:null});await settle();
  old.resolve({items:[{attraction:{name:'甲收藏'}}],nextCursor:null});await settle();
  assert.equal(f.node('#favorites-list').children[0].item.name,'乙收藏');
});

test('行程待辦網址只顯示確認，沒有 GET 自動寫入',async()=>{
  const f=await fixture('planner','?place=place-a');assert.equal(f.data.writes.length,0);assert.equal(f.data.reads,1);
});

test('行程保存成功後讀取失敗，仍提示已保存而非重送',async()=>{
  const f=await fixture('planner');
  f.data.trip={id:'trip-a',version:1,title:'甲行程',tripDate:null,isCurrent:true,items:[{attractionId:'x'}]};f.windowEvents.get('focus')();await settle();
  f.trips.list=async()=>{throw new Error('讀取失敗');};
  f.node('#trip-form').emit('submit');await settle();
  assert.equal(f.data.writes.length,1);assert.match(f.node('#trip-status').textContent,/已保存.*讀取失敗/);
  assert.equal(f.node('#itinerary-list').children.length,1);assert.equal(f.node('#reload-trip').disabled,false);
});

test('行程換帳號會清除原私人站點，不以另一帳號覆蓋草稿',async()=>{
  const f=await fixture('planner');f.data.trip={id:'trip-a',version:1,items:[{attractionId:'x'}]};
  f.windowEvents.get('focus')();await settle();assert.equal(f.node('#itinerary-list').children.length,1);
  f.data.member={id:'b'};f.docEvents.get('visibilitychange')();await settle();assert.equal(f.node('#itinerary-list').children.length,0);
  assert.match(f.node('#trip-status').textContent,/登入身分已變更/);
});
