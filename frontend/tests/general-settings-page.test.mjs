import test from 'node:test';
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import vm from 'node:vm';
const source=(await readFile(new URL('../assets/js/pages/general-settings.js',import.meta.url),'utf8')).replace(/^import .*;\r?\n/gm,'').replace('export async function','async function');
class Node{
  constructor(tag='div'){this.tag=tag;this.children=[];this.listeners={};this.disabled=false;this.value='';}
  append(...items){this.children.push(...items);}
  replaceChildren(...items){this.children=items;}
  addEventListener(type,fn){this.listeners[type]=fn;}
  emit(type){return this.listeners[type]?.({preventDefault(){}});}
  focus(){this.focused=true;}
  all(){return this.children.flatMap(child=>[child,...child.all()]);}
  querySelector(){return this.all().find(node=>node.type==='submit');}
}
const documentValue=()=>({version:1,seoVersion:2,siteName:'映畫竹塹',description:'摘要',values:{contactEmail:'',termsVersion:'',privacyVersion:'',termsText:'',privacyText:''}});
async function fixture({edit=true,preview=false,readFailure=false}={}){
  const form=new Node('form'),message=new Node(),reload=new Node('button'),inputs=new Map();
  const state={reads:0,writes:[],error:null,confirm:true,save:async body=>({...body,version:2,seoVersion:3,publicationReady:true})};
  const sandbox={Object,Map,document:{querySelector:key=>({'#general-settings-form':form,'#general-status':message,'#reload-general':reload}[key])},
    api:async(_path,options)=>{if(options.method==='PUT'){state.writes.push(options.body);return state.save(options.body);}state.reads++;if(readFailure&&state.reads===1)throw new Error('read failed');return documentValue();},
    element:(tag,attrs,text)=>{const node=new Node(tag);Object.assign(node,attrs);if('disabled' in attrs)node.disabled=true;node.textContent=text;return node;},
    seoPanel:()=>({panel:new Node(),grid:new Node()}),seoField:(_label,name,value,options)=>{const input=new Node('input');input.value=value;input.disabled=options.disabled;inputs.set(name,input);return {wrapper:new Node(),input};},
    hasPermission:()=>edit,status:(node,text,error)=>{node.textContent=text;node.error=error;},handleAdminError:error=>{state.error=error;},formErrors:()=>{},unsavedChanges:fn=>{state.dirty=fn;},confirmAction:async()=>state.confirm};
  vm.createContext(sandbox);vm.runInContext(source,sandbox);await sandbox.initializeGeneralSettings({preview:{active:preview}});
  return {state,form,message,reload,inputs,submit:()=>form.emit('submit')};
}
test('可編輯表單的初始儲存按鈕可用，唯讀及模擬不提供寫入',async()=>{
  const edit=await fixture();assert.equal(edit.form.querySelector().disabled,false);
  for(const config of [{edit:false},{preview:true}]){const f=await fixture(config);assert.equal(f.form.querySelector().disabled,true);await f.submit();assert.equal(f.state.writes.length,0);f.form.emit('input');assert.equal(f.state.dirty(),false);}
});
test('儲存期間鎖定所有輸入與重新讀取，完成後使用最新雙版本',async()=>{
  const f=await fixture();let resolve;f.state.save=body=>new Promise(done=>{resolve=()=>done({...body,version:2,seoVersion:3,publicationReady:true});});
  const pending=f.submit();assert.equal(f.reload.disabled,true);assert.ok([...f.inputs.values()].every(input=>input.disabled));resolve();await pending;assert.equal(f.reload.disabled,false);assert.ok([...f.inputs.values()].every(input=>!input.disabled));
  f.state.save=async body=>({...body,publicationReady:true});await f.submit();assert.equal(f.state.writes[1].version,2);assert.equal(f.state.writes[1].seoVersion,3);assert.equal(f.message.focused,true);
});
test('409保留草稿及版本；取消重新讀取不丟失內容',async()=>{
  const f=await fixture();f.inputs.get('general.termsText').value='未儲存';f.form.emit('input');f.state.save=async()=>{throw Object.assign(new Error('衝突'),{status:409});};await f.submit();assert.equal(f.inputs.get('general.termsText').value,'未儲存');assert.equal(f.state.dirty(),true);f.state.confirm=false;await f.reload.emit('click');assert.equal(f.state.reads,1);
});
test('讀取失敗可重新載入；保存成功但發布未完成不冒稱未保存',async()=>{
  const f=await fixture({readFailure:true});assert.equal(f.reload.disabled,false);await f.reload.emit('click');assert.equal(f.state.reads,2);f.state.save=async body=>({...body,version:2,seoVersion:3,publicationReady:false});f.form.emit('input');await f.submit();assert.equal(f.state.dirty(),false);assert.equal(f.message.error,true);assert.match(f.message.textContent,/已儲存/);
});
