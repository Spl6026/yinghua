import test from 'node:test';
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import vm from 'node:vm';
import {formatUtcDateTime} from '../assets/js/core/utc-display.js';
const source=(await readFile(new URL('../assets/js/pages/operations-overview.js',import.meta.url),'utf8')).replace(/^import .*;\r?\n/gm,'').replace('export async function','async function');
class Node{
  constructor(){this.children=[];this.childNodes=this.children;this.listeners={};this.textContent='';this.disabled=false;}
  append(...items){this.children.push(...items);}
  replaceChildren(...items){this.children=items;this.childNodes=this.children;}
  setAttribute(){}
  removeAttribute(){}
  addEventListener(type,fn){this.listeners[type]=fn;}
  emit(type){return this.listeners[type]?.();}
  all(){return this.children.flatMap(n=>[n,...n.all()]);}
}
async function fixture(root=false,preview=false){
  const host=new Node(),message=new Node(),retry=new Node(),calls=[];
  const sandbox={formatUtcDateTime,Object,document:{querySelector:selector=>({'#operations':host,'#operations-status':message,'#operations-retry':retry}[selector])},
    api:async path=>{calls.push(path);return path.includes('audit-logs')?{total:21,items:[{createdAt:'2026-01-01',action:'save',resourceType:'site_settings',resourceId:'general',actorId:'admin-id'}]}:{counts:{news:3},recent:[],auditAvailable:root};},
    element:(_tag,attrs,text)=>Object.assign(new Node(),attrs,{textContent:text}),status:(node,text)=>{node.textContent=text;},handleAdminError(){},hasPermission:(_identity,module,action)=>module==='news'&&action==='create'};
  vm.createContext(sandbox);vm.runInContext(source,sandbox);await sandbox.initializeOperations({preview:{active:preview}});return {host,calls};
}
test('普通帳號只顯示API允許統計與新增入口，不探測稽核',async()=>{
  const f=await fixture();assert.equal(f.calls.length,1);assert.deepEqual(f.host.all().filter(n=>n.href).map(n=>n.href),['/admin/news-add.html']);assert.ok(!f.host.all().some(n=>n.textContent==='會員'));
});
test('root稽核面板顯示最小資料，提供正確頁界控制',async()=>{
  const f=await fixture(true);assert.ok(f.calls.includes('/api/v1/admin/audit-logs?page=1'));assert.equal(f.host.all().find(n=>n.textContent==='稽核上一頁').disabled,true);assert.equal(f.host.all().find(n=>n.textContent==='稽核下一頁').disabled,false);assert.ok(f.host.all().some(n=>n.textContent==='general'));
});
test('唯讀模擬不顯示新增或root稽核入口',async()=>{
  const f=await fixture(true,true);assert.equal(f.calls.length,1);assert.equal(f.host.all().filter(n=>n.href).length,0);
});
