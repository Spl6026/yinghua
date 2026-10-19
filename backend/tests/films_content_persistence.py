"""[E-25] 重建隔離 app/web 後，電影、複數場次、海報及同版 HTML 保留；不清 volume。"""
import datetime,json,secrets,subprocess,time
from api_integration import Client,ROOT,fixture,password
from attractions_integration import upload,test_png,raw

def main():
    fixture(password,'root');root=Client('admin');guest=Client();assert root.login('admin')[0]==200
    prefix='film-persist-'+secrets.token_hex(5);film=None;checks=0
    code,result=upload(root,test_png(),extra={'module':'films'});assert code==201;media=result['media']
    local=datetime.datetime.now(datetime.timezone(datetime.timedelta(hours=8)))
    sunday=local.date()-datetime.timedelta(days=local.weekday())+datetime.timedelta(days=6)
    try:
        code,result,_=root.call('POST','/api/v1/admin/films',{'title':'電影重建驗收 '+prefix,'slug':prefix,'posterMediaId':media['id'],'status':'published',
          'screenings':[{'startsAt':f'{sunday}T09:00:00+08:00'},{'startsAt':f'{sunday}T09:30:00+08:00'}]})
        assert code==201,(code,result);film=result['film'];checks+=1
        path=film['url'];image=raw(guest,media['url']);html=raw(guest,path)
        assert image[0]==200 and html[0]==200;checks+=1
        before=guest.call('GET','/api/v1/films/'+prefix)[1]
        subprocess.run(['docker','compose','up','-d','--no-deps','--force-recreate','app-test','web-test'],cwd=ROOT,check=True,capture_output=True)
        for _ in range(60):
            try:
                if guest.call('GET','/api/v1/films/'+prefix)[0]==200:break
            except (OSError,ValueError):pass
            time.sleep(.5)
        else:raise AssertionError('Recreated test containers did not become ready.')
        assert raw(guest,media['url'])[:2]==image[:2];checks+=1
        assert raw(guest,path)[:2]==html[:2];checks+=1
        after=guest.call('GET','/api/v1/films/'+prefix)[1]
        assert after['generation']==before['generation'] and after['film']['screenings']==before['film']['screenings'];checks+=1
        persisted=root.call('GET','/api/v1/admin/films/'+film['id'])[1]['film']
        assert persisted['version']==1 and len(persisted['screenings'])==2;checks+=1
        assert len(fixture("SELECT id FROM film_screenings WHERE film_id='"+film['id']+"'"))==2;checks+=1
    finally:
        if film:
            code,result,_=root.call('GET','/api/v1/admin/films/'+film['id'])
            if code==200:assert root.call('DELETE','/api/v1/admin/films/'+film['id'],{'version':result['film']['version']})[0]==200
    assert raw(guest,path)[0]==404 and raw(guest,media['url'])[0]==404;checks+=1
    report={'filmPersistenceChecks':checks,'recreated':['app-test','web-test'],'sharedDatabaseRestarted':False,'volumesRemoved':False}
    (ROOT/'artifacts/phase4-persistence-results.json').write_text(json.dumps(report,indent=2),encoding='utf-8')
    print(json.dumps(report))

if __name__=='__main__':main()
