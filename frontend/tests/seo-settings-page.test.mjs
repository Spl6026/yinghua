import test from 'node:test';
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import vm from 'node:vm';
import {resolveSeo} from '../assets/js/core/seo-resolver.js';

const strip=text=>text.replace(/^import .*;\r?\n/gm,'').replace(/\bexport /g,'');
const source=await Promise.all(['components/seo-fields.js','pages/seo-settings.js'].map(async path=>strip(await readFile(new URL('../assets/js/'+path,import.meta.url),'utf8'))));
class Node{
  constructor(tag='div'){this.tag=tag;this.children=[];this.attrs={};this.dataset={};this.listeners={};this.value='';this.disabled=false;}
  setAttribute(key,value){this.attrs[key]=value;if(key.startsWith('data-'))this.dataset[key.slice(5).replace(/-([a-z])/g,(_,c)=>c.toUpperCase())]=value;else this[key]=value;}
  hasAttribute(key){return key in this.attrs;}
  append(...children){for(const child of children)child.parent=this;this.children.push(...children);}
  replaceChildren(...children){this.children=children;}
  addEventListener(type,fn){this.listeners[type]=fn;}
  emit(type,event={}){return this.listeners[type]?.({preventDefault(){},currentTarget:this,...event});}
  focus(){this.focused=true;}
  closest(tag){for(let node=this;node;node=node.parent)if(node.tag===tag)return node;return null;}
  all(){return this.children.flatMap(child=>[child,...child.all()]);}
  querySelectorAll(selector){return this.all().filter(node=>selector.split(',').includes(node.tag));}
}
const defaults={siteName:'映畫竹塹',titleSuffix:'｜映畫竹塹',description:'摘要',keywords:'新竹',canonicalOrigin:'',robots:'index,follow',ogImageUrl:'',twitterCard:'summary_large_image',twitterSite:'',googleVerification:'',bingVerification:''};
const doc=()=>({version:0,defaults:{...defaults},overrides:[],publicationReady:true,pages:[{path:'/food.html',label:'美食',title:'美食',description:'食物介紹',protected:false}],targets:[],targetsTruncated:false});
const settle=async()=>{for(let n=0;n<5;n++)await new Promise(resolve=>setImmediate(resolve));};
function deferred(){let resolve;const promise=new Promise(done=>resolve=done);return {promise,resolve};}
async function fixture(edit=true,preview=false){
  const form=new Node('form'),message=new Node(),reload=new Node('button');
  const state={reads:0,writes:[],confirm:true,read:async()=>doc(),save:async input=>({...doc(),...input,version:input.version+1}),errors:null};
  const sandbox={CustomEvent:class{constructor(type,init){this.type=type;this.detail=init?.detail;}},Object,Array,URLSearchParams,resolveSeo,location:{origin:'http://localhost:8081'},document:{listeners:{},addEventListener(type,fn){(this.listeners[type]??=[]).push(fn);},dispatchEvent(event){(this.listeners[event.type]||[]).forEach(fn=>fn(event));return true;},querySelector:selector=>({'#seo-settings-form':form,'#seo-status':message,'#reload-seo':reload}[selector])},
    element:(tag,attrs={},text)=>{const node=new Node(tag);for(const [key,value]of Object.entries(attrs))node.setAttribute(key,value);node.textContent=text;return node;},
    status:(node,text,error)=>{node.textContent=text;node.error=error;},formErrors:(_form,error)=>{state.errors=error.fieldErrors;state.errorFieldsEnabled=Object.keys(error.fieldErrors).every(name=>!form.all().find(node=>node.name===name)?.disabled);return false;},
    hasPermission:(_identity,_module,action)=>action!=='edit'||edit,handleAdminError(){},unsavedChanges:callback=>state.dirty=callback,
    confirmAction:async()=>state.confirm,seoSettingsApi:{read:async query=>{state.reads++;return state.read(query);},save:async input=>{state.writes.push(input);return state.save(input);}}};
  vm.createContext(sandbox);vm.runInContext(source.join('\n'),sandbox);await sandbox.initializeSeoSettings({preview:{active:preview}});
  return {state,form,message,reload,field:name=>form.all().find(node=>node.name===name),submit:()=>form.emit('submit')};
}
test('唯讀設定頁可以讀取與重新載入，不能提交',async()=>{
  const f=await fixture(false);assert.equal(f.state.reads,1);assert.equal(f.field('defaults.siteName').disabled,true);assert.equal(f.reload.disabled,false);
  await f.submit();assert.equal(f.state.writes.length,0);
  f.form.emit('input',{target:f.field('seo-target-query')});assert.equal(f.state.dirty(),false);
});

test('模擬身分即使具有 edit 也維持唯讀',async()=>{
  const f=await fixture(true,true);assert.equal(f.field('defaults.siteName').disabled,true);
  await f.submit();assert.equal(f.state.writes.length,0);
});
test('儲存中互斥且不把待發布結果顯示成網站已更新',async()=>{
  const f=await fixture();const wait=deferred();f.state.save=()=>wait.promise;f.field('defaults.siteName').value='新網站';f.form.emit('input');
  const pending=f.submit();await settle();await f.submit();assert.equal(f.state.writes.length,1);assert.equal(f.reload.disabled,true);
  wait.resolve({...doc(),version:1,publicationReady:false});await pending;
  assert.match(f.message.textContent,/已儲存.*待重試/);assert.equal(f.message.focused,true);assert.equal(f.state.dirty(),false);
});
test('版本衝突保留編輯值，重讀取消不丟資料，同意才重新讀取',async()=>{
  const f=await fixture();f.field('defaults.siteName').value='未保存';f.form.emit('input');f.state.save=async()=>{throw Object.assign(new Error('請重新讀取'),{status:409});};
  await f.submit();assert.equal(f.field('defaults.siteName').value,'未保存');assert.equal(f.state.dirty(),true);
  f.state.confirm=false;await f.reload.emit('click');assert.equal(f.state.reads,1);assert.equal(f.field('defaults.siteName').value,'未保存');
  f.state.confirm=true;await f.reload.emit('click');assert.equal(f.state.reads,2);assert.equal(f.field('defaults.siteName').value,'映畫竹塹');
});
test('搜尋單筆內容只改選項，不覆蓋未儲存設定；名稱保持純文字',async()=>{
  const f=await fixture();f.field('defaults.siteName').value='未保存';f.form.emit('input');
  f.state.read=async()=>({...doc(),targets:[{type:'film',id:'a',title:'<script>電影</script>',published:true}]});
  const button=f.form.all().find(node=>node.textContent==='搜尋內容');await button.emit('click');
  assert.equal(f.field('defaults.siteName').value,'未保存');assert.equal(f.state.dirty(),true);
  assert.ok(f.form.all().some(node=>node.tag==='option'&&node.textContent==='<script>電影</script>'));assert.ok(f.form.all().every(node=>node.tag!=='script'));
  f.field('content-choice').value='film:a';
  await f.form.all().find(node=>node.textContent==='加入單筆內容覆寫').emit('click');
  const added=f.form.all().filter(node=>node.tag==='pre').at(-1);
  assert.match(added.textContent,/電影/);
});
test('覆寫欄位錯誤展開原本關閉的 details 並對應原輸入欄位',async()=>{
  const f=await fixture();f.field('overrides.0.title').value='測試';
  f.state.save=async()=>{throw Object.assign(new Error('請檢查輸入'),{status:422,fieldErrors:{'overrides.0.title':'欄位錯誤'}});};
  await f.submit();assert.equal(f.field('overrides.0.title').closest('details').open,true);assert.equal(f.state.errors['overrides.0.title'],'欄位錯誤');assert.equal(f.state.errorFieldsEnabled,true);
});
