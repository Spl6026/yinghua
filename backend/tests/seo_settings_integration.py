"""在隔離環境驗證 SEO 設定的權限、版本、回滾與回應契約。"""
import argparse,concurrent.futures,copy,json,secrets,subprocess,sys,uuid
from pathlib import Path
parser=argparse.ArgumentParser();parser.add_argument('--dependency-path');options=parser.parse_args()
if options.dependency_path:sys.path.insert(0,str(Path(options.dependency_path).resolve()))
import jsonschema
from api_integration import Client,ROOT,fixture,password,admin_body,challenge,register_body

base='/api/v1/admin/settings/seo';checks=[];responses=0;prefix='p7-'+secrets.token_hex(4)
spec=json.loads((ROOT/'backend/resources/openapi.json').read_text(encoding='utf8'));validator=jsonschema.Draft202012Validator(spec)
def check(name,ok):
    if not ok:raise AssertionError(name)
    checks.append(name)
def call(client,method,path,body=None,csrf=True):
    global responses
    if method!='GET' and csrf:client.refresh()
    result=client.call(method,path,body,csrf=csrf)
    if path.split('?')[0]==base:
        response=spec['paths'][base][method.lower()]['responses'].get(str(result[0]));check('documented '+method+' '+str(result[0]),response is not None)
        validator.evolve(schema=response['content']['application/json']['schema']).validate(result[1]);responses+=1
        if result[0]==200 and method=='PUT':validator.evolve(schema={'$ref':'#/components/schemas/SeoSettingsInput'}).validate(body)
    return result
def payload(doc):return {key:copy.deepcopy(doc[key]) for key in ['version','defaults','overrides']}
def publish():
    subprocess.run(['docker','compose','exec','-T','-u','www-data','app-test','php','bin/console.php','content:publish'],cwd=ROOT,capture_output=True,check=True)
def literal(value):
    if value is None:return 'NULL'
    if isinstance(value,(int,float)):return str(value)
    return 'CONVERT(0x'+str(value).encode('utf8').hex()+' USING utf8mb4)'
def restore(table,rows):
    fixture('DELETE FROM '+table)
    for row in rows:fixture('INSERT INTO '+table+'('+','.join('`'+key+'`' for key in row)+') VALUES('+','.join(literal(value) for value in row.values())+')')

def main():
    fixture(password,'root');root=Client('admin');check('root login',root.login('admin')[0]==200)
    stored=fixture('SELECT * FROM site_settings');stored_overrides=fixture('SELECT * FROM content_seo')
    admins=[];places=[];member_id=None;trigger='p7_seo_audit_'+secrets.token_hex(4)
    def rights(admin_id,view=False,edit=False,attractions=False):
        matrix=root.call('GET','/api/v1/admin/admins/'+admin_id+'/permissions')[1]
        for right in matrix['permissions']:right['allowed']=(right['module']=='settings' and (right['action']=='view' and view or right['action']=='edit' and edit)) or (right['module']=='attractions' and right['action']=='view' and attractions)
        code,result,_=call(root,'PUT','/api/v1/admin/admins/'+admin_id+'/permissions',{'version':matrix['version'],'permissions':matrix['permissions']})
        check('permissions saved '+str(code)+' '+str(result.get('code','')),code==204)
    try:
        clients=[]
        for index in range(3):
            name=prefix.replace('-','')+str(index);code,created,_=call(root,'POST','/api/v1/admin/admins',admin_body(name));check('temporary admin created',code==201)
            admin_id=created['admin']['id'];admins.append(admin_id);rights(admin_id,view=index<2,edit=index==0)
            client=Client('admin');check('temporary admin login',client.login(name)[0]==200);clients.append(client)
        editor,viewer,denied=clients
        check('anonymous read denied',call(Client('admin'),'GET',base)[0]==401)
        member=Client();email=prefix+'@example.test';token,answer=challenge(member,email)
        code,registered,_=member.call('POST',member.auth+'register',register_body(member,email,token,answer));check('ordinary member fixture',code==201);member_id=registered['member']['id']
        check('member cookie is not administrator',call(member,'GET',base)[0]==401)
        check('no permission read denied',call(denied,'GET',base)[0]==403)
        current=call(root,'GET',base)[1];body=payload(current)
        check('view-only read works',call(viewer,'GET',base)[0]==200)
        check('view-only write denied',call(viewer,'PUT',base,body)[0]==403)
        check('write missing csrf denied',call(editor,'PUT',base,body,csrf=False)[0]==403)
        check('complete document required',call(editor,'PUT',base,{'version':body['version']})[0]==422)
        for change in [{'canonicalOrigin':'http://example.test'},{'googleVerification':'<script>bad</script>'},{'robots':'index,anything'}]:
            invalid=copy.deepcopy(body);invalid['defaults'].update(change);check('unsafe defaults rejected',call(editor,'PUT',base,invalid)[0]==422)
        body['defaults']['siteName']='隔離 SEO '+prefix;body['defaults']['canonicalOrigin']='https://example.test'
        body['overrides']=[{'target':{'type':'page','path':'/food.html'},'title':'隔離美食 SEO','description':'可選的摘要','keywords':''}]
        code,saved,_=call(editor,'PUT',base,body);check('settings persisted',code==200 and saved['version']==body['version']+1)
        check('clear keywords preserved',saved['overrides'][0]['keywords']=='')
        check('independent client sees settings',call(viewer,'GET',base)[1]['defaults']['siteName']==body['defaults']['siteName'])
        check('stale version refused',call(editor,'PUT',base,body)[0]==409)
        audit=fixture("SELECT before_json,after_json FROM audit_logs WHERE action='seo-update' AND resource_id='seo' ORDER BY id DESC LIMIT 1")[0]
        check('audit safe version event',json.loads(audit['before_json'])=={'version':body['version']} and json.loads(audit['after_json'])=={'version':saved['version'],'status':'published'})
        current=call(root,'GET',base)[1];before=payload(current);attempt=payload(current);attempt['defaults']['siteName']='must rollback'
        fixture(f"CREATE TRIGGER {trigger} BEFORE INSERT ON audit_logs FOR EACH ROW BEGIN IF NEW.action='seo-update' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='owned audit failure'; END IF; END")
        try:check('audit failure rejects whole save',call(root,'PUT',base,attempt)[0]==500)
        finally:fixture('DROP TRIGGER IF EXISTS '+trigger)
        check('all settings rolled back',payload(call(root,'GET',base)[1])==before)
        for state in ['draft','published']:
            place=str(uuid.uuid4());places.append(place)
            fixture(f"INSERT INTO attractions(id,slug,name,category,summary,description,address,status,publish_at) VALUES('{place}','{prefix}-{state}','{prefix}-{state}','景點','','','','{state}','2020-01-01')")
        publish()
        read=call(editor,'GET',base+'?q='+prefix)[1];check('draft selector hidden without content view',all(item['published'] for item in read['targets']) and all(item['id']!=places[0] for item in read['targets']))
        current=payload(call(root,'GET',base)[1]);current['overrides'].append({'target':{'type':'attraction','id':places[0]},'title':'不公開的草稿 SEO'})
        check('authorized root edits draft seo',call(root,'PUT',base,current)[0]==200)
        read=call(editor,'GET',base)[1];check('draft overrides hidden',all(item['target'].get('id')!=places[0] for item in read['overrides']))
        current=payload(read);current['defaults']['siteName']='keep hidden '+prefix;check('global edit works without reading draft',call(editor,'PUT',base,current)[0]==200)
        check('hidden draft override retained',any(item['target'].get('id')==places[0] for item in call(root,'GET',base)[1]['overrides']))
        current=payload(call(editor,'GET',base)[1]);current['overrides'].append({'target':{'type':'attraction','id':places[0]},'title':'cannot read'})
        check('blind draft overwrite rejected',call(editor,'PUT',base,current)[0]==422)
        a=Client('admin');b=Client('admin');check('independent concurrent sessions',a.login(prefix.replace('-','')+'0')[0]==b.login(prefix.replace('-','')+'0')[0]==200)
        current=payload(call(editor,'GET',base)[1]);a.refresh();b.refresh()
        def write(pair):client,label=pair;body=copy.deepcopy(current);body['defaults']['siteName']=label;return call(client,'PUT',base,body)[0]
        with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:codes=list(pool.map(write,[(a,'race A'),(b,'race B')]))
        check('only one concurrent same-version save',sorted(codes)==[200,409])
        preview='/api/v1/admin/auth/preview';check('enter readonly preview',call(root,'POST',preview,{'adminId':admins[0]})[0]==200)
        check('preview can read',call(root,'GET',base)[0]==200);check('preview cannot write',call(root,'PUT',base,payload(call(root,'GET',base)[1]))[0]==403)
        check('exit readonly preview',call(root,'DELETE',preview,{})[0]==200)
        rights(admins[0],view=False,edit=False);check('revocation effective next request',call(editor,'GET',base)[0]==403)
    finally:
        fixture('DROP TRIGGER IF EXISTS '+trigger)
        restore('content_seo',stored_overrides);restore('site_settings',stored)
        for place in places:fixture("DELETE FROM attractions WHERE id='"+place+"'")
        for admin_id in admins:
            fixture("DELETE FROM sessions WHERE admin_id='"+admin_id+"' OR preview_admin_id='"+admin_id+"'")
            fixture("UPDATE admin_users SET status='disabled',deleted_at=UTC_TIMESTAMP() WHERE id='"+admin_id+"'")
        if member_id:
            fixture("DELETE FROM sessions WHERE member_id='"+member_id+"'")
            fixture("UPDATE members SET status='disabled',deleted_at=UTC_TIMESTAMP() WHERE id='"+member_id+"'")
        publish()
    result={'checks':len(checks),'contracts':responses,'isolatedCleanup':True}
    (ROOT/'artifacts/phase7-settings-results.json').write_text(json.dumps(result,indent=2),encoding='utf8');print(json.dumps(result))
if __name__=='__main__':main()
