import test from 'node:test';
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import vm from 'node:vm';

const source=(await Promise.all(['core/ui.js','core/session.js','core/film-view.js','pages/film-form.js'].map(path=>readFile(new URL('../assets/js/'+path,import.meta.url),'utf8')))).map(text=>text.replace(/^import .*;\r?\n/gm,'').replace(/\bexport /g,''));
const html=await readFile(new URL('../pages/admin/film-add.html',import.meta.url),'utf8');
const names=[...html.matchAll(/\bname="([^"]+)"/g)].map(m=>m[1]).filter(name=>!['viewport','robots','referrer'].includes(name));
const settle=async()=>{for(let i=0;i<8;i++)await new Promise(resolve=>setImmediate(resolve));};
class Node {
  constructor(tag='div'){this.tag=tag;this.attrs={};this.dataset={};this.children=[];this.listeners=new Map();this.value='';this.files=[];this.disabled=false;this.textContent='';this.classList={add(){},remove(){}};}
  setAttribute(k,v){this.attrs[k]=v;}removeAttribute(k){delete this.attrs[k];}
  append(...children){for(const c of children){c.parent=this;this.children.push(c);}}
  replaceChildren(...children){this.children=[];this.append(...children);}
  addEventListener(type,fn){this.listeners.set(type,fn);}emit(type){return this.listeners.get(type)?.({preventDefault(){},currentTarget:this});}
  querySelectorAll(selector){if(selector.startsWith('input,select'))return this.children.filter(n=>['input','select','textarea'].includes(n.tag)||n.attrs['data-write']);if(selector==='[aria-invalid]')return this.children.filter(n=>n.attrs['aria-invalid']);if(selector==='.field-error')return this.children.filter(n=>n.className==='field-error');return [];}
  focus(){this.focused=true;}closest(){return this.parent;}remove(){this.parent.children=this.parent.children.filter(c=>c!==this);}
}
function fixture({creating=false,preview=false,edit=true,view=true}={}) {
  const nodes=new Map(),node=(id,tag='div')=>{if(!nodes.has(id))nodes.set(id,new Node(tag));return nodes.get(id);};
  const form=node('#film-form','form'),save=node('#save','button'),message=node('#film-form-status','p');form.elements={};
  for(const name of names){const n=new Node('input');n.name=name;n.id=name;form.elements[name]=n;form.append(n);}
  Object.defineProperty(form.elements,'namedItem',{value:name=>form.elements[name]});form.querySelector=s=>s==='[type="submit"]'?save:null;
  form.elements.posterMode.value='url';const upload=node('#upload-film-image','button');upload.attrs['data-write']=true;form.append(upload);
  const item={id:'a',title:'原電影',genre:'紀錄片',synopsis:'介紹',slug:'test',status:'draft',posterMediaId:null,posterUrl:'https://example.test/a.jpg',posterAlt:'原海報說明',posterSource:'',posterLicense:'',image:'https://example.test/a.jpg',ticketUrl:'',seoTitle:'',seoDescription:'',publishAt:'2026-10-19T00:00:00+08:00',version:1,screenings:[]};
  const state={saves:[],uploads:[],assignments:[],gets:0,confirm:true};
  const sandbox={URLSearchParams,HTMLElement:Node,passwordError:()=>'',
    screeningEditor:()=>({load:rows=>{state.screenings=rows;},read:()=>state.screenings||[],setDisabled:value=>{state.screeningsDisabled=value;}}),
    document:{querySelector:id=>node(id),createElement:tag=>new Node(tag)},
    location:{search:'?id=a',protocol:'http:',assign:url=>state.assignments.push(url)},history:{replaceState(){}},
    window:{addEventListener(){},setTimeout(){}},FormData:class extends Map{},confirmAction:async()=>state.confirm,
    filmsApi:{get:async()=>{state.gets++;return state.get?state.get():{film:item};},save:async(id,body)=>{state.saves.push({id,body});return state.write?state.write():{film:{...item,version:2},publication:{status:'succeeded'}};},upload:async body=>{state.uploads.push(body);return state.upload?state.upload():{media:{id:'poster-new',url:'/media/new.png',width:32,height:24}};},retry:async()=>({message:'已同步'})}};
  vm.createContext(sandbox);source.forEach(s=>vm.runInContext(s,sandbox));
  const identity={preview:{active:preview},permissions:['create',...(view?['view']:[]),...(edit?['edit']:[])].map(action=>({module:'films',action,allowed:true}))};
  return{form,save,message,node,state,item,initialize:()=>sandbox.initializeFilmForm(identity,creating?'film-add':'film-edit')};
}
test('保留原型海報網址、替代文字與編輯版本；成功後不能重複提交',async()=>{
  const f=fixture();await f.initialize();f.form.elements.posterAlt.value='新說明';f.form.emit('submit');await settle();
  assert.equal(f.state.saves[0].body.posterUrl,f.item.posterUrl);assert.equal(f.state.saves[0].body.posterAlt,'新說明');assert.equal(f.state.saves[0].body.version,1);
  f.form.emit('submit');await settle();assert.equal(f.state.saves.length,1);
});
test('讀取失敗不能寫入；409 保留輸入供重載',async()=>{
  const f=fixture();f.state.get=async()=>{throw new Error('載入失敗');};await f.initialize();assert.equal(f.save.disabled,true);f.form.emit('submit');await settle();assert.equal(f.state.saves.length,0);
  f.state.get=null;await f.node('#reload-film').emit('click');f.form.elements.synopsis.value='尚未保存';f.state.write=async()=>{throw new Error('資料已被修改');};f.form.emit('submit');await settle();assert.equal(f.form.elements.synopsis.value,'尚未保存');assert.equal(f.save.disabled,false);
});
test('唯讀模擬阻擋修改、上傳與保存',async()=>{
  const f=fixture({preview:true});await f.initialize();assert.equal(f.form.elements.title.disabled,true);f.form.emit('submit');await f.node('#upload-film-image').emit('click');await settle();assert.equal(f.state.saves.length,0);assert.equal(f.state.uploads.length,0);
});
test('上傳明確使用 films 模組、清除網址；未上傳的選檔阻擋保存',async()=>{
  const f=fixture();await f.initialize();f.form.elements.posterMode.value='upload';f.form.elements.posterMode.emit('change');f.form.elements.image.files=[{}];
  f.form.emit('submit');await settle();assert.equal(f.state.saves.length,0);
  await f.node('#upload-film-image').emit('click');assert.equal(f.state.uploads[0].get('module'),'films');assert.equal(f.form.elements.posterUrl.value,'');assert.equal(f.form.elements.posterMediaId.value,'poster-new');
});
test('新增已保存但公開失敗：只具新增權限不能重複新增',async()=>{
  const f=fixture({creating:true,edit:false});await f.initialize();f.form.elements.title.value='新片';f.state.write=async()=>({film:{...f.item,version:1},publication:{status:'failed',message:'已保存，同步失敗'}});
  f.form.emit('submit');await settle();assert.equal(f.save.disabled,true);f.form.emit('submit');await settle();assert.equal(f.state.saves.length,1);assert.match(f.message.textContent,/請勿重複新增/);
});
test('202 後有編輯權限，下一次使用既有 id/version 而非重複新增',async()=>{
  const f=fixture({creating:true});await f.initialize();f.state.write=async()=>({film:{...f.item,version:3},publication:{status:'failed',message:'已保存，同步失敗'}});
  f.form.emit('submit');await settle();f.form.emit('submit');await settle();assert.equal(f.state.saves[1].id,'a');assert.equal(f.state.saves[1].body.version,3);
});

test('僅新增權限保存成功後顯示結果，可再新增，不導向沒有權限的列表',async()=>{
  const f=fixture({creating:true,edit:false,view:false});await f.initialize();f.form.elements.title.value='新增權限電影';f.form.emit('submit');await settle();
  assert.equal(f.state.assignments.length,0);assert.equal(f.save.disabled,true);assert.match(f.message.textContent,/已儲存/);assert.equal(f.node('#film-another').hidden,false);assert.equal(f.node('#film-cancel').href,'/index.html');
});
