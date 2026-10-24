"""[E-29] Own test DB record; API/gate reject stale content with correlated, bounded logs."""
import json,subprocess,uuid
from datetime import datetime,timezone
from api_integration import Client,fixture,ROOT

def publish():
    subprocess.run(['docker','compose','exec','-T','-u','www-data','app-test','php','bin/console.php','content:publish'],cwd=ROOT,capture_output=True,check=True)

def main():
    identifier=str(uuid.uuid4());marker='private-diagnostic-'+uuid.uuid4().hex
    client=Client();checks=0
    try:
        fixture("INSERT INTO attractions(id,slug,name,category,summary,description,address,status,publish_at) VALUES('"+identifier+"','diag-"+identifier+"','"+marker+"','景點','','','','published','2020-01-01')")
        publish()
        assert client.call('GET','/api/v1/home')[0]==200;checks+=1
        since=datetime.now(timezone.utc).strftime('%Y-%m-%dT%H:%M:%SZ')
        fixture("UPDATE attractions SET name='changed-"+marker+"',version=version+1 WHERE id='"+identifier+"'")
        code,body,headers=client.call('GET','/api/v1/home')
        assert code==503 and body['code']=='PUBLICATION_PENDING' and headers.get('Retry-After')=='5';checks+=1
        assert not any(key in body for key in ['sections','expected','published','diagnostic']);checks+=1
        api_request=body['requestId']
        code,body,_=client.call('GET','/api/v1/publication/access');assert code==403;checks+=1
        gate_request=body['requestId']
        logs=subprocess.check_output(['docker','compose','logs','--since',since,'--no-color','app-test'],cwd=ROOT,text=True,encoding='utf8')
        for request in [api_request,gate_request]:
            assert any('request='+request+' publication_reason=projection_changed' in line and 'items' in line for line in logs.splitlines())
            checks+=1
        assert marker not in logs;checks+=1
    finally:
        fixture("DELETE FROM attractions WHERE id='"+identifier+"'")
        publish()
    assert client.call('GET','/api/v1/home')[0]==200;checks+=1
    print(json.dumps({'checks':checks,'apiAndGateRequestIdsCorrelated':True,'noContentInDiagnostics':True,'ownedFixtureRemoved':True}))

if __name__=='__main__':main()
