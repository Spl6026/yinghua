"""只重建隔離的 HTTP 服務，確認會員資料與登入狀態都會保留。"""
import json,subprocess,time,uuid
import member_resources_integration as probe
from api_integration import ROOT,fixture

def main():
    member_id=None;place=str(uuid.uuid4());base='/api/v1/me/itineraries/current'
    try:
        fixture(f"INSERT INTO attractions(id,slug,name,category,summary,description,address,status,publish_at) VALUES('{place}','{probe.prefix}-restart','重建保存地點','景點','','','','published',UTC_TIMESTAMP())")
        probe.publish();client,member_id,_=probe.member('restart')
        probe.check('favorite created',probe.call(client,'PUT','/api/v1/me/favorites/'+place)[0]==201)
        probe.check('trip created',probe.call(client,'PUT',base+'/items/'+place,{'version':0})[0]==201)
        # 每次 HTTP 的 requestId 原本就不同，只比較真正保存的業務資料。
        def saved_data(path):
            code,payload,_=probe.call(client,'GET',path)
            probe.check('private read succeeds '+path,code==200)
            return {key:value for key,value in payload.items() if key!='requestId'}
        before={path:saved_data(path) for path in ['/api/v1/me/favorites',base]}
        result=subprocess.run(['docker','compose','up','-d','--no-deps','--force-recreate','app-test','web-test'],cwd=ROOT,capture_output=True,text=True,encoding='utf8')
        if result.returncode:raise AssertionError('Isolated service recreation failed: '+result.stderr)
        for _ in range(60):
            try:
                if client.call('GET',client.auth+'me')[0]==200:break
            except OSError:pass
            time.sleep(.5)
        else:raise AssertionError('Member session did not become available after recreation.')
        for path,data in before.items():probe.check('same private data after recreation '+path,saved_data(path)==data)
        probe.check('existing session can remove saved item',probe.call(client,'DELETE',base+'/items/'+place,{'version':1})[1]['version']==2)
        report={'checks':len(probe.checks),'contractResponses':probe.responses,'sessionRetained':True,'recreated':['app-test','web-test'],'databaseRestarted':False,'volumesRemoved':False,'fixturesRemoved':True}
    finally:
        if member_id:
            fixture(f"DELETE i FROM itinerary_items i JOIN itineraries t ON t.id=i.itinerary_id WHERE t.member_id='{member_id}'")
            fixture(f"DELETE FROM itineraries WHERE member_id='{member_id}'")
            fixture(f"DELETE FROM favorites WHERE member_id='{member_id}'")
            fixture(f"DELETE FROM sessions WHERE member_id='{member_id}'")
            fixture(f"UPDATE members SET status='disabled',deleted_at=UTC_TIMESTAMP() WHERE id='{member_id}'")
        fixture(f"DELETE FROM attractions WHERE id='{place}'");probe.publish()
    (ROOT/'artifacts/phase6-restart-results.json').write_text(json.dumps(report,indent=2),encoding='utf8')
    print(json.dumps(report))

if __name__=='__main__':main()
