"""[S E13/G15/L15; E-27] Real isolated HTTP/MariaDB ownership, failure and contract tests."""
import argparse,concurrent.futures,json,secrets,subprocess,sys,uuid
from pathlib import Path
from urllib.parse import quote

parser=argparse.ArgumentParser();parser.add_argument('--dependency-path');options=parser.parse_args()
if options.dependency_path:sys.path.insert(0,str(Path(options.dependency_path).resolve()))
import jsonschema
from api_integration import Client,ROOT,fixture,password,challenge,register_body

spec=json.loads((ROOT/'backend/resources/openapi.json').read_text(encoding='utf8'))
validator=jsonschema.Draft202012Validator(spec);checks=[];responses=0
prefix='p6-'+secrets.token_hex(5)
def check(name,value):
    if not value:raise AssertionError(name)
    checks.append(name)
def call(client,method,path,body=None,csrf=True):
    global responses
    if method not in ('GET','HEAD') and csrf:client.refresh()
    result=client.call(method,path,body,csrf=csrf)
    if path.startswith('/api/v1/me/'):
        for route,verbs in sorted(spec['paths'].items(),key=lambda item:'{' in item[0]):
            import re
            if re.fullmatch(re.sub(r'\{[^}]+\}',r'[^/]+',route),path.split('?')[0]) and method.lower() in verbs:
                response=verbs[method.lower()]['responses'].get(str(result[0]))
                check('documented response '+method+' '+route+' '+str(result[0]),response is not None)
                if result[0]!=204 and response:
                    validator.evolve(schema=response['content']['application/json']['schema']).validate(result[1]);responses+=1
                break
        else:raise AssertionError('Undocumented member operation '+path)
    return result
def publish():
    result=subprocess.run(['docker','compose','exec','-T','-u','www-data','app-test','php','bin/console.php','content:publish'],cwd=ROOT,capture_output=True,text=True,encoding='utf8')
    if result.returncode:raise AssertionError(result.stdout+' '+result.stderr)
def member(label):
    client=Client();email=prefix+'-'+label+'@example.test'
    token,answer=challenge(client,email)
    code,data,_=client.call('POST',client.auth+'register',register_body(client,email,token,answer))
    check('register test member '+label,code==201)
    return client,data['member']['id'],email

def main():
    members=[];places=[str(uuid.uuid4()) for _ in range(4)]
    favorite='/api/v1/me/favorites';first=places[0]
    try:
        for index,target in enumerate(places):
            state='published' if index<3 else 'draft'
            fixture(f"INSERT INTO attractions(id,slug,name,category,summary,description,address,status,publish_at) VALUES('{target}','{prefix}-{index}','公開地點 {index}','景點','','','','{state}',UTC_TIMESTAMP())")
        publish()
        a,a_id,email=member('a');members.append(a_id)
        b,b_id,_=member('b');members.append(b_id)
        guest=Client();guest.refresh()
        check('anonymous read rejected',call(guest,'GET',favorite)[0]==401)
        check('anonymous write rejected',call(guest,'PUT',favorite+'/'+first)[0]==401)
        check('empty collection is real',call(a,'GET',favorite)[1]['items']==[])
        check('missing csrf rejected',call(a,'PUT',favorite+'/'+first,csrf=False)[0]==403)
        check('explicit favorite creates',call(a,'PUT',favorite+'/'+first)[0]==201)
        check('repeat PUT does not toggle',call(a,'PUT',favorite+'/'+first)[0]==200)
        check('same target only one row',len(fixture(f"SELECT attraction_id FROM favorites WHERE member_id='{a_id}' AND attraction_id='{first}'"))==1)
        check('other member cannot see favorite',call(b,'GET',favorite)[1]['items']==[])
        check('batch true false state', [row['favorited'] for row in call(a,'GET',favorite+'/status?ids='+first+','+places[1])[1]['statuses']]==[True,False])
        check('batch duplicates deduplicated',len(call(a,'GET',favorite+'/status?ids='+first+','+first)[1]['statuses'])==1)
        check('batch empty is not full collection',call(a,'GET',favorite+'/status')[1]['statuses']==[])
        check('numeric prototype index rejected',call(a,'PUT',favorite+'/1')[0]==404)
        check('member id body cannot assign owner',call(a,'PUT',favorite+'/'+places[1],{'memberId':b_id})[0]==422)
        check('member id query rejected',call(a,'GET',favorite+'?memberId='+b_id)[0]==422)
        check('draft target hidden',call(a,'PUT',favorite+'/'+places[3])[0]==404)
        check('missing target hidden',call(a,'PUT',favorite+'/'+str(uuid.uuid4()))[0]==404)
        check('invalid cursor rejected',call(a,'GET',favorite+'?cursor=bad-json')[0]==422)
        call(a,'PUT',favorite+'/'+places[1])
        page=call(a,'GET',favorite+'?pageSize=1')[1]
        check('cursor has next page',len(page['items'])==1 and isinstance(page['nextCursor'],str))
        second=call(a,'GET',favorite+'?pageSize=1&cursor='+quote(page['nextCursor']))[1]
        check('cursor stable no repeated row',len(second['items'])==1 and second['items'][0]['attractionId']!=page['items'][0]['attractionId'])
        check('cursor does not grant other member access',call(b,'GET',favorite+'?cursor='+quote(page['nextCursor']))[1]['items']==[])
        check('client body cannot reveal owner', 'memberId' not in json.dumps(page) and 'member_id' not in json.dumps(page))
        clone=Client();check('second browser login',clone.login(email)[0]==200)
        check('second browser sees same two',len(call(clone,'GET',favorite)[1]['items'])==2)
        clients=[a.clone(),a.clone()]
        with concurrent.futures.ThreadPoolExecutor(2) as pool:
            codes=list(pool.map(lambda client:call(client,'PUT',favorite+'/'+places[2])[0],clients))
        check('parallel favorite one create one existing',sorted(codes)==[200,201])
        fixture(f"UPDATE attractions SET status='archived',name='私人草稿名稱',summary='私人摘要',version=version+1 WHERE id='{first}'")
        pending=call(a,'GET',favorite)[1]
        check('pending publication does not disclose stale data',all(item['availability']=='unknown' and item['attraction'] is None for item in pending['items']))
        check('private body contains no draft text','私人' not in json.dumps(pending,ensure_ascii=False))
        check('remove works during publication failure',call(a,'DELETE',favorite+'/'+first)[0]==204)
        check('repeated removal succeeds',call(a,'DELETE',favorite+'/'+first)[0]==204)
        publish()
        check('archived cannot be re-added',call(a,'PUT',favorite+'/'+first)[0]==404)
        fixture(f"UPDATE attractions SET status='archived',version=version+1 WHERE id='{places[1]}'");publish()
        unavailable=call(a,'GET',favorite)[1]
        check('saved unavailable item retained',any(item['attractionId']==places[1] and item['availability']=='unavailable' and item['attraction'] is None for item in unavailable['items']))
        check('other user removal cannot remove A',call(b,'DELETE',favorite+'/'+places[1])[0]==204 and len(fixture(f"SELECT attraction_id FROM favorites WHERE member_id='{a_id}' AND attraction_id='{places[1]}'"))==1)
        fixture(f"UPDATE members SET status='disabled' WHERE id='{a_id}'")
        check('disabled account rejected',call(a,'GET',favorite)[0]==401)
        fixture(f"UPDATE members SET status='active' WHERE id='{a_id}'")
        check('relogin restores actual saved data',a.login(email)[0]==200 and len(call(a,'GET',favorite)[1]['items'])==2)
        a.refresh();check('logout succeeds',a.call('POST',a.auth+'logout',{})[0]==204)
        check('logged out private api rejected',call(a,'GET',favorite)[0]==401)
    finally:
        if members:
            ids=','.join("'"+value+"'" for value in members)
            fixture('DELETE FROM favorites WHERE member_id IN ('+ids+')')
            fixture('DELETE FROM sessions WHERE member_id IN ('+ids+')')
            fixture("UPDATE members SET status='disabled',deleted_at=UTC_TIMESTAMP() WHERE id IN ("+ids+')')
        ids=','.join("'"+value+"'" for value in places)
        fixture('DELETE FROM attractions WHERE id IN ('+ids+')');publish()
    print(json.dumps({'memberIntegrationChecks':len(checks),'contractResponses':responses,'fixturesRemoved':True}))
if __name__=='__main__':main()
