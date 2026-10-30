import test from 'node:test';
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import vm from 'node:vm';
const source=(await readFile(new URL('../assets/js/pages/route-list.js',import.meta.url),'utf8')).replace(/^import .*;\r?\n/gm,'').replace(/\bexport /g,'');
class Node{
 constructor(tag='div'){this.tag=tag;this.children=[];this.value='';this.events={};}
 get childNodes(){return this.children;}
 append(...nodes){this.children.push(...nodes);}
 replaceChildren(...nodes){this.children=nodes;}
 addEventListener(type,handler){this.events[type]=handler;}
 emit(type){return this.events[type]?.();}
 focus(){this.focused=true;}
}
function fixture(confirm=true){
 const nodes=new Map(),get=id=>{if(!nodes.has(id))nodes.set(id,new Node());return nodes.get(id);};
 const state={deleted:false,calls:0};
 const sandbox={document:{querySelector:get,createTextNode:text=>({textContent:text})},setTimeout,clearTimeout,
  service:{list:async()=>({items:state.deleted?[]:[{id:'route-a',title:'待刪路線',theme:'',stops:[],sortOrder:0,status:'published',version:1}],total:state.deleted?0:1}),delete:async()=>{state.calls++;state.deleted=true;return{publication:{status:'succeeded',message:'資料與公開頁面已同步。'}};}},
  hasPermission:()=>true,handleAdminError:()=>{},confirmAction:async()=>confirm,
  element:(tag,attrs={},text)=>Object.assign(new Node(tag),attrs,{textContent:text}),status:(node,text)=>{node.textContent=text;},
 };
 vm.createContext(sandbox);vm.runInContext(source,sandbox);
 return{get,state,initialize:()=>sandbox.initializeRouteList({}),remove:()=>get('#route-rows').children[0].children.at(-1).children.find(n=>n.tag==='button').emit('click')};
}
test('路線刪除後聚焦仍存在的結果訊息，避免焦點落到BODY',async()=>{
 const f=fixture();await f.initialize();await f.remove();
 assert.equal(f.state.calls,1);assert.equal(f.get('#route-list-status').focused,true);assert.equal(f.get('#route-list-status').textContent,'資料與公開頁面已同步。');
});
test('路線刪除取消不送出也不移除既有列',async()=>{
 const f=fixture(false);await f.initialize();await f.remove();
 assert.equal(f.state.calls,0);assert.equal(f.get('#route-rows').children.length,1);
});
