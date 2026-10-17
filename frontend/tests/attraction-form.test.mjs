import test from 'node:test';
import assert from 'node:assert/strict';
import { readFile } from 'node:fs/promises';
import vm from 'node:vm';

// 真正執行表單與共用控制模組；只替換瀏覽器 DOM／API 邊界。
const source = (await Promise.all(['core/ui.js','core/session.js','core/place-candidate.js','pages/attraction-form.js'].map(path => readFile(new URL('../assets/js/'+path,import.meta.url),'utf8')))).map(text=>text.replace(/^import .*;\r?\n/gm,'').replace(/\bexport /g,''));
const html = await readFile(new URL('../pages/admin/place-add.html',import.meta.url),'utf8');
const fieldNames = [...html.matchAll(/\bname="([^"]+)"/g)].map(match=>match[1]).filter(name=>!['viewport','robots','referrer'].includes(name));
const settle = async () => { for(let i=0;i<6;i++) await new Promise(resolve=>setImmediate(resolve)); };
const deferred = () => { let resolve;const promise=new Promise(yes=>{resolve=yes;});return {promise,resolve}; };
class Node {
  constructor(tag='div'){this.tag=tag;this.attrs={};this.dataset={};this.children=[];this.listeners=new Map();this.value='';this.files=[];this.disabled=false;this.checked=false;this.textContent='';this.classList={add(){},remove(){}};}
  setAttribute(key,value){this.attrs[key]=value;}
  removeAttribute(key){delete this.attrs[key];}
  append(...children){for(const child of children){child.parent=this;this.children.push(child);}}
  replaceChildren(...children){this.children=[];this.append(...children);}
  addEventListener(type,callback){this.listeners.set(type,callback);}
  emit(type){return this.listeners.get(type)?.({preventDefault(){},currentTarget:this});}
  querySelectorAll(selector){if(selector.startsWith('input,select'))return this.children.filter(x=>['input','select','textarea'].includes(x.tag)||x.attrs['data-write']);if(selector==='[aria-invalid]')return this.children.filter(x=>x.attrs['aria-invalid']);if(selector==='.field-error')return this.children.filter(x=>x.className==='field-error');return [];}
  focus(){this.focused=true;}
  closest(){return this.parent;}
  remove(){this.parent.children=this.parent.children.filter(x=>x!==this);}
}
function fixture({creating=false,preview=false,edit=true,view=true}={}) {
  const nodes=new Map();const node=(id,tag='div')=>{if(!nodes.has(id))nodes.set(id,new Node(tag));return nodes.get(id);};
  const form=node('#place-form','form'),save=node('#save','button'),message=node('#place-form-status','p');
  form.elements={};
  for(const name of fieldNames){const input=new Node('input');input.name=name;input.id=name;form.elements[name]=input;form.append(input);}
  Object.defineProperty(form.elements,'namedItem',{value:name=>form.elements[name]});
  // 原生 file input 清空 value 時也會清空 FileList；選檔本身不代表上傳。
  let imageValue='';
  Object.defineProperty(form.elements.image,'value',{get:()=>imageValue,set:value=>{imageValue=value;if(value==='')form.elements.image.files=[];}});
  form.querySelector=selector=>selector==='[type="submit"]'?save:null;
  for(const id of ['#upload-place-image','#parse-place-url']){const button=node(id,'button');button.attrs['data-write']=true;form.append(button);}
  for(const id of ['#reload-place','#retry-place-publication','#place-photo-preview','#media-status','#map-status','#map-candidates','#google-short-url'])node(id);
  const item={id:'a',slug:'sample',name:'原景點',category:'景點',district:'北區',typeLabel:'文化場館',summary:'摘要',description:'介紹',address:'地址',hours:'09:00',publishAt:'2026-10-18T00:00:00+08:00',featured:false,isMuseum:false,version:1,status:'draft',image:'/media/original.png',mediaId:'media-a',imageAlt:'原圖說明',imageSource:'原來源',imageLicense:'原授權',recommendationLabel:'城市記憶'};
  const state={gets:0,saves:[],uploads:[],assignments:[],replacements:[],windowEvents:new Map(),confirm:true};
  const sandbox={URLSearchParams,HTMLElement:Node,routes:new Set(),passwordError:()=>'',
    document:{querySelector:id=>node(id),createElement:tag=>new Node(tag)},
    location:{search:'?id=a',origin:'http://localhost:8081',pathname:'/admin/place-edit.html',assign:url=>state.assignments.push(url),replace:url=>state.replacements.push(url)},
    history:{replaceState(){}},window:{addEventListener:(name,handler)=>state.windowEvents.set(name,handler),setTimeout(){}},
    FormData:class extends Map{},confirmAction:async()=>state.confirm,
    openingHoursEditor:()=>({load(value,legacy){state.openingHours=value;state.hoursLegacy=legacy;},read:()=>state.openingHours,setDisabled:value=>{state.hoursDisabled=value;}}),
    googlePlaceLink:()=>({load(){},acceptCandidate(){state.linkConfirmed=true;},setDisabled(value){state.linkDisabled=value;},canSave:()=>!state.linkStale,confirmed:()=>Boolean(state.linkConfirmed)}),
    renderPlaceReference:(container,item)=>{container.reference=item;},
    attractionSamplePicker:()=>({setDisabled(){},async load(){}}),
    attractionsApi:{get:async()=>{state.gets++;return state.get?state.get():{attraction:item};},save:async(id,body)=>{state.saves.push({id,body});return state.write?state.write():{attraction:{id:'a',version:2},publication:{status:'succeeded'}};},upload:async body=>{state.uploads.push(body);return state.upload?state.upload():{media:{id:'new-image',url:'/media/new.png',alt:'新說明',source:'新來源',license:'新授權',width:20,height:20}};},resolve:async()=>state.mapResult,retry:async()=>({message:'公開頁面已同步'})}};
  vm.createContext(sandbox);source.forEach(code=>vm.runInContext(code,sandbox));
  const identity={preview:{active:preview},permissions:[...(view?['view']:[]),'create',...(edit?['edit']:[])].map(action=>({module:'attractions',action,allowed:true}))};
  return {form,save,message,node,state,item,initialize:()=>sandbox.initializeAttractionForm(identity,creating?'place-add':'place-edit')};
}

test('只有新增權限保存後留在成功畫面，再次送出不重複新增',async()=>{
  const f=fixture({creating:true,view:false,edit:false});await f.initialize();
  assert.equal(f.node('#place-cancel').href,'/admin/place-add.html');
  f.form.emit('submit');await settle();
  assert.equal(f.state.assignments.length,0);assert.match(f.message.textContent,/已儲存/);
  assert.equal(f.node('#place-another').hidden,false);assert.equal(f.save.disabled,true);
  f.form.emit('submit');await settle();assert.equal(f.state.saves.length,1);
});
test('只有新增權限遇到同步失敗，保留已保存結果並禁止第二筆 POST',async()=>{
  const f=fixture({creating:true,view:false,edit:false});await f.initialize();
  f.state.write=async()=>({attraction:{id:'saved',version:1},publication:{status:'failed',message:'資料已保存，公開同步失敗'}});
  f.form.emit('submit');await settle();f.form.emit('submit');await settle();
  assert.equal(f.state.saves.length,1);assert.equal(f.save.disabled,true);
  assert.match(f.message.textContent,/資料已保存/);assert.equal(f.state.assignments.length,0);
});
test('只改圖片說明也送出完整保存資料及版本',async()=>{
  const f=fixture();await f.initialize();Object.assign(f.form.elements.imageAlt,{value:'修改說明'});f.form.elements.imageSource.value='修改來源';f.form.elements.imageLicense.value='修改授權';
  f.form.emit('submit');await settle();const body=f.state.saves[0].body;
  assert.equal(body.imageAlt,'修改說明');assert.equal(body.imageSource,'修改來源');assert.equal(body.imageLicense,'修改授權');assert.equal(body.recommendationLabel,'城市記憶');assert.equal(body.version,1);
  f.form.emit('submit');await settle();assert.equal(f.state.saves.length,1);
});
test('景點載入失敗不能送出；重試載入後恢復',async()=>{
 const f=fixture();f.state.get=async()=>{throw new Error('讀取失敗');};await f.initialize();assert.equal(f.save.disabled,true);f.form.emit('submit');await settle();assert.equal(f.state.saves.length,0);
 f.state.get=null;await f.node('#reload-place').emit('click');assert.equal(f.save.disabled,false);assert.equal(f.form.elements.imageAlt.value,'原圖說明');
});
test('取消重載保留尚未儲存的說明',async()=>{
 const f=fixture();await f.initialize();f.form.elements.imageAlt.value='尚未保存';f.form.emit('input');f.state.confirm=false;await f.node('#reload-place').emit('click');assert.equal(f.form.elements.imageAlt.value,'尚未保存');assert.equal(f.state.gets,1);
});
test('取消重載保留待上傳圖片；確認重載丟棄圖片並恢復可保存狀態',async()=>{
 const f=fixture();await f.initialize();
 f.form.elements.image.value='C:\\fakepath\\poster.png';f.form.elements.image.files=[{name:'poster.png'}];f.form.emit('input');
 f.state.confirm=false;await f.node('#reload-place').emit('click');
 assert.equal(f.form.elements.image.files.length,1);assert.equal(f.state.gets,1);
 f.state.confirm=true;await f.node('#reload-place').emit('click');
 assert.equal(f.form.elements.image.value,'');assert.equal(f.form.elements.image.files.length,0);
 assert.equal(f.form.elements.mediaId.value,'media-a');assert.equal(f.state.uploads.length,0);
 f.form.emit('submit');await settle();assert.equal(f.state.saves.length,1);
 assert.equal(f.state.saves[0].body.mediaId,'media-a');
});
test('景點重載失敗時保留待上傳檔案，成功重試後才清除',async()=>{
 const f=fixture();await f.initialize();f.form.elements.image.value='C:\\fakepath\\poster.png';f.form.elements.image.files=[{name:'poster.png'}];f.form.emit('input');
 f.state.get=async()=>{throw new Error('讀取失敗');};await f.node('#reload-place').emit('click');
 assert.equal(f.form.elements.image.files.length,1);assert.equal(f.save.disabled,true);
 f.state.get=null;await f.node('#reload-place').emit('click');
 assert.equal(f.form.elements.image.files.length,0);assert.equal(f.save.disabled,false);
});
test('409 衝突保留輸入；重新載入恢復伺服器版本',async()=>{
 const f=fixture();await f.initialize();f.form.elements.imageAlt.value='保留輸入';f.state.write=async()=>{throw Object.assign(new Error('資料已被修改'),{status:409});};f.form.emit('submit');await settle();assert.equal(f.form.elements.imageAlt.value,'保留輸入');assert.equal(f.save.disabled,false);assert.match(f.message.textContent,/資料已被修改/);
 await f.node('#reload-place').emit('click');assert.equal(f.form.elements.imageAlt.value,'原圖說明');
});
test('唯讀模擬重載後不能修改、上傳或送出',async()=>{
 const f=fixture({preview:true});await f.initialize();await f.node('#reload-place').emit('click');assert.equal(f.save.disabled,true);assert.equal(f.form.elements.imageAlt.disabled,true);await f.node('#upload-place-image').emit('click');f.form.emit('submit');await settle();assert.equal(f.state.saves.length,0);assert.equal(f.state.uploads.length,0);
});
test('儲存期間重新載入與連續送出不建立第二筆請求',async()=>{
 const f=fixture();await f.initialize();const wait=deferred();f.state.write=()=>wait.promise;f.form.emit('submit');await settle();await f.node('#reload-place').emit('click');f.form.emit('submit');await settle();assert.equal(f.state.gets,1);assert.equal(f.state.saves.length,1);wait.resolve({attraction:{id:'a',version:2},publication:{status:'succeeded'}});await settle();
});
test('202 已保存但同步失敗沿用新 id；重試不重複新增',async()=>{
 const f=fixture({creating:true});await f.initialize();f.state.write=async()=>({attraction:{id:'saved',version:1},publication:{status:'failed',message:'資料已保存，公開同步失敗'}});f.form.emit('submit');await settle();assert.equal(f.state.assignments.length,0);assert.equal(f.node('#retry-place-publication').hidden,false);assert.equal(f.state.saves[0].id,null);f.form.emit('submit');await settle();assert.equal(f.state.saves[1].id,'saved');assert.equal(f.state.saves[1].body.version,1);
});
test('僅 create 權限遇到202不能重送，明確提示聯絡編輯者',async()=>{
 const f=fixture({creating:true,edit:false});await f.initialize();f.state.write=async()=>({attraction:{id:'saved',version:1},publication:{status:'failed',message:'已保存但同步失敗'}});f.form.emit('submit');await settle();assert.equal(f.save.disabled,true);assert.match(f.message.textContent,/沒有重試權限/);f.form.emit('submit');await settle();assert.equal(f.state.saves.length,1);
});
test('上傳期間鎖定保存；完成後帶入新媒體與伺服器說明',async()=>{
 const f=fixture();await f.initialize();f.form.elements.image.files=[{}];const wait=deferred();f.state.upload=()=>wait.promise;const uploading=f.node('#upload-place-image').emit('click');await settle();f.form.emit('submit');await settle();assert.equal(f.state.saves.length,0);wait.resolve({media:{id:'new-image',url:'/media/new.png',alt:'新說明',source:'新來源',license:'新授權',width:20,height:20}});await uploading;assert.equal(f.form.elements.mediaId.value,'new-image');assert.equal(f.form.elements.imageAlt.value,'新說明');assert.equal(f.save.disabled,false);
});

test('確認候選帶入行政區與官網，但簡介只顯示參考、不改網站介紹',async()=>{
 const f=fixture();await f.initialize();f.node('#google-short-url').value='https://www.google.com/maps?q=test';
 const candidate={name:'東區地點',address:'新竹市東區',district:'東區',latitude:24.8,longitude:120.97,websiteUrl:'http://example.test',mapUrl:'https://maps.google.com/maps',googlePlaceId:'ChIJ_test',editorialSummary:'Google原文'};
 f.state.mapResult={candidates:[candidate],attribution:'Google Maps',notice:'請核對'};
 await f.node('#parse-place-url').emit('click');await f.node('#map-candidates').children[0].children.find(node=>node.tag==='button').emit('click');
 assert.equal(f.form.elements.district.value,'東區');assert.equal(f.form.elements.websiteUrl.value,'http://example.test');assert.equal(f.form.elements.description.value,'介紹');assert.equal(f.form.elements.summary.value,'摘要');assert.equal(f.node('#google-place-reference').reference.editorialSummary,'Google原文');
 f.form.emit('submit');await settle();assert.equal(f.state.saves[0].body.googleLinkConfirmed,true);assert.equal(Object.hasOwn(f.state.saves[0].body,'editorialSummary'),false);
});
test('取消候選確認不改動內容；未確認的新地點關聯不能送出',async()=>{
 const f=fixture();await f.initialize();f.state.confirm=false;f.node('#google-short-url').value='https://www.google.com/maps?q=test';f.state.mapResult={candidates:[{name:'另一地點',address:'地址'}],attribution:'Google Maps',notice:''};
 await f.node('#parse-place-url').emit('click');await f.node('#map-candidates').children[0].children.find(node=>node.tag==='button').emit('click');assert.equal(f.form.elements.name.value,'原景點');
 f.state.linkStale=true;f.form.emit('submit');await settle();assert.equal(f.state.saves.length,0);assert.equal(f.node('#google-place-link').focused,true);
});

test('圖片來源及授權說明留空仍可上傳，替代文字仍必填',async()=>{
 const f=fixture();await f.initialize();
 f.form.elements.image.files=[{}];f.form.elements.imageSource.value='';f.form.elements.imageLicense.value='';
 f.form.elements.imageAlt.value='';await f.node('#upload-place-image').emit('click');assert.equal(f.state.uploads.length,0);
 f.form.elements.imageAlt.value='測試圖片';await f.node('#upload-place-image').emit('click');assert.equal(f.state.uploads.length,1);
});
