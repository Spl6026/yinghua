"""驗證目前行程只能由本人存取，以及整份行程的交易與並行處理。"""
import concurrent.futures,json,secrets,uuid
import member_resources_integration as probe
from api_integration import Client,fixture,password

call=probe.call;check=probe.check;publish=probe.publish
def main():
    members=[];places=[str(uuid.uuid4()) for _ in range(8)];trigger='p6_item_fail_'+secrets.token_hex(4)
    base='/api/v1/me/itineraries/current';item=base+'/items/'
    try:
        for index,target in enumerate(places):
            state='draft' if index==4 else 'published';date="'2099-01-01'" if index==5 else 'UTC_TIMESTAMP()';deleted='UTC_TIMESTAMP()' if index==6 else 'NULL'
            fixture(f"INSERT INTO attractions(id,slug,name,category,summary,description,address,status,publish_at,deleted_at) VALUES('{target}','{probe.prefix}-trip-{index}','旅程公開名稱 {index}','景點','','','','{state}',{date},{deleted})")
        publish()
        a,a_id,email=probe.member('trip-a');members.append(a_id)
        b,b_id,_=probe.member('trip-b');members.append(b_id)
        check('no current trip on initial GET',call(a,'GET',base)[1]['itinerary'] is None and not fixture(f"SELECT id FROM itineraries WHERE member_id='{a_id}'"))
        check('missing csrf refuses trip write',call(a,'PUT',item+places[0],{'version':0},csrf=False)[0]==403)
        generation=a.call('GET','/api/v1/attractions')[1]['generation']
        code,first,_=call(a,'PUT',item+places[0],{'version':0});check('first add creates parent and first item',code==201 and first['version']==1 and first['changed'])
        trip_id=first['itineraryId']
        check('read uses default title and no invented date',call(a,'GET',base)[1]['itinerary']['title']=='我的旅行計畫' and call(a,'GET',base)[1]['itinerary']['tripDate'] is None)
        check('private writes do not publish',a.call('GET','/api/v1/attractions')[1]['generation']==generation)
        check('duplicate stale PUT returns current without extra row',call(a,'PUT',item+places[0],{'version':0})[1]['changed'] is False)
        check('B cannot read A current trip',call(b,'GET',base)[1]['itinerary'] is None)
        check('foreign itinerary id cannot be assigned',call(b,'PUT',item+places[1],{'version':0,'itineraryId':trip_id})[0]==422)
        check('full title editing remains unopened',call(a,'PUT',item+places[1],{'version':1,'title':'unsupported'})[0]==422)
        check('B removal affects no A row',call(b,'DELETE',item+places[0],{'version':0})[1]['itineraryId'] is None)
        check('stale different add rejected',call(a,'PUT',item+places[1],{'version':0})[0]==409)
        code,second,_=call(a,'PUT',item+places[1],{'version':1});check('second add advances version',code==201 and second['version']==2)
        page=call(a,'GET',base+'?pageSize=1')[1];cursor=page['nextCursor'];check('current items cursor exists',bool(cursor))
        from urllib.parse import quote
        check('other member cursor cannot expose trip',call(b,'GET',base+'?cursor='+quote(cursor))[0]==409)
        check('remove with matching version',call(a,'DELETE',item+places[0],{'version':2})[1]['version']==3)
        check('old cursor cannot mix versions',call(a,'GET',base+'?cursor='+quote(cursor))[0]==409)
        for index in [4,5,6]:check('unavailable target '+str(index)+' rejected',call(a,'PUT',item+places[index],{'version':3})[0]==404)
        fixture(f"UPDATE attractions SET status='archived',name='草稿私人新名稱',description='私人正文',version=version+1 WHERE id='{places[1]}'")
        hidden=call(a,'GET',base)[1]['itinerary']['items'][0]
        check('pending snapshot retains saved name only',hidden['snapshotName']=='旅程公開名稱 1' and hidden['availability']=='unknown' and hidden['attraction'] is None)
        check('removal works while publication pending',call(a,'DELETE',item+places[1],{'version':3})[1]['version']==4)
        publish()
        empty=call(a,'GET',base)[1]['itinerary'];check('last removal retains empty parent and version',empty['id']==trip_id and empty['items']==[] and empty['version']==4)
        check('old first-add cannot recreate parent',call(a,'PUT',item+places[0],{'version':0})[0]==409)
        check('adding to retained trip uses same id',call(a,'PUT',item+places[0],{'version':4})[1]['itineraryId']==trip_id)
        fixture(f"UPDATE attractions SET status='archived',deleted_at=UTC_TIMESTAMP(),name='刪除後私人名稱',version=version+1 WHERE id='{places[0]}'");publish()
        deleted=call(a,'GET',base)[1]['itinerary']['items'][0]
        check('deleted item keeps prior public snapshot',deleted['snapshotName']=='旅程公開名稱 0' and deleted['availability']=='unavailable' and deleted['attraction'] is None)
        check('deleted item still removable',call(a,'DELETE',item+places[0],{'version':5})[1]['version']==6)
        fixture(f"CREATE TRIGGER {trigger} BEFORE INSERT ON itinerary_items FOR EACH ROW BEGIN IF NEW.attraction_id='{places[2]}' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='isolated-member-write-failure'; END IF; END")
        try:
            check('faulting first item reports failure',call(b,'PUT',item+places[2],{'version':0})[0]==500)
            check('failed first item rolls back parent',not fixture(f"SELECT id FROM itineraries WHERE member_id='{b_id}'"))
            check('faulting existing parent reports failure',call(a,'PUT',item+places[2],{'version':6})[0]==500)
            check('failed item rolls back parent version',call(a,'GET',base)[1]['itinerary']['version']==6)
        finally:fixture('DROP TRIGGER IF EXISTS '+trigger)
        clients=[b.clone(),b.clone()]
        with concurrent.futures.ThreadPoolExecutor(2) as pool:
            futures=[pool.submit(call,client,'PUT',item+target,{'version':0}) for client,target in zip(clients,places[2:4])]
            results=[future.result() for future in futures];codes=[result[0] for result in results]
        check('parallel first different adds preserve one version '+str([(r[0],r[1].get('code')) for r in results]),sorted(codes)==[201,409] and len(fixture(f"SELECT id FROM itineraries WHERE member_id='{b_id}'"))==1)
        check('parallel first creates only one item',len(call(b,'GET',base)[1]['itinerary']['items'])==1)
        check('retry after rollback can save',call(a,'PUT',item+places[2],{'version':6})[0]==201)
        fixture(password,'root');admin=Client('admin');check('test root login',admin.login('admin')[0]==200)
        check('admin cookie alone cannot read member trip',call(admin,'GET',base)[0]==401)
        check('admin cookie alone cannot read member favorites',call(admin,'GET','/api/v1/me/favorites')[0]==401)
        version=int(fixture(f"SELECT version FROM attractions WHERE id='{places[2]}'")[0]['version'])
        admin.refresh();check('member references do not block normal attraction deletion',admin.call('DELETE','/api/v1/admin/attractions/'+places[2],{'version':version})[0]==200)
        check('deleted referenced attraction remains a private snapshot',call(a,'GET',base)[1]['itinerary']['items'][0]['availability']=='unavailable')
        for round_index in range(3):
            current=call(a,'GET',base)[1]['itinerary']
            for entry in current['items']:
                result=call(a,'DELETE',item+entry['attractionId'],{'version':current['version']})[1];current['version']=result['version']
            clients=[a.clone(),a.clone()]
            with concurrent.futures.ThreadPoolExecutor(2) as pool:
                jobs=[pool.submit(call,client,'PUT',item+target,{'version':current['version']}) for client,target in zip(clients,[places[3],places[7]])]
                codes=[job.result()[0] for job in jobs]
            check('repeat concurrent version round '+str(round_index),sorted(codes)==[201,409] and len(call(a,'GET',base)[1]['itinerary']['items'])==1)
        current=call(a,'GET',base)[1]['itinerary'];existing=current['items'][0]['attractionId']
        adding=next(target for target in [places[3],places[7]] if target!=existing)
        with concurrent.futures.ThreadPoolExecutor(2) as pool:
            removing=pool.submit(call,a.clone(),'DELETE',item+existing,{'version':current['version']})
            inserting=pool.submit(call,a.clone(),'PUT',item+adding,{'version':current['version']})
            removed,added=removing.result(),inserting.result()
        check('same-version add versus remove permits exactly one change',(removed[0],added[0]) in [(200,409),(409,201)])
        after=call(a,'GET',base)[1]['itinerary']
        check('add/remove conflict does not lose another successful change',after['version']==current['version']+1 and {entry['attractionId'] for entry in after['items']}==(set() if removed[0]==200 else {existing,adding}))
    finally:
        fixture('DROP TRIGGER IF EXISTS '+trigger)
        if members:
            ids=','.join("'"+value+"'" for value in members)
            fixture('DELETE i FROM itinerary_items i JOIN itineraries t ON t.id=i.itinerary_id WHERE t.member_id IN ('+ids+')')
            fixture('DELETE FROM itineraries WHERE member_id IN ('+ids+')')
            fixture('DELETE FROM sessions WHERE member_id IN ('+ids+')')
            fixture("UPDATE members SET status='disabled',deleted_at=UTC_TIMESTAMP() WHERE id IN ("+ids+')')
        fixture('DELETE FROM attractions WHERE id IN ('+','.join("'"+value+"'" for value in places)+')');publish()
    print(json.dumps({'itineraryIntegrationChecks':len(probe.checks),'contractResponses':probe.responses,'csrfRecoveries':probe.csrf_recoveries,'fixturesRemoved':True}))
if __name__=='__main__':main()
