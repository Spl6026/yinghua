import test from 'node:test';
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import vm from 'node:vm';

const source=(await readFile(new URL('../assets/js/core/member-actions.js',import.meta.url),'utf8')).replace(/^import .*;\r?\n/gm,'').replace(/\bexport /g,'');
const settle=()=>new Promise(resolve=>setImmediate(resolve));
function fixture(){
  let resolve,section=null;const waiting=new Promise(done=>resolve=done);
  const main={prepend:node=>{section=node;}};
  const sandbox={URLSearchParams,memberResourcesApi:{},readMember:async()=>null,location:{search:'?pendingFavorite=06000000-0000-4000-8000-000000000001'},
    authApi(){},MemberResourceState:class {async refresh(){return true;}},
    document:{querySelector:selector=>selector==='main'?main:section?.isConnected?section:null,querySelectorAll:()=>[]},
    api:()=>waiting,status:(node,text)=>node.textContent=text,toast(){},
    history:{replaceState(){}},URL,
    element:(tag,attrs={},text)=>({tag,...attrs,textContent:text,children:[],isConnected:true,listeners:new Map(),append(...nodes){this.children.push(...nodes);},addEventListener(name,fn){this.listeners.set(name,fn);},remove(){this.isConnected=false;}})};
  vm.createContext(sandbox);vm.runInContext(source,sandbox);
  vm.runInContext("clearMemberIntent=()=>{}",sandbox);
  return {start:()=>vm.runInContext('showFavoriteIntent()',sandbox),resolve,section:()=>section};
}

test('列表重畫重複掛載時不作廢正在讀取的收藏待辦',async()=>{
  const f=fixture();const first=f.start();await settle();await f.start();
  f.resolve({items:[{id:'06000000-0000-4000-8000-000000000001',name:'公開地點'}],total:1});await first;
  assert.match(f.section().children[0].textContent,/是否將.*公開地點/);assert.equal(f.section().children.length,3);
});

test('收藏地點載入中即可取消，晚回應不補上確認按鈕',async()=>{
  const f=fixture();const first=f.start();await settle();f.section().children[1].listeners.get('click')();
  f.resolve({items:[],total:0});await first;
  assert.equal(f.section().isConnected,false);assert.equal(f.section().children.length,2);
});
