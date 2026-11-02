import test from 'node:test';
import assert from 'node:assert/strict';
import {readFile} from 'node:fs/promises';
import vm from 'node:vm';
const file=await readFile(new URL('../assets/js/core/session.js',import.meta.url),'utf8');
const source=file.slice(file.indexOf('let memberNavigationVersion'),file.indexOf('export function handleAdminError')).replace('export async function','async function');
class Node{
  constructor(tag,text=''){this.tag=tag;this.textContent=text;this.children=[];this.attrs={};this.listeners={};}
  append(...items){this.children.push(...items);}
  replaceChildren(...items){this.children=items;}
  setAttribute(key,value){this.attrs[key]=value;}
  removeAttribute(key){delete this.attrs[key];}
  addEventListener(type,fn){this.listeners[type]=fn;}
  focus(){this.focused=true;}
  texts(){return [this.textContent,...this.children.flatMap(n=>n.texts())];}
}
function fixture(read){
  const node=new Node('span');node.append(new Node('a','會員登入'));
  const navigation=new Node('nav');navigation.querySelector=()=>null;
  const sandbox={initializeMenus(){},document:{querySelector:selector=>selector==='#member-navigation'?node:selector==='#primary-nav'?navigation:null},element:(tag,attrs={},text)=>{const n=new Node(tag,text);Object.assign(n,attrs);return n;},authApi:()=>({me:read}),toast(){}};
  vm.createContext(sandbox);vm.runInContext(source,sandbox);return {node,navigation,run:()=>sandbox.memberNavigation()};
}
test('等待帳號回應時不先顯示訪客；成功才顯示原有橫列會員資訊',async()=>{
  let resolve;const f=fixture(()=>new Promise(done=>{resolve=done;}));const pending=f.run();assert.deepEqual(f.node.texts(),['','確認帳號中…']);assert.equal(f.node.attrs['aria-busy'],'true');resolve({member:{displayName:'本人'}});await pending;assert.ok(f.node.texts().includes('本人'));assert.ok(f.node.texts().includes('我的收藏'));assert.ok(!f.node.children.some(n=>n.tag==='details'));assert.equal(f.node.attrs['aria-busy'],undefined);
});
test('401才顯示登入；網路失敗提供重試，不冒稱訪客',async()=>{
  const guest=fixture(async()=>{throw {status:401};});await guest.run();assert.ok(guest.node.texts().includes('會員登入 ↗'));
  const failure=fixture(async()=>{throw {status:0};});await failure.run();assert.ok(failure.node.texts().includes('重新確認帳號'));assert.ok(!failure.node.texts().includes('會員登入 ↗'));
});
test('舊帳號回應不得蓋過較新的身分確認',async()=>{
  let first,second,calls=0;const f=fixture(()=>new Promise(resolve=>{if(++calls===1)first=resolve;else second=resolve;}));const old=f.run(),fresh=f.run();second({member:{displayName:'新帳號'}});await fresh;first({member:{displayName:'舊帳號'}});await old;assert.ok(f.node.texts().includes('新帳號'));assert.ok(!f.node.texts().includes('舊帳號'));
});

test('手機漢堡選單另附收藏、更改密碼與登出，頁首不需擠入全部連結',async()=>{
  const f=fixture(async()=>({member:{displayName:'本人'}}));await f.run();
  const account=f.navigation.children.find(n=>n.className==='nav-account');
  assert.ok(account);assert.deepEqual(account.children.map(n=>n.textContent),['我的收藏','更改密碼','登出']);
});
