"""只重建 app-test／web-test，保留資料庫、媒體與發布的 volume；清理測試自有的 UUID 資料。"""
from pathlib import Path
import argparse,hashlib,json,secrets,sys,time,urllib.request,urllib.error,uuid
from news_public_integration import ROOT,run,db,cli as news_cli,get,opener
from offers_public_integration import cli as offer_cli
from attractions_integration import test_png

def read(path):
    try:r=opener.open(urllib.request.Request('http://127.0.0.1:8081'+path,headers={'Host':'localhost:8081'}),timeout=15)
    except urllib.error.HTTPError as error:r=error
    with r:return r.status,hashlib.sha256(r.read()).hexdigest()

def main():
    prefix='p5-restart-'+secrets.token_hex(5);assets=[];merchant=str(uuid.uuid4());news=str(uuid.uuid4());offer=str(uuid.uuid4());checks=[]
    def check(name,value):
        if not value:raise AssertionError(name)
        checks.append(name)
    try:
        path=ROOT/'backend/.build'/(prefix+'.png');path.write_bytes(test_png())
        run(['docker','compose','cp',str(path),'app-test:/tmp/'+prefix+'.png'])
        for module in ['news','offers']:
            result=run(['docker','compose','exec','-T','--user','www-data','app-test','php','bin/console.php','media:import','--module='+module,'--file=/tmp/'+prefix+'.png','--alt=重建驗收圖片'])
            assets.append(json.loads(result.stdout)['media'])
        news_cli([{'id':news,'title':prefix,'type':'city','slug':prefix,'status':'published','body':'<p>保存的正文</p><img src="'+assets[0]['url']+'" alt="正文圖片">'}],True)
        offer_cli('merchants',[{'id':merchant,'name':prefix}],True)
        offer_cli('offers',[{'id':offer,'title':prefix,'slug':prefix,'status':'published','merchantId':merchant,'discount':'九折','terms':'保存的條款','coverMediaId':assets[1]['id']}],True)
        paths=['/news/'+prefix+'.html','/offers/'+prefix+'.html',assets[0]['url'],assets[1]['url']]
        before={path:read(path) for path in paths};check('news and offers HTML and image paths are public',all(value[0]==200 for value in before.values()))
        generation=json.loads(get('/api/v1/home')[1])['generation']
        run(['docker','compose','up','-d','--no-deps','--force-recreate','app-test','web-test'])
        for _ in range(60):
            try:
                if get('/api/v1/home')[0]==200:break
            except (OSError,ValueError):pass
            time.sleep(.5)
        else:raise AssertionError('Recreated test services did not become ready.')
        for path in paths:check('same bytes after restart '+path,read(path)==before[path])
        check('same publication generation retained',json.loads(get('/api/v1/home')[1])['generation']==generation)
        check('news body and media retained',json.loads(get('/api/v1/news/'+prefix)[1])['article']['bodyImages']==[assets[0]['url']])
        check('offer merchant and terms retained',json.loads(get('/api/v1/offers/'+prefix)[1])['offer']['terms']=='保存的條款')
        check('main app remains available',run(['docker','compose','exec','-T','web','wget','-q','-O','/dev/null','--header=Host:localhost:8080','http://127.0.0.1/']).returncode==0)
        report={'checks':len(checks),'cases':checks,'recreated':['app-test','web-test'],'sharedDatabaseRestarted':False,'volumesRemoved':False,'fixturesRemoved':True}
        (ROOT/'artifacts/phase5-restart-results.json').write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf8')
        print(json.dumps({key:value for key,value in report.items() if key!='cases'}))
    finally:
        db('DELETE FROM news_aliases WHERE news_post_id=:id',{'id':news});db('DELETE FROM news_posts WHERE id=:id',{'id':news})
        db('DELETE FROM offer_aliases WHERE offer_id=:id',{'id':offer});db('DELETE FROM offers WHERE id=:id',{'id':offer});db('DELETE FROM merchants WHERE id=:id',{'id':merchant})
        for asset in assets:
            db('DELETE FROM media_assets WHERE id=:id',{'id':asset['id']})
            run(['docker','compose','exec','-T','app-test','php','-r',"$key="+json.dumps(asset['url'].split('/')[-1])+";if(!preg_match('/^[a-f0-9-]{36}\\.(jpg|png|webp)$/D',$key))exit(1);@unlink('/var/www/media/'.$key);"])
        run(['docker','compose','exec','-T','--user','www-data','app-test','php','bin/console.php','content:publish'])

if __name__=='__main__':
    parser=argparse.ArgumentParser();parser.add_argument('--dependency-path');options=parser.parse_args()
    if options.dependency_path:sys.path.insert(0,str(Path(options.dependency_path).resolve()))
    main()
