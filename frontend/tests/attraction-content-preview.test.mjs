import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFile} from 'node:fs/promises';
const source=(await readFile(new URL('../assets/js/components/attraction-content-preview.js',import.meta.url),'utf8')).replace(/^import .*;\r?\n/gm,'').replace('export function','function');
class Node{
  constructor(tag,attrs={},text=''){this.tag=tag;this.attrs=attrs;this.textContent=text;this.children=[];this.events={};this.disabled=false;}
  append(...items){this.children.push(...items);}
  before(){}
  replaceChildren(...items){this.children=items;}
  addEventListener(name,handler){this.events[name]=handler;}
  setAttribute(name,value){this.attrs[name]=value;}
  removeAttribute(name){delete this.attrs[name];}
}
function fixture(){
  let resolve,calls=0;
  const pending=new Promise(done=>{resolve=done;});
  const form=new Node('form');form.elements={summary:{value:''},description:{value:'網站介紹',closest:()=>({after(){}})},googlePlaceId:{value:''}};
  const context=vm.createContext({element:(...args)=>new Node(...args),api:()=>{calls++;return pending;}});vm.runInContext(source,context);
  const host=context.attractionContentPreview(form,()=>true);return {form,button:host.children[1],message:host.children[2],resolve,calls:()=>calls};
}
const result={summary:'網站介紹',description:'網站介紹',contentSource:{summary:'description-excerpt',description:'authored'}};
test('預覽等待保留可聚焦按鈕，阻擋重送且不改原欄位',async()=>{
  const f=fixture();const first=f.button.events.click();await f.button.events.click();
  assert.equal(f.calls(),1);assert.equal(f.button.disabled,false);assert.equal(f.button.attrs['aria-busy'],'true');
  f.resolve(result);await first;
  assert.equal(f.form.elements.summary.value,'');assert.equal(f.message.children[0].textContent,'列表摘要｜網站介紹摘錄：網站介紹');
  assert.equal(f.button.attrs['aria-busy'],undefined);
});
test('等待期間改文字，不把舊回應當目前內容預覽',async()=>{
  const f=fixture();const first=f.button.events.click();f.form.elements.description.value='新介紹';f.form.events.input();f.resolve(result);await first;
  assert.equal(f.message.children.length,0);assert.equal(f.message.textContent,'內容已變更，可重新預覽。');
});
