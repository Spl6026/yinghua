"""[E-15] 真實 HTTP／MariaDB／Mailpit 驗收，固定 localhost:8081 與 yinghua_test。"""
import concurrent.futures, copy, hashlib, http.cookiejar, json, os, re, secrets, subprocess, time
import urllib.request, urllib.error
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
BASE = 'http://127.0.0.1:8081'
ORIGIN = 'http://localhost:8081'
MAIL = 'http://127.0.0.1:8025'
run = secrets.token_hex(4)
password = 'Test-only-' + secrets.token_urlsafe(18)
new_password = 'Changed-only-' + secrets.token_urlsafe(18)
checks = []
seen_mail = {}

def fixture(value, mode='sql', expect=True):
    result = subprocess.run(['docker','compose','exec','-T','app-test','php','tests/integration-fixture.php',mode],
        cwd=ROOT,input=value,text=True,encoding='utf-8',capture_output=True)
    if expect and result.returncode: raise AssertionError('Isolated fixture failed: ' + result.stderr)
    return result.returncode if not expect else (json.loads(result.stdout) if mode=='sql' else None)

def check(name, condition):
    if not condition: raise AssertionError(name)
    checks.append(name)

class Client:
    def __init__(self, kind='member'):
        self.kind=kind; self.cookies=http.cookiejar.CookieJar(); self.token=None
        self.http=urllib.request.build_opener(urllib.request.ProxyHandler({}),urllib.request.HTTPCookieProcessor(self.cookies))
    @property
    def auth(self): return '/api/v1/' + ('admin/auth/' if self.kind=='admin' else 'auth/')
    def call(self, method, path, body=None, csrf=True, extra=None):
        if csrf and method not in ('GET','HEAD') and self.token is None: self.refresh()
        headers={'Accept':'application/json','Origin':ORIGIN}
        if method not in ('GET','HEAD'):
            headers['Content-Type']='application/json'
            if csrf: headers['X-CSRF-Token']=self.token
        if extra: headers.update(extra)
        req=urllib.request.Request(BASE+path,data=None if body is None else json.dumps(body,ensure_ascii=False).encode(),headers=headers,method=method)
        try: response=self.http.open(req,timeout=15)
        except urllib.error.HTTPError as error: response=error
        data=response.read(); payload=json.loads(data) if data and response.headers.get('Content-Type','').startswith('application/json') else {}
        if payload.get('csrfToken'): self.token=payload['csrfToken']
        if response.status==204: self.token=None
        return response.status,payload,response.headers
    def refresh(self):
        code,body,_=self.call('GET',self.auth+'csrf'); assert code==200
        self.token=body['token']; return body
    def login(self, account, pw=password):
        return self.call('POST',self.auth+'login', {'account' if self.kind=='admin' else 'email':account,'password':pw})
    def clone(self):
        client=Client(self.kind)
        for cookie in self.cookies: client.cookies.set_cookie(copy.copy(cookie))
        client.token=self.token
        client.http=urllib.request.build_opener(urllib.request.ProxyHandler({}),urllib.request.HTTPCookieProcessor(client.cookies));return client

def mail_body(email, subject):
    http=urllib.request.build_opener(urllib.request.ProxyHandler({}))
    for attempt in range(50):
        with http.open(MAIL+'/api/v1/messages',timeout=5) as response: messages=json.load(response)['messages']
        for item in messages:
            if subject in item['Subject'] and any(to['Address']==email for to in item['To']) and item['ID'] != seen_mail.get((email,subject)):
                seen_mail[(email,subject)] = item['ID']
                with http.open(MAIL+'/api/v1/message/'+item['ID'],timeout=5) as response: return json.load(response)['Text']
        time.sleep(.1)
    raise AssertionError('Expected local Mailpit message not received')

def challenge(client, email):
    code,data,_=client.call('POST',client.auth+'captcha/email',{'email':email});assert code==200
    answer=re.search(r'\b\d{6}\b',mail_body(email,'註冊驗證碼')).group()
    return data['challengeToken'],answer

def register_body(client, email, token, answer):
    return dict(email=email,displayName='整合測試 '+run,password=password,passwordConfirmation=password,
        acceptTerms=True,termsVersion=client.refresh()['termsVersion'],challengeToken=token,answer=answer)

def admin_body(name, role='viewer'):
    return dict(account=name,displayName='測試 '+name,email=name+'@example.test',password=password,roleId=role,status='active')

def invalid_confirmations(client, operation, valid):
    for label, value in [('omitted', None), ('empty', ''), ('mismatch', 'different'), ('wrong type', 123)]:
        invalid = dict(valid)
        if value is None: invalid.pop('passwordConfirmation')
        else: invalid['passwordConfirmation'] = value
        code, payload, _ = client.call('POST', client.auth + operation, invalid)
        check(operation + ' rejects ' + label + ' confirmation: ' + client.kind,
              code == 422 and 'passwordConfirmation' in payload.get('fieldErrors', {}))

def main():
    fixture(password,'root'); fixture('DELETE FROM auth_rate_limits')
    (ROOT/'artifacts/phase2-test-credentials.json').write_text(json.dumps({'rootPassword':password,'run':run}),encoding='utf-8')
    root=Client('admin'); guest=Client(); admin_guest=Client('admin')
    check('member me requires login',guest.call('GET',guest.auth+'me')[0]==401)
    check('admin me requires login',admin_guest.call('GET',admin_guest.auth+'me')[0]==401)
    check('anonymous write requires CSRF',guest.call('POST',guest.auth+'login',{'email':'unknown@example.test','password':password},csrf=False)[0]==403)
    guest.refresh(); check('cross-origin write rejected',guest.call('POST',guest.auth+'login',{'email':'unknown@example.test','password':password},extra={'Origin':'https://attacker.test'})[0]==403)
    check('root login',root.login('admin')[0]==200)
    root_summary=root.call('GET',root.auth+'me')[1]; root_id=root_summary['admin']['id']
    check('root has exactly 40 rights',len(root_summary['permissions'])==40 and all(x['allowed'] for x in root_summary['permissions']))
    check('root DELETE blocked',root.call('DELETE','/api/v1/admin/admins/'+root_id,{})[0]==403)
    protected=dict(account='admin',displayName='changed',email='other@example.test',roleId='viewer',status='active',version=1)
    check('root PUT blocked',root.call('PUT','/api/v1/admin/admins/'+root_id,protected)[0]==403)
    root_matrix=root.call('GET','/api/v1/admin/admins/'+root_id+'/permissions')[1]
    check('root matrix immutable',root.call('PUT','/api/v1/admin/admins/'+root_id+'/permissions',{'version':root_matrix['version'],'permissions':root_matrix['permissions']})[0]==403)
    bad=admin_body('mass'+run);bad['isRoot']=True
    check('mass assignment rejected',root.call('POST','/api/v1/admin/admins',bad)[0]==422)
    check('root role cannot be granted',root.call('POST','/api/v1/admin/admins',admin_body('fakeroot'+run,'root'))[0]==403)
    viewer_name='view'+run; body=admin_body(viewer_name)
    code,created,_=root.call('POST','/api/v1/admin/admins',body);check('create administrator',code==201)
    viewer_id=created['admin']['id'];viewer=Client('admin'); check('created admin login',viewer.login(viewer_name)[0]==200)
    check('new account has no forced first password change',viewer.call('GET',viewer.auth+'me')[1]['admin']['mustChangePassword'] is False)
    change=dict(currentPassword=password,password=new_password,passwordConfirmation=new_password)
    invalid_confirmations(viewer, 'change-password', change)
    check('first password change',viewer.call('POST',viewer.auth+'change-password',change)[0]==204)
    check('changed password revokes session',viewer.call('GET',viewer.auth+'me')[0]==401)
    check('new password login',viewer.login(viewer_name,new_password)[0]==200)
    check('viewer cannot create',viewer.call('POST','/api/v1/admin/admins',admin_body('denied'+run))[0]==403)
    check('duplicate account rejected',root.call('POST','/api/v1/admin/admins',body)[0]==409)
    check('roles omit system root',all(role['id']!='root' for role in root.call('GET','/api/v1/admin/roles')[1]['roles']))
    check('catalog minimal target fields',all(set(x)=={'id','account','displayName','isRoot'} for x in root.call('GET','/api/v1/admin/permissions/catalog')[1]['targets']))
    matrix=root.call('GET','/api/v1/admin/admins/'+viewer_id+'/permissions')[1]
    check('40 personal rights',len(matrix['permissions'])==40)
    check('incomplete matrix rejected',root.call('PUT','/api/v1/admin/admins/'+viewer_id+'/permissions',{'version':matrix['version'],'permissions':matrix['permissions'][:39]})[0]==422)
    rights=copy.deepcopy(matrix['permissions'])
    for right in rights:
        if right['module']=='admins' and right['action']=='view':right['allowed']=True
    check('save personal rights',root.call('PUT','/api/v1/admin/admins/'+viewer_id+'/permissions',{'version':matrix['version'],'permissions':rights})[0]==204)
    check('stale matrix conflict',root.call('PUT','/api/v1/admin/admins/'+viewer_id+'/permissions',{'version':matrix['version'],'permissions':rights})[0]==409)
    check('permissions apply without login',viewer.call('GET','/api/v1/admin/admins')[0]==200)
    target=root.call('GET','/api/v1/admin/admins/'+viewer_id)[1]['admin']
    edit={key:target[key] for key in ['account','displayName','email','roleId','status','version']}
    edit['displayName']='並行測試'
    clients=[root.clone(),root.clone()]
    with concurrent.futures.ThreadPoolExecutor(2) as pool:
        codes=list(pool.map(lambda client:client.call('PUT','/api/v1/admin/admins/'+viewer_id,edit)[0],clients))
    check('concurrent writes have one conflict',sorted(codes)==[200,409])
    edit['version']+=1;edit['status']='disabled'
    check('disable admin',root.call('PUT','/api/v1/admin/admins/'+viewer_id,edit)[0]==200)
    check('disabled sessions revoked',viewer.call('GET',viewer.auth+'me')[0]==401)
    check('disabled login uniform',viewer.login(viewer_name,new_password)[0]==401)
    check('soft delete admin',root.call('DELETE','/api/v1/admin/admins/'+viewer_id,{'version':edit['version']+1})[0]==204)
    check('deleted record inaccessible',root.call('GET','/api/v1/admin/admins/'+viewer_id)[0]==404)
    check('root database deletion blocked',fixture("DELETE FROM admin_users WHERE account='admin'",expect=False)!=0)
    check('root database disable blocked',fixture("UPDATE admin_users SET status='disabled' WHERE account='admin'",expect=False)!=0)
    check('audit append-only constraint',fixture('DELETE FROM audit_logs',expect=False)!=0)
    audit=fixture("SELECT before_json,after_json FROM audit_logs WHERE action='admin-create' ORDER BY created_at DESC LIMIT 1")[0]
    check('audit contains no password or clear PII',password not in json.dumps(audit) and viewer_name+'@example.test' not in json.dumps(audit) and 'emailHash' in str(audit))
    fixture("CREATE TRIGGER test_audit_failure BEFORE INSERT ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='test failure'")
    rollback_name='rollback'+run
    try: check('audit failure rejects mutation',root.call('POST','/api/v1/admin/admins',admin_body(rollback_name))[0]==500)
    finally: fixture('DROP TRIGGER test_audit_failure')
    check('audit failure rolls back account and rights',not fixture("SELECT id FROM admin_users WHERE account='"+rollback_name+"'"))
    member=Client(); email='member'+run+'@example.test'
    image=member.call('GET',member.auth+'captcha')[1]
    check('captcha PNG no answer property',image['imageDataUrl'].startswith('data:image/png;base64,') and 'answer' not in image)
    token,answer=challenge(member,email); registration=register_body(member,email,token,answer)
    invalid_confirmations(member, 'register', registration)
    wrong=registration.copy();wrong['answer']='wrong'
    check('wrong captcha rejected',member.call('POST',member.auth+'register',wrong)[0]==422)
    stranger=Client(); stranger.refresh()
    check('captcha bound to anonymous session',stranger.call('POST',stranger.auth+'register',registration)[0]==422)
    code,data,_=member.call('POST',member.auth+'register',registration)
    check('member registration and automatic login',code==201 and data['member']['email']==email)
    member_id=data['member']['id'];check('member me',member.call('GET',member.auth+'me')[0]==200)
    check('member cookie cannot authenticate admin',member.call('GET','/api/v1/admin/auth/me')[0]==401)
    check('session cookies use HttpOnly and SameSite', 'HttpOnly' in str(member.call('POST',member.auth+'login',{'email':email,'password':password})[2]) and 'SameSite=Lax' in str(member.call('POST',member.auth+'login',{'email':email,'password':password})[2]))
    check('member has two consent records',len(fixture("SELECT id FROM member_consents WHERE member_id='"+member_id+"'"))==2)
    check('logout revokes member session',member.call('POST',member.auth+'logout',{})[0]==204 and member.call('GET',member.auth+'me')[0]==401)
    check('used captcha cannot be reused',member.call('POST',member.auth+'register',registration)[0]==422)
    check('member login',member.login(email)[0]==200)
    unknown=Client();known_response=member.call('POST',member.auth+'forgot-password',{'email':email})
    unknown_response=unknown.call('POST',unknown.auth+'forgot-password',{'email':'missing'+run+'@example.test'})
    check('reset response hides account existence',known_response[0]==unknown_response[0]==200 and known_response[1]['message']==unknown_response[1]['message'])
    text=mail_body(email,'密碼重設'); reset_token=re.search(r'#token=([a-f0-9]{64})',text).group(1)
    reset_body=dict(token=reset_token,password=new_password,passwordConfirmation=new_password)
    check('reset token wrong namespace rejected',root.call('POST',root.auth+'reset-password',reset_body)[0]==422)
    invalid_confirmations(unknown, 'reset-password', reset_body)
    check('member reset password',unknown.call('POST',unknown.auth+'reset-password',reset_body)[0]==204)
    check('reset revokes all sessions',member.call('GET',member.auth+'me')[0]==401)
    check('reset token is one use',unknown.call('POST',unknown.auth+'reset-password',reset_body)[0]==422)
    check('old password rejected',member.login(email)[0]==401)
    check('new member password works',member.login(email,new_password)[0]==200)
    second_member=Client();second_member.login(email,new_password)
    check('wrong current password rejected',member.call('POST',member.auth+'change-password',dict(currentPassword=password,password=password,passwordConfirmation=password))[0]==422)
    invalid_confirmations(member, 'change-password', dict(currentPassword=new_password,password=password,passwordConfirmation=password))
    check('member password change',member.call('POST',member.auth+'change-password',dict(currentPassword=new_password,password=password,passwordConfirmation=password))[0]==204)
    check('change revokes every member session',second_member.call('GET',second_member.auth+'me')[0]==401)
    member.login(email)
    fixture("UPDATE sessions SET idle_expires_at=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE member_id='"+member_id+"' AND revoked_at IS NULL")
    check('idle session expires',member.call('GET',member.auth+'me')[0]==401)
    member.login(email)
    fixture("UPDATE sessions SET expires_at=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE member_id='"+member_id+"' AND revoked_at IS NULL")
    check('absolute session expires',member.call('GET',member.auth+'me')[0]==401)
    expiring=Client();expiry_email='expiry'+run+'@example.test'
    expiry_token,expiry_answer=challenge(expiring,expiry_email)
    expiry_body=register_body(expiring,expiry_email,expiry_token,expiry_answer)
    token_hash=hashlib.sha256(expiry_token.encode()).hexdigest()
    fixture("UPDATE captcha_challenges SET expires_at=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE token_hash='"+token_hash+"'")
    check('expired captcha rejected',expiring.call('POST',expiring.auth+'register',expiry_body)[0]==422)
    exhausting=Client();exhaust_email='exhaust'+run+'@example.test'
    exhaust_token,exhaust_answer=challenge(exhausting,exhaust_email)
    exhaust_body=register_body(exhausting,exhaust_email,exhaust_token,exhaust_answer)
    for _ in range(3):
        wrong=exhaust_body.copy();wrong['answer']='wrong';check('captcha failure counted',exhausting.call('POST',exhausting.auth+'register',wrong)[0]==422)
    check('exhausted captcha rejects correct answer',exhausting.call('POST',exhausting.auth+'register',exhaust_body)[0]==422)
    replacement=Client();replacement_email='replace'+run+'@example.test'
    old_token,old_answer=challenge(replacement,replacement_email)
    replacement.call('GET',replacement.auth+'captcha')
    check('refresh invalidates old challenge',replacement.call('POST',replacement.auth+'register',register_body(replacement,replacement_email,old_token,old_answer))[0]==422)
    reused=Client();duplicate_token,duplicate_answer=challenge(reused,email)
    check('duplicate member email rejected',reused.call('POST',reused.auth+'register',register_body(reused,email,duplicate_token,duplicate_answer))[0]==409)
    # 新管理員專用重設流程，與會員 token、Cookie 完全分開。
    reset_admin='reset'+run;code,created,_=root.call('POST','/api/v1/admin/admins',admin_body(reset_admin))
    check('reset test admin created',code==201)
    root.call('POST',root.auth+'forgot-password',{'email':reset_admin+'@example.test'})
    admin_token=re.search(r'#token=([a-f0-9]{64})',mail_body(reset_admin+'@example.test','密碼重設')).group(1)
    admin_reset=dict(token=admin_token,password=new_password,passwordConfirmation=new_password)
    invalid_confirmations(admin_guest, 'reset-password', admin_reset)
    check('administrator reset',admin_guest.call('POST',admin_guest.auth+'reset-password',admin_reset)[0]==204)
    check('reset clears first password requirement',admin_guest.login(reset_admin,new_password)[1]['admin']['mustChangePassword'] is False)
    admin_guest.call('POST',admin_guest.auth+'forgot-password',{'email':reset_admin+'@example.test'})
    expired_reset=re.search(r'#token=([a-f0-9]{64})',mail_body(reset_admin+'@example.test','密碼重設')).group(1)
    fixture("UPDATE password_reset_tokens SET expires_at=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE token_hash='"+hashlib.sha256(expired_reset.encode()).hexdigest()+"'")
    check('expired reset token rejected',admin_guest.call('POST',admin_guest.auth+'reset-password',dict(token=expired_reset,password=password,passwordConfirmation=password))[0]==422)
    editor_name='editor'+run;code,created,_=root.call('POST','/api/v1/admin/admins',admin_body(editor_name,'content-editor'));check('editor created',code==201)
    editor_id=created['admin']['id'];editor=Client('admin');editor.login(editor_name)
    editor.call('POST',editor.auth+'change-password',dict(currentPassword=password,password=new_password,passwordConfirmation=new_password));editor.login(editor_name,new_password)
    editor_matrix=root.call('GET','/api/v1/admin/admins/'+editor_id+'/permissions')[1]
    original_rights=copy.deepcopy(editor_matrix['permissions'])
    for right in editor_matrix['permissions']:
        if right['module']=='admins' and right['action']=='delete':right['allowed']=False
    root.call('PUT','/api/v1/admin/admins/'+editor_id+'/permissions',{'version':editor_matrix['version'],'permissions':editor_matrix['permissions']})
    check('nonroot cannot grant removed right',editor.call('PUT','/api/v1/admin/admins/'+editor_id+'/permissions',{'version':editor_matrix['version']+1,'permissions':original_rights})[0]==403)
    check('nonroot cannot create broader role',editor.call('POST','/api/v1/admin/admins',admin_body('broader'+run,'content-editor'))[0]==403)
    check('nonroot without delete permission cannot delete self',editor.call('DELETE','/api/v1/admin/admins/'+editor_id,{})[0]==403)
    old_csrf=editor.token;editor.login(editor_name,new_password)
    check('login rotates anonymous CSRF',editor.call('POST',editor.auth+'logout',{},extra={'X-CSRF-Token':old_csrf})[0]==403)
    limiter=Client()
    for _ in range(5):check('failed login uniform',limiter.login('limit'+run+'@example.test')[0]==401)
    code,_,headers=limiter.login('limit'+run+'@example.test')
    check('rate limit supplies Retry-After',code==429 and int(headers['Retry-After'])>0)
    check('JSON API 404',guest.call('GET','/api/v1/missing')[0]==404)
    code,_,headers=guest.call('PUT',guest.auth+'me',{})
    check('JSON API 405 with Allow',code==405 and 'GET' in headers['Allow'])
    check('API HEAD has no body',guest.call('HEAD',guest.auth+'csrf')[0]==200)
    check('private source and env not published',all(guest.call('GET',path)[0]==404 for path in ['/.env','/backend/src/Config.php','/compose.yaml','/api/../.env']))
    check('admin logout',root.call('POST',root.auth+'logout',{})[0]==204)
    results={'checks':len(checks),'passed':checks,'database':'yinghua_test','baseUrl':ORIGIN,'run':run}
    (ROOT/'artifacts/phase2-integration-results.json').write_text(json.dumps(results,ensure_ascii=False,indent=2),encoding='utf-8')
    print(f'{len(checks)} integration checks passed against isolated MariaDB and local Mailpit.')

if __name__=='__main__':main()
