import test from 'node:test';
import assert from 'node:assert/strict';
import vm from 'node:vm';
import {readFile} from 'node:fs/promises';
const source=(await readFile(new URL('../assets/js/components/google-summary.js',import.meta.url),'utf8')).replace(/^import .*;\r?\n/gm,'').replace('export async function','async function');
class Node{
  constructor(tag,attrs={},text=''){this.tag=tag;this.attrs=attrs;this.textContent=text;this.children=[];this.dataset={googleSummary:'owned-place'};}
  append(...nodes){this.children.push(...nodes);}
  replaceChildren(...nodes){this.children=nodes;}
  setAttribute(name,value){this.attrs[name]=value;}
  removeAttribute(name){delete this.attrs[name];}
}
function fixture(){
  const context=vm.createContext({URL,element:(...args)=>new Node(...args)});vm.runInContext(source,context);
  const host=new Node('div');host.textContent='尚未提供景點介紹。';return {host,load:context.loadGoogleSummary};
}
test('Google原文完整當文字呈現，來源可辨，不把script當HTML',async()=>{
  const {host,load}=fixture();const text='  原文 <script>unsafe()</script>\n第二行  ';
  await load(host,async()=>({status:'available',text,mapUrl:'https://www.google.com/maps',attributions:[]}));
  assert.equal(host.children[0].children[1].textContent,text);
  assert.equal(host.children[0].children[0].textContent,'Google Maps');
  assert.equal(host.attrs['aria-busy'],undefined);
});
test('服務失敗或無簡介保持原空狀態且不搬動焦點',async()=>{
  for(const fetch of [async()=>({status:'unavailable'}),async()=>{throw new Error('timeout');}]){
    const {host,load}=fixture();await load(host,fetch);assert.equal(host.textContent,'尚未提供景點介紹。');assert.equal(host.children.length,0);
  }
});
test('來源網址仍拒絕javascript與帶帳密的外連',async()=>{
  const {host,load}=fixture();await load(host,async()=>({status:'available',text:'原文',mapUrl:'javascript:alert(1)',attributions:[{provider:'第三方',providerUri:'https://user:pass@example.test'}]}));
  assert.equal(host.children[0].children.filter(x=>x.tag==='a').length,0);
});
