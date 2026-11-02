"""以 yinghua_test 實際驗證最新消息與優惠的 HTTP 與資料庫行為；只用測試自有的資料，不動主資料。"""
import concurrent.futures,json,re,subprocess,sys
from pathlib import Path
from api_integration import ROOT,Client,fixture

checks=[]
def check(name,condition):
    if not condition:raise AssertionError(name)
    checks.append(name)

def main():
    m=json.loads((ROOT/'artifacts/phase8-browser-fixture.json').read_text(encoding='utf8'));assert not m['cleaned']
    admin=Client('admin');check('owned administrator login',admin.login(m['prefix'],m['password'])[0]==200)
    prefix=m['prefix'];actor=m['adminId'];guest=Client();paths=[]
    def call(method,path,body=None,expected=200,client=admin,**kwargs):
        code,data,_=client.call(method,path,body,**kwargs);check(method+' '+path+' '+str(expected),code==expected)
        if code>=400:check('error envelope',all(key in data for key in ['code','message','fieldErrors','requestId']))
        return data
    def publish():subprocess.run(['docker','compose','exec','-T','-u','www-data','app-test','php','bin/console.php','content:publish'],cwd=ROOT,check=True,capture_output=True)
    try:
        for module in ['news','offers']:
            call('GET','/api/v1/admin/'+module,expected=401,client=guest)
        call('POST','/api/v1/admin/news',{'title':'x','type':'event'},expected=403,csrf=False)
        merchant=call('POST','/api/v1/admin/offers/merchants',{'name':prefix+'合作店家'},expected=201)['record']
        check('standalone merchant optional fields',merchant['attractionId'] is None and merchant['phone']=='')
        exact=call('GET','/api/v1/admin/offers/merchants?merchantId='+merchant['id']);check('exact selector stable id and version',len(exact['items'])==1 and exact['items'][0]['version']==1)
        news={'title':prefix+'活動一','slug':prefix+'-event-a','type':'event','summary':'','body':'','status':'draft'}
        n=call('POST','/api/v1/admin/news',news,expected=201)['record'];paths.append(('news',n['id']))
        check('optional summary body event dates',n['summary']=='' and n['body']=='' and n['eventStartAt'] is None)
        call('GET','/api/v1/news/'+n['slug'],expected=404,client=guest)
        call('POST','/api/v1/admin/news',{**news,'type':'offers'},expected=422)
        call('POST','/api/v1/admin/news',{**news,'slug':prefix+'-bad-dates','eventStartAt':'2026-10-29T18:00:00+08:00','eventEndAt':'2026-10-29T12:00:00+08:00'},expected=422)
        safe={**news,'version':n['version'],'status':'published','body':'<p onclick="alert(1)">安全正文</p><script>alert(1)</script>'}
        n=call('PUT','/api/v1/admin/news/'+n['id'],safe)['record']
        check('server rich text sanitized','onclick' not in n['body'] and '<script' not in n['body'] and '安全正文' in n['body'])
        call('PUT','/api/v1/admin/news/'+n['id'],{**safe,'version':1,'title':'stale'},expected=409)
        call('PUT','/api/v1/admin/news/'+n['id'],{**news,'version':0},expected=422)
        call('PUT','/api/v1/admin/news/00000000-0000-4000-8000-000000000000',{**news,'version':1},expected=404)
        call('POST','/api/v1/admin/news',{**news,'slug':n['slug']},expected=409)
        public=call('GET','/api/v1/news/'+n['slug'],client=guest)['article']
        check('public excludes admin metadata',not {'version','deletedAt','status','coverMediaId'}&set(public))
        n2=call('POST','/api/v1/admin/news',{**news,'title':prefix+'活動二','slug':prefix+'-event-b','status':'published','publishAt':'2020-01-01T00:00:00+08:00'},expected=201)['record'];paths.append(('news',n2['id']))
        city=call('POST','/api/v1/admin/news',{**news,'title':prefix+'城市消息','slug':prefix+'-city','type':'city','status':'published'},expected=201)['record'];paths.append(('news',city['id']))
        neighbors=call('GET','/api/v1/news/'+n['slug']+'/neighbors',client=guest)
        for topic,suffix in [('attractions','-place'),('films','-film')]:
            adjacent=call('GET','/api/v1/'+topic+'/'+prefix+suffix+'/neighbors',client=guest)
            check(topic+' shared snapshot neighbors',len(adjacent['generation'])==64)
        check('neighbor generation present',len(neighbors['generation'])==64)
        for side in ['previous','next']:
            if neighbors[side]:
                other=call('GET','/api/v1/news/'+neighbors[side]['slug'],client=guest)['article'];check('same news classification',other['type']=='event')
        call('GET','/api/v1/news/not-published-'+prefix+'/neighbors',expected=404,client=guest)
        call('POST','/api/v1/admin/offers',{'title':prefix+'優惠','merchantId':merchant['id'],'discount':'九折','terms':''},expected=422)
        call('POST','/api/v1/admin/offers',{'title':prefix+'優惠','merchantId':'00000000-0000-4000-8000-000000000000','discount':'九折','terms':'條款'},expected=422)
        offer={'title':prefix+'優惠','slug':prefix+'-offer','merchantId':merchant['id'],'discount':'九折','terms':'出示頁面，每人一次。','status':'published'}
        call('POST','/api/v1/admin/offers',{**offer,'validFrom':'2026-10-30','validTo':'2026-10-29'},expected=422)
        o=call('POST','/api/v1/admin/offers',offer,expected=201)['record'];paths.append(('offers',o['id']))
        check('open validity bounds',o['validFrom'] is None and o['validTo'] is None)
        pub=call('GET','/api/v1/offers/'+o['slug'],client=guest)['offer'];check('offer terms and merchant',pub['terms']==offer['terms'] and pub['merchant']['id']==merchant['id'])
        listing=call('GET','/api/v1/admin/news?q='+prefix);check('offers do not enter news',all(x['type'] in ['event','city'] and x['title']!=offer['title'] for x in listing['items']))
        merchant=call('PUT','/api/v1/admin/offers/merchants/'+merchant['id'],{'name':prefix+'修正店家','version':merchant['version'],'status':'inactive'})['record']
        call('GET','/api/v1/offers/'+o['slug'],expected=404,client=guest)
        merchant=call('PUT','/api/v1/admin/offers/merchants/'+merchant['id'],{'name':prefix+'修正店家','version':merchant['version'],'status':'active'})['record']
        check('shared profile updates public offer',call('GET','/api/v1/offers/'+o['slug'],client=guest)['offer']['merchant']['name']==merchant['name'])
        o=call('PUT','/api/v1/admin/offers/'+o['id'],{**offer,'version':o['version'],'validTo':'2020-01-01'})['record']
        check('expired detail explicit',call('GET','/api/v1/offers/'+o['slug'],client=guest)['offer']['validity']=='expired')
        call('GET','/api/v1/admin/offers/'+o['id'])
        call('GET','/api/v1/offers/'+o['slug']+'/neighbors',client=guest)
        check('expired excluded active list',not any(x['id']==o['id'] for x in call('GET','/api/v1/offers?q='+prefix,client=guest)['items']))
        check('expired included admin filter',any(x['id']==o['id'] for x in call('GET','/api/v1/admin/offers?active=false&q='+prefix)['items']))
        # 權限在每次請求時重新讀取；所有 SQL 都經過 integration-fixture.php，限制只能動測試庫。
        fixture("UPDATE admin_role_permissions SET allowed=0 WHERE admin_id='"+actor+"'")
        for module in ['news','offers']:
            call('GET','/api/v1/admin/'+module,expected=403)
        fixture("UPDATE admin_role_permissions a JOIN permissions p ON p.id=a.permission_id SET a.allowed=1 WHERE a.admin_id='"+actor+"' AND p.module_key='news' AND p.action_key='create'")
        createonly=call('POST','/api/v1/admin/news',{**news,'title':prefix+'僅新增','slug':prefix+'-create-only'},expected=201)['record'];paths.append(('news',createonly['id']))
        call('GET','/api/v1/admin/news/'+n['id'],expected=403)
        fixture("UPDATE admin_role_permissions a JOIN permissions p ON p.id=a.permission_id SET a.allowed=(p.module_key IN ('news','offers') AND p.action_key='edit') WHERE a.admin_id='"+actor+"'")
        call('GET','/api/v1/admin/news/'+n['id']);call('GET','/api/v1/admin/offers/merchants?merchantId='+merchant['id'])
        call('GET','/api/v1/admin/news',expected=403);call('POST','/api/v1/admin/offers/merchants',{'name':prefix+'拒絕'},expected=403)
        fixture("UPDATE admin_role_permissions a JOIN permissions p ON p.id=a.permission_id SET a.allowed=(p.module_key IN ('news','offers') AND p.action_key='view') WHERE a.admin_id='"+actor+"'")
        call('GET','/api/v1/admin/news');call('GET','/api/v1/admin/offers')
        call('PUT','/api/v1/admin/news/'+n['id'],{**news,'version':n['version']},expected=403)
        call('DELETE','/api/v1/admin/offers/'+o['id'],{'version':o['version']},expected=403)
        fixture("UPDATE admin_role_permissions a JOIN permissions p ON p.id=a.permission_id SET a.allowed=(p.module_key='offers' AND p.action_key='create') WHERE a.admin_id='"+actor+"'")
        call('GET','/api/v1/admin/offers/merchants');call('GET','/api/v1/admin/offers',expected=403)
        call('POST','/api/v1/admin/offers',{**offer,'slug':prefix+'-offer-create-only'},expected=201)
        call('PUT','/api/v1/admin/offers/merchants/'+merchant['id'],{'name':'denied','version':merchant['version']},expected=403)
        fixture("UPDATE admin_role_permissions a JOIN permissions p ON p.id=a.permission_id SET a.allowed=(p.module_key='news' AND p.action_key='delete') WHERE a.admin_id='"+actor+"'")
        call('GET','/api/v1/admin/news',expected=403);call('GET','/api/v1/admin/news/'+createonly['id'],expected=403)
        call('DELETE','/api/v1/admin/news/'+createonly['id'],{'version':createonly['version']})
        fixture("UPDATE admin_role_permissions SET allowed=1 WHERE admin_id='"+actor+"'")
        current=call('GET','/api/v1/admin/news/'+n['id'])['record'];a=admin.clone();b=admin.clone()
        def update(client,title):return client.call('PUT','/api/v1/admin/news/'+n['id'],{**news,'title':title,'version':current['version'],'status':'published'})[0]
        with concurrent.futures.ThreadPoolExecutor(2) as pool:codes=list(pool.map(lambda pair:update(*pair),[(a,prefix+'並行A'),(b,prefix+'並行B')]))
        check('concurrent write no silent overwrite',sorted(codes)==[200,409])
        n=call('GET','/api/v1/admin/news/'+n['id'])['record']
        call('DELETE','/api/v1/admin/news/'+n['id'],{'version':1},expected=409)
        call('DELETE','/api/v1/admin/news/'+n['id'],{'version':n['version']})
        call('GET','/api/v1/news/'+n['slug'],expected=404,client=guest);call('GET','/api/v1/news/'+n['slug']+'/neighbors',expected=404,client=guest)
        call('GET','/api/v1/admin/news/'+n['id'],expected=404)
        call('DELETE','/api/v1/admin/offers/'+o['id'],{'version':o['version']})
        call('GET','/api/v1/offers/'+o['slug'],expected=404,client=guest)
        check('normal audit records',len(fixture("SELECT id FROM audit_logs WHERE actor_admin_id='"+actor+"' AND action LIKE 'news-%'"))>0)
        print(json.dumps({'checks':len(checks),'passed':checks},ensure_ascii=False))
    finally:
        fixture("UPDATE admin_role_permissions SET allowed=1 WHERE admin_id='"+actor+"'")
        for table,alias,fk in [('offers','offer_aliases','offer_id'),('news_posts','news_aliases','news_post_id')]:
            fixture(f"DELETE FROM {alias} WHERE {fk} IN (SELECT id FROM {table} WHERE created_by='{actor}')")
            fixture(f"DELETE FROM {table} WHERE created_by='{actor}'")
        fixture("DELETE FROM merchants WHERE name LIKE '"+prefix+"%'")
        publish()

if __name__=='__main__':main()
