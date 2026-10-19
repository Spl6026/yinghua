import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';
import { screeningStart,screeningFields } from '../assets/js/core/screening-time.js';

test('使用台北日期、跨年及分鐘，拒絕日期溢位與只有單邊資料',()=>{
  assert.equal(screeningStart('2026-12-31','23:30'),'2026-12-31T23:30:00+08:00');
  assert.deepEqual(screeningFields('2026-12-31T16:30:00+00:00'),{date:'2027-01-01',time:'00:30'});
  assert.equal(screeningStart('',''),null);
  for(const [date,time]of [['2026-02-30','10:00'],['2026-10-19','24:00'],['','14:00'],['2026-10-19',''],['2026-10-19','10:01:30']])assert.throws(()=>screeningStart(date,time,2),error=>'screenings.2.startsAt'in error.fieldErrors);
});

const code=(await Promise.all(['core/ui.js','core/screening-time.js','components/screening-editor.js'].map(path=>readFile(new URL('../assets/js/'+path,import.meta.url),'utf8')))).map(text=>text.replace(/^import .*;\r?\n/gm,'').replace(/\bexport /g,''));
class Node {
  constructor(){this.children=[];this.dataset={};this.attrs={};this.value='';this.listeners={};}
  setAttribute(k,v){this.attrs[k]=v;}append(...nodes){for(const node of nodes){node.parent=this;this.children.push(node);}}
  replaceChildren(...nodes){this.children=[];this.append(...nodes);}addEventListener(type,fn){this.listeners[type]=fn;}
  emit(type){return this.listeners[type]?.();}remove(){this.parent.children=this.parent.children.filter(x=>x!==this);}focus(){this.focused=true;}
}
function fixture(){
  const container=new Node(),state={changes:0,confirm:true};
  const sandbox={document:{createElement:()=>new Node()},passwordError:()=>'',confirmAction:async()=>state.confirm};
  vm.createContext(sandbox);code.forEach(s=>vm.runInContext(s,sandbox));
  const editor=sandbox.screeningEditor(container,()=>state.changes++);
  const rows=()=>container.children[0].children;
  const fields=row=>row.children[1].children.map(field=>field.children[1]);
  return {editor,state,rows,fields,add:()=>container.children[2].emit('click')};
}
test('多場次載入／新增／刪除後保留 ID 與資料，錯誤可對到重編號的列',async()=>{
  const f=fixture();f.editor.load([{id:'a',startsAt:'2026-10-19T06:00:00+00:00',ticketUrl:'https://example.test/a'}]);f.add();
  const controls=f.fields(f.rows()[1]);controls[0].value='2026-10-19';controls[1].value='14:30';
  const body=f.editor.read();assert.equal(body.length,2);assert.equal(body[0].id,'a');assert.equal(body[1].startsAt,'2026-10-19T14:30:00+08:00');
  f.state.confirm=false;await f.rows()[0].children[2].emit('click');assert.equal(f.rows().length,2);
  f.state.confirm=true;await f.rows()[0].children[2].emit('click');assert.equal(f.rows().length,1);assert.equal(f.fields(f.rows()[0])[0].dataset.fieldName,'screenings.0.startsAt');
  assert.equal(f.editor.read()[0].startsAt,'2026-10-19T14:30:00+08:00');
});
test('零場次可儲存；新增的未完成列提示補齊或移除；唯讀不能新增刪除',async()=>{
  const f=fixture();f.editor.load([]);assert.equal(f.editor.read().length,0);f.add();assert.throws(()=>f.editor.read(),error=>'screenings.0.startsAt'in error.fieldErrors);
  f.editor.setDisabled(true);f.add();await f.rows()[0].children[2].emit('click');assert.equal(f.rows().length,1);assert.equal(f.fields(f.rows()[0])[0].disabled,true);
});
