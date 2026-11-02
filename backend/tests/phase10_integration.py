"""推薦路線的整合測試：使用指定的 yinghua_test 測試資料與真實 HTTP／資料庫，不寫入主資料庫。"""
import concurrent.futures,json,subprocess,uuid
from api_integration import ROOT,Client,fixture
from phase8_browser_fixture import literal,publish

checks=[]
def check(name,condition):
    if not condition:raise AssertionError(name)
    checks.append(name)

def main():
    m=json.loads((ROOT/'artifacts/phase8-browser-fixture.json').read_text(encoding='utf8'));assert not m['cleaned']
    admin=Client('admin');check('fixture login',admin.login(m['prefix'],m['password'])[0]==200)
    guest=Client();actor=m['adminId'];prefix=m['prefix'];base='/api/v1/admin/routes';ids=[];extra=str(uuid.uuid4())
    fixture("INSERT INTO attractions(id,slug,name,category,summary,description,address,status,publish_at) VALUES('"+extra+"',"+literal(prefix+'-route-second')+','+literal(prefix+' 第二站')+",'景點','','','','published','2020-01-01')")
    def call(method,path,body=None,expected=200,client=admin,**kwargs):
        code,data,_=client.call(method,path,body,**kwargs);check(method+' '+path+' '+str(expected),code==expected)
        return data
    def route(**kw):return {'title':prefix+' 推薦路線','stops':[{'attractionId':m['placeId']}],**kw}
    try:
        call('GET',base,expected=401,client=guest)
        call('POST',base,route(),expected=403,csrf=False)
        call('POST',base,route(stops=[]),expected=422)
        call('POST',base,route(stops=[{'attractionId':m['placeId']},{'attractionId':m['placeId']}]),expected=422)
        call('POST',base,route(stops=[{'attractionId':str(uuid.uuid4())}]),expected=422)
        call('POST',base,route(durationMinutes=-1),expected=422)
        call('POST',base,route(isRoot=True),expected=422)
        draft=call('POST',base,route(),expected=201)['record'];ids.append(draft['id'])
        check('optional theme summary duration',draft['theme']=='' and draft['summary']=='' and draft['durationMinutes'] is None)
        check('draft never public',all(x['id']!=draft['id'] for x in call('GET','/api/v1/routes',client=guest)['items']))
        a=call('POST',base,route(title=prefix+' route A',theme='散策',sortOrder=8,status='published',stops=[{'attractionId':m['placeId'],'suggestedTime':'10:30','note':'第一站'},{'attractionId':extra}]),expected=201)['record'];ids.append(a['id'])
        b=call('POST',base,route(title=prefix+' route B',sortOrder=1,status='published'),expected=201)['record'];ids.append(b['id'])
        listing=call('GET','/api/v1/routes',client=guest)
        own=[x for x in listing['items'] if x['id'] in ids]
        check('route order matches sortOrder',[x['id'] for x in own]==[b['id'],a['id']])
        check('stop order preserved',[s['attractionId'] for s in own[1]['stops']]==[m['placeId'],extra])
        check('public no editor version','version' not in own[1])
        html=guest.http.open('http://127.0.0.1:8081/routes.html').read().decode()
        check('HTML same order as API',html.index(prefix+' route B')<html.index(prefix+' route A'))
        check('no prototype replacement','data-managed-routes="true"' in html)
        check('prototype travel navigation selected','class="active" aria-current="page" href="/planner.html"' in html)
        call('GET',base+'/attractions?q='+prefix)
        check('literal search does not wildcard',call('GET',base+'?q=%25')['total']==0)
        check('theme filter',all(x['theme']=='散策' for x in call('GET','/api/v1/routes?theme='+__import__('urllib.parse',fromlist=['quote']).quote('散策'),client=guest)['items']))
        call('DELETE','/api/v1/admin/attractions/'+m['placeId'],{'version':1},expected=409)
        changed=route(title=prefix+' route A',status='published',sortOrder=0,version=a['version'],stops=[{'attractionId':extra},{'attractionId':m['placeId']}])
        a=call('PUT',base+'/'+a['id'],changed)['record']
        check('reorder persists',a['stops'][0]['attractionId']==extra)
        call('PUT',base+'/'+a['id'],changed,expected=409)
        attempts=[{**changed,'version':a['version'],'title':prefix+' parallel '+str(i)} for i in range(2)]
        clients=[admin.clone(),admin.clone()]
        with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
            responses=list(pool.map(lambda i:clients[i].call('PUT',base+'/'+a['id'],attempts[i])[0],range(2)))
        check('concurrent edit has one winner',sorted(responses)==[200,409])
        # 公開快照會拿掉剛下架的站點，而且一站都沒有的路線絕不公開。
        fixture("UPDATE attractions SET status='archived' WHERE id='"+extra+"'");publish()
        public=call('GET','/api/v1/routes',client=guest)
        aa=next(x for x in public['items'] if x['id']==a['id']);check('archived stop skipped',len(aa['stops'])==1 and aa['stops'][0]['attractionId']==m['placeId'])
        fresh=call('GET',base+'/'+a['id'])['record']
        call('PUT',base+'/'+a['id'],{**changed,'version':fresh['version']},expected=422)
        fixture("UPDATE attractions SET status='archived' WHERE id='"+m['placeId']+"'");publish()
        check('zero usable routes hidden',all(x['id'] not in ids for x in call('GET','/api/v1/routes',client=guest)['items']))
        fixture("UPDATE attractions SET status='published' WHERE id IN ('"+extra+"','"+m['placeId']+"')");publish()
        # 使用真實的權限設定、不建立新帳號；最後在 finally 還原測試帳號原本的權限矩陣。
        fixture("UPDATE admin_role_permissions ap JOIN permissions p ON p.id=ap.permission_id SET ap.allowed=(p.action_key='view') WHERE ap.admin_id='"+actor+"' AND p.module_key='routes'")
        call('GET',base)
        call('POST',base,route(),expected=403)
        call('DELETE',base+'/'+draft['id'],{'version':draft['version']},expected=403)
        fixture("UPDATE admin_role_permissions ap JOIN permissions p ON p.id=ap.permission_id SET ap.allowed=(p.action_key='create') WHERE ap.admin_id='"+actor+"' AND p.module_key='routes'")
        call('GET',base,expected=403)
        call('GET',base+'/attractions')
        c=call('POST',base,route(),expected=201)['record'];ids.append(c['id'])
        call('GET',base+'/'+c['id'],expected=403)
        fixture("UPDATE admin_role_permissions ap JOIN permissions p ON p.id=ap.permission_id SET ap.allowed=1 WHERE ap.admin_id='"+actor+"' AND p.module_key='routes'")
        for rid in ids:
            record=call('GET',base+'/'+rid)['record'];call('DELETE',base+'/'+rid,{'version':record['version']});call('GET',base+'/'+rid,expected=404)
        check('deleted route references released',not fixture("SELECT route_id FROM route_attractions WHERE route_id IN ("+','.join("'"+i+"'" for i in ids)+')'))
        audit=fixture("SELECT COUNT(*) AS n FROM audit_logs WHERE actor_admin_id='"+actor+"' AND resource_type='routes'")
        check('route writes audited',int(audit[0]['n'])>=7)
    finally:
        fixture("UPDATE admin_role_permissions ap JOIN permissions p ON p.id=ap.permission_id SET ap.allowed=1 WHERE ap.admin_id='"+actor+"' AND p.module_key='routes'")
        for rid in ids:
            fixture("DELETE FROM route_attractions WHERE route_id='"+rid+"'");fixture("DELETE FROM routes WHERE id='"+rid+"'")
        fixture("UPDATE attractions SET status='published' WHERE id='"+m['placeId']+"'")
        fixture("DELETE FROM attractions WHERE id='"+extra+"'");publish()
    target=ROOT/'artifacts/phase10';target.mkdir(exist_ok=True)
    (target/'integration.json').write_text(json.dumps({'checks':checks,'passed':len(checks)},ensure_ascii=False,indent=2),encoding='utf8')
    print(json.dumps({'passed':len(checks),'database':'yinghua_test'}))

if __name__=='__main__':main()
