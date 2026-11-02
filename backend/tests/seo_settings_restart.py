"""重建隔離的應用程式後，SEO 設定的修訂版本與登入狀態都要保留。"""
import json,subprocess,time
from api_integration import Client,ROOT,fixture,password
from seo_settings_integration import payload,restore,publish

def main():
    fixture(password,'root');client=Client('admin');assert client.login('admin')[0]==200
    base='/api/v1/admin/settings/seo';stored=fixture('SELECT * FROM site_settings');overrides=fixture('SELECT * FROM content_seo');checks=[]
    def check(name,ok):
        if not ok:raise AssertionError(name)
        checks.append(name)
    try:
        doc=payload(client.call('GET',base)[1]);doc['defaults']['siteName']='容器重建驗收'
        client.refresh();code,saved,_=client.call('PUT',base,doc);check('saved version before recreation',code==200 and saved['publicationReady'])
        expected=payload(saved)
        run=subprocess.run(['docker','compose','up','-d','--no-deps','--force-recreate','app-test','web-test'],cwd=ROOT,capture_output=True,text=True,encoding='utf8')
        check('only isolated application services recreated',run.returncode==0)
        for _ in range(60):
            try:
                code,current,_=client.call('GET',base)
                if code==200:break
            except OSError:pass
            time.sleep(.5)
        else:raise AssertionError('Isolated application failed to recover.')
        check('existing administrator session and settings retained',payload(current)==expected)
        code,public,_=Client().call('GET','/api/v1/site-settings/public')
        check('public snapshot revision retained',code==200 and public['version']==saved['version'] and public['siteName']=='容器重建驗收')
    finally:
        restore('content_seo',overrides);restore('site_settings',stored);publish()
    report={'checks':len(checks),'sessionRetained':True,'settingsRetained':True,'databaseRestarted':False,'volumesRemoved':False,'snapshotRestored':True}
    (ROOT/'artifacts/phase7-restart-results.json').write_text(json.dumps(report,indent=2),encoding='utf8');print(json.dumps(report))

if __name__=='__main__':main()
