"""[S E25:L25/E28:L28; E-36] Real isolated MariaDB and HTTP checks."""
import copy,json,sys,uuid,urllib.request
from urllib.parse import urlencode
from api_integration import ROOT,Client,fixture
from phase8_browser_fixture import literal,publish,php
sys.path.insert(0,str(ROOT/'artifacts/p0-full-20261028/deps'))
import jsonschema
checks=[];contracts=0
spec=json.loads((ROOT/'backend/resources/openapi.json').read_text(encoding='utf8'));validator=jsonschema.Draft202012Validator(spec)
m=json.loads((ROOT/'artifacts/phase8-browser-fixture.json').read_text(encoding='utf8'));assert not m['cleaned']
admin=Client('admin');assert admin.login(m['prefix'],m['password'])[0]==200
guest=Client();member=Client();assert member.login(m['prefix']+'@example.test',m['password'])[0]==200
manifest=ROOT/'artifacts/phase14/settings-fixture.json';backup=fixture("SELECT * FROM site_settings WHERE `key` IN ('general','seo')")
manifest.write_text(json.dumps({'prefix':m['prefix'],'settings':backup,'cleaned':False}),encoding='utf8')
created=[];success=False

def check(name,value):assert value,name;checks.append(name)
def call(client,method,path,body=None,expected=200,csrf=True):
    global contracts
    code,data,_=client.call(method,path,body,csrf=csrf);check(method+' '+path+' '+str(expected),code==expected)
    operation=spec['paths'][path.split('?')[0]][method.lower()];schema=operation['responses'][str(code)]['content']['application/json']['schema']
    check('response contract',not list(validator.evolve(schema=schema).iter_errors(data)));contracts+=1
    if code==200 and method=='PUT':check('request contract',not list(validator.evolve(schema=operation['requestBody']['content']['application/json']['schema']).iter_errors(body)))
    return data
def text(path):
    with urllib.request.build_opener(urllib.request.ProxyHandler({})).open('http://127.0.0.1:8081'+path) as response:return response.read().decode()
def restore():
    current=fixture("SELECT updated_by FROM site_settings WHERE `key` IN ('general','seo')")
    assert all(row['updated_by']==m['adminId'] or any(row['updated_by']==old['updated_by'] for old in backup) for row in current)
    fixture("DELETE FROM site_settings WHERE `key` IN ('general','seo')")
    for row in backup:
        fixture('INSERT INTO site_settings('+','.join('`'+k+'`' for k in row)+') VALUES('+','.join('NULL' if v is None else literal(v) for v in row.values())+')')
    publish();data=json.loads(manifest.read_text(encoding='utf8'));data['cleaned']=True;manifest.write_text(json.dumps(data),encoding='utf8')

try:
    for path in ['members','settings','dashboard','audit-logs']:
        call(guest,'GET','/api/v1/admin/'+path,expected=401);call(member,'GET','/api/v1/admin/'+path,expected=401)
    for i in range(23):
        identifier=str(uuid.uuid4());created.append(identifier)
        fixture('INSERT INTO members(id,email,display_name,password_hash) VALUES('+','.join(map(literal,[identifier,m['prefix']+'-directory-'+str(i)+'@example.test',m['prefix']+' 隱私會員 '+str(i),'not-a-real-hash']))+')')
    first=call(admin,'GET','/api/v1/admin/members?'+urlencode({'q':m['prefix'],'page':1}));second=call(admin,'GET','/api/v1/admin/members?'+urlencode({'q':m['prefix'],'page':2}))
    check('fixed page and total',len(first['items'])==20 and first['total']==25 and len(second['items'])==5)
    check('stable disjoint pages',not ({r['memberId'] for r in first['items']}&{r['memberId'] for r in second['items']}))
    check('no raw PII or credentials',all('＊＊＊' in r['displayName'] and '＊＊＊' in r['email'] and set(r)=={'memberId','displayName','email','status','joinedAt'} for r in first['items']))
    for q in ['%_',"' OR 1=1 --"]:check('literal search',call(admin,'GET','/api/v1/admin/members?'+urlencode({'q':q}))['total']==0)
    check('beyond last page',call(admin,'GET','/api/v1/admin/members?page=99999')['items']==[])
    call(admin,'GET','/api/v1/admin/members?page=0',expected=422);call(admin,'GET','/api/v1/admin/members?q='+('x'*81),expected=422)
    overview=call(admin,'GET','/api/v1/admin/dashboard');check('real count',overview['counts']['members']==int(fixture('SELECT COUNT(*) AS n FROM members WHERE deleted_at IS NULL')[0]['n']))
    call(admin,'GET','/api/v1/admin/audit-logs',expected=403)
    # Trusted read-only CLI uses existing root; no credentials minted or grants changed.
    audit=json.loads(php(r"""require 'vendor/autoload.php';$c=new Yinghua\Config();$d=Yinghua\Repositories\Database::connect($c);if($c->string('DB_DATABASE')!=='yinghua_test')throw new RuntimeException('isolation');$a=new Yinghua\Repositories\AccountRepository($d);$row=$d->row('SELECT id FROM admin_users WHERE is_root=1');$root=$a->find(Yinghua\Models\IdentityKind::Admin,$row['id']);$service=new Yinghua\Services\OperationsOverview(new Yinghua\Repositories\OperationsRepository($d),new Yinghua\Services\Authorization($a,new Yinghua\Repositories\PermissionRepository($d),new Yinghua\Policies\PermissionPolicy()));echo json_encode($service->audits($root,1),JSON_THROW_ON_ERROR);"""))
    check('root minimal projection',len(audit['items'])>0 and all(set(r)=={'id','actorId','action','resourceType','resourceId','createdAt'} for r in audit['items']))
    fixture("UPDATE admin_role_permissions a JOIN permissions p ON a.permission_id=p.id SET a.allowed=(p.module_key='dashboard' AND p.action_key='view') WHERE a.admin_id="+literal(m['adminId']))
    limited=call(admin,'GET','/api/v1/admin/dashboard');check('no aggregates disclosed',limited['counts']=={} and limited['recent']==[])
    call(admin,'GET','/api/v1/admin/members',expected=403);call(admin,'GET','/api/v1/admin/settings',expected=403)
    fixture('UPDATE admin_role_permissions SET allowed=1 WHERE admin_id='+literal(m['adminId']))
    current=call(admin,'GET','/api/v1/admin/settings');seo=admin.call('GET','/api/v1/admin/settings/seo')[1]
    body={k:current[k] for k in ['version','seoVersion','siteName','description','values']};body['values']={**body['values'],'contactEmail':'contact@example.test','termsVersion':'驗收條款-14','privacyVersion':'驗收隱私-14','termsText':'隔離測試服務條款\n<script>alert(1)</script> {{SITE_HEADER}}','privacyText':'隔離測試隱私政策：只供驗收。'}
    invalid=copy.deepcopy(body);invalid['key']='databasePassword';call(admin,'PUT','/api/v1/admin/settings',invalid,expected=422)
    invalid=copy.deepcopy(body);invalid['values']['contactEmail']='bad';call(admin,'PUT','/api/v1/admin/settings',invalid,expected=422)
    call(admin,'PUT','/api/v1/admin/settings',body,expected=403,csrf=False)
    saved=call(admin,'PUT','/api/v1/admin/settings',body);check('publication succeeded',saved['publicationReady'])
    call(admin,'PUT','/api/v1/admin/settings',body,expected=409)
    public=call(guest,'GET','/api/v1/site-settings/public');check('public synchronized',public['general']==body['values'] and public['siteName']==body['siteName'])
    after=admin.call('GET','/api/v1/admin/settings/seo')[1];check('SEO retained',all(after['defaults'][k]==v for k,v in seo['defaults'].items() if k not in ['siteName','description']) and after['overrides']==seo['overrides'])
    terms=text('/terms.html');privacy=text('/privacy.html');check('policy escaped','驗收條款-14' in terms and '&lt;script&gt;' in terms and '<script>alert(1)</script>' not in terms);check('policy template stays literal','{{SITE_HEADER}}' in terms and terms.count('class="site-header"')==1);check('privacy published','隔離測試隱私政策' in privacy)
    check('footer contact','mailto:contact@example.test' in text('/index.html'))
    metadata=guest.refresh();check('consent digest',metadata['termsVersion'].startswith('policy-') and len(metadata['termsVersion'])==71)
    auditrows=fixture("SELECT before_json,after_json FROM audit_logs WHERE actor_admin_id="+literal(m['adminId'])+" AND action='general-update'");check('audit excludes content',bool(auditrows) and 'contact@' not in json.dumps(auditrows) and '條款' not in json.dumps(auditrows,ensure_ascii=False))
    explain=fixture('EXPLAIN SELECT id,display_name,email,status,created_at FROM members WHERE deleted_at IS NULL ORDER BY created_at DESC,id DESC LIMIT 20');(ROOT/'artifacts/phase14/explain.json').write_text(json.dumps(explain,indent=2),encoding='utf8')
    success=True
finally:
    fixture('UPDATE admin_role_permissions SET allowed=1 WHERE admin_id='+literal(m['adminId']))
    for identifier in created:fixture('DELETE FROM members WHERE id='+literal(identifier))
    if not (success and '--keep-browser' in sys.argv):restore()
    result={'checks':len(checks),'contracts':contracts,'passed':success};(ROOT/'artifacts/phase14/integration.json').write_text(json.dumps(result),encoding='utf8');print(json.dumps(result))
