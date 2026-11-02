"""以 yinghua_test 實際驗證多份會員行程的 HTTP、交易、本人限制，以及與既有目前行程的相容。"""
import concurrent.futures,json,uuid,urllib.parse
from api_integration import ROOT,Client,fixture
from phase8_browser_fixture import literal,publish

checks=[]
def check(name,condition):
    if not condition:raise AssertionError(name)
    checks.append(name)

def main():
    m=json.loads((ROOT/'artifacts/phase8-browser-fixture.json').read_text(encoding='utf8'));x=json.loads((ROOT/'artifacts/phase11/fixture.json').read_text(encoding='utf8'));assert not m['cleaned'] and not x['cleaned']
    a=Client();b=Client();admin=Client('admin');guest=Client();base='/api/v1/me/itineraries';route_id=None;extra=str(uuid.uuid4());trigger='p11_'+uuid.uuid4().hex[:12]
    check('member A login',a.login(m['prefix']+'@example.test',m['password'])[0]==200)
    check('member B login',b.login(x['emailB'],m['password'])[0]==200)
    check('owned admin login',admin.login(m['prefix'],m['password'])[0]==200)
    def call(method,path,body=None,expected=200,client=a,**kw):
        status,data,_=client.call(method,path,body,**kw);check(method+' '+path+' '+str(expected),status==expected);return data
    def body(record,**kw):return {'title':record['title'],'tripDate':record['tripDate'],'version':record['version'],'items':[{'attractionId':s['attractionId'],'startTime':s['startTime'],'note':s['note']} for s in record['items']],**kw}
    fixture("INSERT INTO attractions (id,slug,name,category,summary,description,address,status,publish_at) VALUES('"+extra+"',"+literal(m['prefix']+'-p11')+",'階段十一第二站','景點','','','','published','2020-01-01')");publish()
    try:
        call('GET',base,expected=401,client=guest)
        call('GET',base,expected=401,client=admin)
        call('POST',base,{},expected=403,csrf=False)
        # 原本的「目前行程」和多份行程是同一份資料，不是另外複製出來的。
        old=call('PUT',base+'/current/items/'+m['placeId'],{'version':0},expected=201)
        original=call('GET',base+'/'+old['itineraryId'])['itinerary']
        check('P0 current retained',original['items'][0]['attractionId']==m['placeId'])
        check('public fields only','memberId' not in original and original['isCurrent'])
        client_id=str(uuid.uuid4())
        t=call('POST',base,{'id':client_id,'title':'','tripDate':None},expected=201)['itinerary']
        check('empty name date and items allowed',t['title']=='' and t['tripDate'] is None and t['items']==[])
        retry=call('POST',base,{'id':client_id,'title':'not overwritten'},expected=200)['itinerary']
        check('create retry does not duplicate or overwrite',retry['id']==t['id'] and retry['title']=='')
        check('current switch version advances beyond old',t['version']>original['version'])
        call('DELETE',base+'/current/items/'+m['placeId'],{'version':original['version']}) # 新的目前行程若沒有這一站，這個刪除不會造成影響
        call('PUT',base+'/current/items/'+extra,{'version':original['version']},expected=409)
        check('old P0 request cannot edit new current',call('GET',base+'/'+t['id'])['itinerary']['items']==[])
        foreign=call('POST',base,{'title':'B private'},expected=201,client=b)['itinerary']
        for method,payload in [('GET',None),('PUT',body(t)),('DELETE',{'version':t['version']})]:call(method,base+'/'+foreign['id'],payload,expected=404)
        call('PUT',base+'/'+foreign['id']+'/current',{'version':foreign['version']},expected=404)
        call('POST',base,{'id':foreign['id']},expected=409)
        call('GET',base+'/'+str(uuid.uuid4()),expected=404)
        call('POST',base,{'title':'x','memberId':x['memberB']},expected=422)
        for change in [{'title':'a'*201},{'tripDate':'2026-02-30'},{'items':[{'attractionId':extra,'startTime':'24:00'}]},{'items':[{'attractionId':extra},{'attractionId':extra}]},{'items':[{'attractionId':str(uuid.uuid4())}]}]:call('PUT',base+'/'+t['id'],body(t,**change),expected=404 if change.get('items') and change['items'][0]['attractionId'] not in [extra,m['placeId']] else 422)
        data=body(t,title='城市旅行',tripDate='2026-11-05',items=[{'attractionId':extra,'startTime':'14:30','note':'先走這站'},{'attractionId':m['placeId'],'startTime':None,'note':''}])
        t=call('PUT',base+'/'+t['id'],data)['itinerary']
        check('ordered metadata roundtrip',t['tripDate']=='2026-11-05' and t['items'][0]['startTime']=='14:30' and t['items'][0]['note']=='先走這站')
        check('sort order unique positive',[s['sortOrder'] for s in t['items']]==[1,2])
        call('PUT',base+'/'+t['id'],data,expected=409)
        # 在更新行程主檔並刪除舊站點之後，故意讓新增站點失敗。
        fixture("CREATE TRIGGER "+trigger+" BEFORE INSERT ON itinerary_items FOR EACH ROW BEGIN IF NEW.itinerary_id='"+t['id']+"' AND NEW.attraction_id='"+extra+"' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='owned phase11 rollback test'; END IF; END")
        try:
            call('PUT',base+'/'+t['id'],body(t,title='must roll back'),expected=500)
        finally:fixture('DROP TRIGGER IF EXISTS '+trigger)
        check('child failure rolls back metadata version and all stations',call('GET',base+'/'+t['id'])['itinerary']==t)
        # 整份行程只鎖主檔這一列，兩個同時儲存的請求只能有一個成功。
        clients=[a.clone(),a.clone()];attempts=[body(t,title='race'+str(i)) for i in range(2)]
        with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:results=list(pool.map(lambda i:clients[i].call('PUT',base+'/'+t['id'],attempts[i])[0],range(2)))
        check('concurrent whole-save one winner',sorted(results)==[200,409])
        t=call('GET',base+'/'+t['id'])['itinerary']
        previous_version=t['version']
        original=call('PUT',base+'/'+original['id']+'/current',{'version':original['version']})['itinerary']
        check('select uses member monotonic version',original['version']>previous_version)
        call('PUT',base+'/current/items/'+extra,{'version':previous_version},expected=409)
        original=call('PUT',base+'/'+original['id'],body(original,title='P0仍保留',items=[{'attractionId':m['placeId'],'startTime':'09:00','note':'原P0站'}]))['itinerary']
        legacy=call('GET',base+'/current')['itinerary'];check('full save visible to P0',legacy['id']==original['id'] and legacy['items'][0]['plannedTime'].startswith('09:00'))
        # 已下架但仍留在行程裡的站點保留原本的私人快照；不能再新加入下架的站點。
        fixture("UPDATE attractions SET status='archived',name='private draft' WHERE id='"+extra+"'");publish()
        t=call('GET',base+'/'+t['id'])['itinerary'];check('unavailable snapshot safe',t['items'][0]['attraction'] is None and t['items'][0]['snapshotName']=='階段十一第二站')
        t=call('PUT',base+'/'+t['id'],body(t,title='可保存其他欄位'))['itinerary']
        call('PUT',base+'/'+original['id'],body(original,items=[{'attractionId':extra}]),expected=404)
        fixture("UPDATE attractions SET status='published',name='階段十一第二站' WHERE id='"+extra+"'");publish()
        route={'title':m['prefix']+'推薦套用','status':'published','stops':[{'attractionId':m['placeId'],'suggestedTime':'10:30','note':'路線備註'},{'attractionId':extra}]}
        r=call('POST','/api/v1/admin/routes',route,expected=201,client=admin)['record'];route_id=r['id']
        pub=call('GET','/api/v1/routes/'+route_id,client=guest)['route'];check('direct public route ordered',[s['attractionId'] for s in pub['stops']]==[m['placeId'],extra])
        t=call('POST',base+'/'+t['id']+'/routes',{'version':t['version'],'routeId':route_id})['itinerary']
        check('route replaces name and stations keeps date',t['title']==route['title'] and t['tripDate']=='2026-11-05' and len(t['items'])==2 and t['items'][0]['startTime']=='10:30' and t['items'][0]['note']=='路線備註')
        call('POST',base+'/'+foreign['id']+'/routes',{'version':foreign['version'],'routeId':route_id},expected=404)
        route={**route,'title':m['prefix']+'更新後路線','stops':[{'attractionId':extra,'suggestedTime':'08:15','note':'更新備註'},route['stops'][0]]}
        r=call('PUT','/api/v1/admin/routes/'+route_id,{**route,'version':r['version']},client=admin)['record']
        t=call('POST',base+'/'+t['id']+'/routes',{'version':t['version'],'routeId':route_id})['itinerary']
        check('updated public route applies current order time and note',t['title']==route['title'] and t['items'][0]['attractionId']==extra and t['items'][0]['startTime']=='08:15' and t['items'][0]['note']=='更新備註')
        fixture("UPDATE attractions SET status='archived' WHERE id='"+extra+"'");publish()
        t=call('POST',base+'/'+t['id']+'/routes',{'version':t['version'],'routeId':route_id})['itinerary'];check('apply skips downed stop',len(t['items'])==1)
        call('PUT','/api/v1/admin/routes/'+route_id,{**route,'status':'draft','version':r['version'],'stops':[route['stops'][1]]},client=admin)
        call('GET','/api/v1/routes/'+route_id,expected=404,client=guest)
        call('POST',base+'/'+t['id']+'/routes',{'version':t['version'],'routeId':route_id},expected=404)
        reauth=Client();check('relogin',reauth.login(m['prefix']+'@example.test',m['password'])[0]==200)
        check('cross-session full roundtrip',call('GET',base+'/'+t['id'],client=reauth)['itinerary']==t)
        listing=call('GET',base+'?pageSize=1');check('owned pagination',listing['total']==2 and len(listing['items'])==1 and all(s['id']!=foreign['id'] for s in listing['items']))
        t=call('PUT',base+'/'+t['id']+'/current',{'version':t['version']})['itinerary']
        call('DELETE',base+'/'+t['id'],{'version':t['version']},expected=204)
        call('GET',base+'/'+t['id'],expected=404)
        check('delete current selects another',call('GET',base+'/current')['itinerary']['id']==original['id'])
        original=call('GET',base+'/'+original['id'])['itinerary'];call('DELETE',base+'/'+original['id'],{'version':original['version']},expected=204)
        check('last delete current empty',call('GET',base+'/current')['itinerary'] is None)
        check('B untouched',call('GET',base+'/'+foreign['id'],client=b)['itinerary']['title']=='B private')
    finally:
        fixture('DROP TRIGGER IF EXISTS '+trigger)
        for mid in [m['memberId'],x['memberB']]:
            fixture("DELETE i FROM itinerary_items i JOIN itineraries t ON t.id=i.itinerary_id WHERE t.member_id='"+mid+"'");fixture("DELETE FROM itineraries WHERE member_id='"+mid+"'")
        if route_id:fixture("DELETE FROM route_attractions WHERE route_id='"+route_id+"'");fixture("DELETE FROM routes WHERE id='"+route_id+"'")
        fixture("DELETE FROM attractions WHERE id='"+extra+"'");publish()
    (ROOT/'artifacts/phase11/integration.json').write_text(json.dumps({'passed':len(checks),'checks':checks},ensure_ascii=False,indent=2),encoding='utf8')
    print(json.dumps({'passed':len(checks)}))

if __name__=='__main__':main()
