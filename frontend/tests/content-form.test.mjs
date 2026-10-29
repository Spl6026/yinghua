import test from 'node:test';
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import vm from 'node:vm';
const paths=['core/ui.js','core/session.js','pages/content-form.js'];
const source=(await Promise.all(paths.map(p=>readFile(new URL('../assets/js/'+p,import.meta.url),'utf8')))).map(s=>s.replace(/^import .*;\r?\n/gm,'').replace(/\bexport /g,'').replace("(await import('../components/offer-merchants.js')).offerMerchants","offerMerchants"));
const settle=async()=>{for(let i=0;i<8;i++)await new Promise(resolve=>setImmediate(resolve));};
class Node {
  constructor(tag='div'){this.tag=tag;this.attrs={};this.dataset={};this.children=[];this.listeners=new Map();this.value='';this.files=[];this.disabled=false;this.textContent='';this.classList={add(){},remove(){}};}
  setAttribute(k,v){this.attrs[k]=v;}removeAttribute(k){delete this.attrs[k];}
  append(...children){for(const c of children){c.parent=this;this.children.push(c);}}
  replaceChildren(...children){this.children=[];this.append(...children);}
  addEventListener(type,fn){this.listeners.set(type,fn);}emit(type){return this.listeners.get(type)?.({preventDefault(){},currentTarget:this});}
  querySelectorAll(selector){if(selector.startsWith('input,'))return this.children.filter(n=>['input','select','textarea'].includes(n.tag)||n.attrs['data-write']||n.tag==='button');if(selector==='[aria-invalid]')return this.children.filter(n=>n.attrs['aria-invalid']);if(selector==='.field-error')return this.children.filter(n=>n.className==='field-error');return [];}
  focus(){this.focused=true;}closest(){return this.parent;}remove(){this.parent.children=this.parent.children.filter(c=>c!==this);}
}

function fixture({creating=false,preview=false,view=true,edit=true,module='news'}={}){
 const nodes=new Map(),node=(id,tag='div')=>{if(!nodes.has(id))nodes.set(id,new Node(tag));return nodes.get(id);};
 const form=node('#content-form','form'),save=node('#save','button'),message=node('#content-form-status','p');form.elements={};form.append(save);
 for(const name of ['title','slug','status','coverUrl','coverMediaId','coverAlt','coverSource','coverLicense','seoTitle','seoDescription','publishAt','type','summary','body','eventLabel','eventLocation','eventStartAt','eventEndAt','merchantId','discount','description','terms','validFrom','validTo','image']){
  const n=new Node('input');n.name=name;n.id='content-'+name;form.elements[name]=n;form.append(n);
 }
 Object.defineProperty(form.elements,'namedItem',{value:name=>form.elements[name]});form.querySelector=s=>s==='[type="submit"]'?save:null;
 form.append(node('#upload-content-image','button'));
 const record={id:'a',title:'原標題',slug:'test',status:'published',type:'event',publishAt:'2026-10-29T19:24:39+08:00',version:1,merchantId:'m'};
 const state={saves:[],gets:0,assignments:[],confirm:true};
 const api={get:async()=>{state.gets++;return state.get?state.get():{record};},save:async(id,body)=>{state.saves.push({id,body});return state.write?state.write():{record:{...record,version:2},publication:{status:'succeeded',message:'已同步'}};},upload:async()=>({media:{id:'image',url:'/media/x.png',width:320,height:240}})};
 const sandbox={URLSearchParams,Intl,Date,HTMLElement:Node,FormData:class extends Map{},
  document:{querySelector:id=>node(id),createElement:tag=>new Node(tag)},
  location:{search:'?id=a',protocol:'http:',assign:url=>state.assignments.push(url)},window:{addEventListener(){},setTimeout(){}},
  confirmAction:async()=>state.confirm,contentAdminApi:()=>api,
  initializeContentBodyEditor:()=>({render(){}}),offerMerchants:()=>({load:async()=>{},select:async()=>{}})};
 vm.createContext(sandbox);source.forEach(s=>vm.runInContext(s,sandbox));
 const identity={preview:{active:preview},permissions:['create',...(view?['view']:[]),...(edit?['edit']:[])].map(action=>({module,action,allowed:true}))};
 return{form,save,message,state,node,initialize:()=>sandbox.initializeContentForm(identity,module,(module==='news'?'news':'offer')+(creating?'-add':'-edit'))};
}
for(const module of ['news']){
 test(module+'：202已保存禁止重複新增，只具新增權限不返回自己',async()=>{
  const f=fixture({module,creating:true,view:false,edit:false});await f.initialize();f.state.write=async()=>({record:{id:'new',version:1},publication:{status:'failed',message:'已保存，同步失敗'}});
  f.form.emit('submit');await settle();f.form.emit('submit');await settle();assert.equal(f.state.saves.length,1);assert.equal(f.save.disabled,true);assert.equal(f.node('#content-cancel').href,'/index.html');assert.equal(f.state.assignments.length,0);
 });
 test(module+'：讀取失敗鎖定，重試後可用；409保留輸入',async()=>{
  const f=fixture({module});f.state.get=async()=>{throw new Error('讀取失敗');};await f.initialize();f.form.emit('submit');await settle();assert.equal(f.state.saves.length,0);assert.equal(f.save.disabled,true);
  f.state.get=null;await f.node('#reload-content-form').emit('click');f.form.elements.title.value='保留文字';f.state.write=async()=>{throw Object.assign(new Error('版本衝突'),{status:409});};f.form.emit('submit');await settle();assert.equal(f.form.elements.title.value,'保留文字');assert.equal(f.save.disabled,false);assert.equal(f.message.focused,true);
 });
 test(module+'：唯讀模擬不可修改、上傳及送出',async()=>{
  const f=fixture({module,preview:true});await f.initialize();assert.equal(f.form.elements.title.disabled,true);f.form.emit('submit');await f.node('#upload-content-image').emit('click');await settle();assert.equal(f.state.saves.length,0);
 });
 test(module+'：取消重載保留草稿，確認重載清除錯誤',async()=>{
  const f=fixture({module});await f.initialize();f.form.elements.title.value='草稿';f.form.emit('input');f.state.confirm=false;await f.node('#reload-content-form').emit('click');assert.equal(f.form.elements.title.value,'草稿');assert.equal(f.state.gets,1);
  f.state.confirm=true;await f.node('#reload-content-form').emit('click');assert.equal(f.form.elements.title.value,'原標題');assert.equal(f.state.gets,2);
 });
}
