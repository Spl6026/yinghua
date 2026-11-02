"""建立隔離瀏覽器檢查用的深層頁面、長文字與缺圖測試資料；清理時只處理本次的 manifest。"""
from pathlib import Path
from datetime import datetime,timedelta,timezone
import argparse,json,re,secrets,sys,uuid
from news_public_integration import ROOT,cli as news_cli,db,run
from offers_public_integration import cli as offer_cli

manifest_path=ROOT/'artifacts/phase5-browser-fixtures.json'
def main(cleanup):
    if cleanup:
        data=json.loads(manifest_path.read_text('utf8'));prefix=data['prefix']
        assert re.fullmatch(r'p5-browser-[a-f0-9]{10}',prefix)
        for table,alias,column,key in [('news_posts','news_aliases','news_post_id','newsIds'),('offers','offer_aliases','offer_id','offerIds')]:
            for record_id in data[key]:
                assert re.fullmatch(r'[a-f0-9-]{36}',record_id)
                row=db('SELECT slug FROM '+table+' WHERE id=:id',{'id':record_id})
                assert not row or row[0]['slug'].startswith(prefix+'-')
                db('DELETE FROM '+alias+' WHERE '+column+'=:id',{'id':record_id});db('DELETE FROM '+table+' WHERE id=:id',{'id':record_id})
        db('DELETE FROM merchants WHERE id=:id AND name=:name',{'id':data['merchantId'],'name':prefix})
        run(['docker','compose','exec','-T','--user','www-data','app-test','php','bin/console.php','content:publish'])
        data['cleaned']=True;manifest_path.write_text(json.dumps(data,ensure_ascii=False,indent=2),encoding='utf8');print('Browser fixtures removed.');return
    assert not manifest_path.exists() or json.loads(manifest_path.read_text('utf8')).get('cleaned'), 'Clean existing browser fixtures first.'
    prefix='p5-browser-'+secrets.token_hex(5);merchant=str(uuid.uuid4());news=[];offers=[];now=datetime.now(timezone.utc)
    for n in range(21):
        common={'id':str(uuid.uuid4()),'slug':prefix+'-news-'+str(n),'title':('A'*210 if n==0 else '畫面驗收消息 '+str(n)),'status':'published','publishAt':(now-timedelta(seconds=n+2)).isoformat(timespec='seconds')}
        news.append({**common,'type':'city' if n%2 else 'event','summary':'長文字與分頁驗收；不是正式內容。','coverUrl':'http://localhost:8081/assets/phase5-missing.png','coverAlt':'找不到的測試圖片'})
        offers.append({'id':str(uuid.uuid4()),'slug':prefix+'-offer-'+str(n),'title':('B'*210 if n==0 else '畫面驗收優惠 '+str(n)),'merchantId':merchant,'discount':'九折','terms':'限本機驗收使用','status':'published'})
    data={'prefix':prefix,'merchantId':merchant,'newsIds':[x['id'] for x in news],'offerIds':[x['id'] for x in offers],'cleaned':False}
    manifest_path.write_text(json.dumps(data,ensure_ascii=False,indent=2),encoding='utf8')
    offer_cli('merchants',[{'id':merchant,'name':prefix}],True);news_cli(news,True);offer_cli('offers',offers,True)
    print(json.dumps({'fixtureNews':21,'fixtureOffers':21,'prefix':prefix}))

if __name__=='__main__':
    parser=argparse.ArgumentParser();parser.add_argument('--dependency-path');parser.add_argument('--cleanup',action='store_true');options=parser.parse_args()
    if options.dependency_path:sys.path.insert(0,str(Path(options.dependency_path).resolve()))
    main(options.cleanup)
