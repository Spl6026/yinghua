"""使用測試資料庫自有的紀錄：直接改資料庫（不經後台儲存）時，網站繼續提供上一版，
發布程序的 tick 比對後才換成新內容；過程中不回 503，也不在回應中露出內部欄位。"""
import json,subprocess,uuid
from api_integration import Client,fixture,ROOT

def console(command):
    subprocess.run(['docker','compose','exec','-T','-u','www-data','app-test','php','bin/console.php',command],cwd=ROOT,capture_output=True,check=True)

def names(client):
    code,body,_=client.call('GET','/api/v1/attractions?pageSize=100')
    assert code==200 and not any(key in body for key in ['sections','expected','published','diagnostic'])
    return [item['name'] for item in body['items']]

def main():
    identifier=str(uuid.uuid4());marker='freshness-'+uuid.uuid4().hex
    client=Client();checks=0
    try:
        fixture("INSERT INTO attractions(id,slug,name,category,summary,description,address,status,publish_at) VALUES('"+identifier+"','diag-"+identifier+"','"+marker+"','景點','','','','published','2020-01-01')")
        console('content:publish')
        assert marker in names(client);checks+=1
        fixture("UPDATE attractions SET name='changed-"+marker+"',version=version+1 WHERE id='"+identifier+"'")
        assert marker in names(client) and 'changed-'+marker not in names(client);checks+=1
        assert client.call('GET','/api/v1/publication/access')[0]==204;checks+=1
        console('content:tick')
        assert 'changed-'+marker in names(client);checks+=1
    finally:
        fixture("DELETE FROM attractions WHERE id='"+identifier+"'")
        console('content:publish')
    assert all(marker not in name for name in names(client));checks+=1
    print(json.dumps({'checks':checks,'servesLastVersionUntilTick':True,'tickPicksUpDirectChange':True,'ownedFixtureRemoved':True}))

if __name__=='__main__':main()
