import test from 'node:test';
import assert from 'node:assert/strict';
import { recordSessionResponse,sessionSnapshot,sessionEpoch } from '../assets/js/core/session-events.js';
const response=(status=200,headers={})=>({status,ok:status>=200&&status<300,headers:new Headers(headers)});
const timing=kind=>({'X-Session-Kind':kind,'X-Session-Owner':'owner','X-Session-Server-Time':'1000','X-Session-Expires-At':'44200','X-Session-Idle-Expires-At':'2800'});
test('匿名401不建立登入恢復；有效本人資料不保存csrf或密碼',()=>{
  recordSessionResponse('/api/v1/auth/logout',response(),{},'member');
  recordSessionResponse('/api/v1/auth/me',response(401),{code:'UNAUTHENTICATED'},'member');
  assert.equal(sessionSnapshot('member').known,undefined);
  recordSessionResponse('/api/v1/auth/me',response(200,timing('member')),{member:{id:'owner',email:'a@example.test'},csrfToken:'never-store'},'member');
  assert.equal(sessionSnapshot('member').known,true);assert.equal(JSON.stringify(sessionSnapshot('member')).includes('never-store'),false);
  recordSessionResponse('/api/v1/auth/me',response(401),{code:'UNAUTHENTICATED'},'member');
  assert.equal(sessionSnapshot('member').expired,true);
});
test('登入失敗不清除原身份；模擬身分一律保留原操作者',()=>{
  recordSessionResponse('/api/v1/admin/auth/logout',response(),{},'admin');
  recordSessionResponse('/api/v1/admin/auth/me',response(200,timing('admin')),{admin:{id:'target',account:'target'},preview:{active:true,operator:{id:'owner',account:'admin'}}},'admin');
  recordSessionResponse('/api/v1/admin/auth/login',response(401),{code:'INVALID_CREDENTIALS'},'admin');
  assert.equal(sessionSnapshot('admin').identity.identifier,'admin');assert.equal(sessionSnapshot('admin').identity.preview,true);
});
test('編輯其他管理員的回應不會替換目前登入者',()=>{
  recordSessionResponse('/api/v1/admin/admins/target',response(200,timing('admin')),{admin:{id:'target',account:'target'}},'admin');
  assert.equal(sessionSnapshot('admin').identity.id,'owner');assert.equal(Boolean(sessionSnapshot('admin').changed),false);
});
test('登出後較慢的舊回應不能恢復過時期限',()=>{
  const epoch=sessionEpoch('member');recordSessionResponse('/api/v1/auth/logout',response(),{},'member');
  recordSessionResponse('/api/v1/auth/me',response(200,timing('member')),{member:{id:'owner',email:'a@example.test'}},'member',epoch);
  assert.equal(sessionSnapshot('member').known,undefined);
});
test('其他帳號回應會標記衝突，不將舊表單視為同一身分',()=>{
  recordSessionResponse('/api/v1/auth/me',response(200,timing('member')),{member:{id:'owner',email:'a@example.test'}},'member');
  recordSessionResponse('/api/v1/auth/me',response(200,{...timing('member'),'X-Session-Owner':'different'}),{member:{id:'different',email:'b@example.test'}},'member');
  assert.equal(sessionSnapshot('member').changed,true);
});
