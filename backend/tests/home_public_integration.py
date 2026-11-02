"""首頁的真實整合測試，以及同一版發布時的故障處理；最後只清理本次產生的 UUID 資料。"""
from pathlib import Path
from datetime import datetime,timedelta,timezone
import argparse,json,re,secrets,sys,uuid
from news_public_integration import ROOT,run,db,get,cli,validate

checks=[];news_ids=[];film_ids=[]
def check(name,value):
    if not value:raise AssertionError(name)
    checks.append(name)

def home():
    code,text,_=get('/api/v1/home');data=json.loads(text)
    spec=json.loads((ROOT/'backend/resources/openapi.json').read_text('utf8'))
    validate(spec['paths']['/api/v1/home']['get']['responses'][str(code)]['content']['application/json']['schema'],data)
    return code,data

def main():
    prefix='p5-home-'+secrets.token_hex(5);now=datetime.now(timezone.utc)
    rows=[]
    try:
        for n in range(5):
            record_id=str(uuid.uuid4());news_ids.append(record_id)
            row={'id':record_id,'title':prefix+' '+str(n),'slug':prefix+'-'+str(n),'type':'event' if n%2 else 'city','status':'published',
                 'publishAt':(now-timedelta(seconds=n+2)).isoformat(timespec='seconds')}
            if n==3:row['status']='draft'
            if n==4:row['publishAt']='2099-01-01T00:00:00+00:00'
            rows.append(row)
        cli(rows,True)
        period=home()[1]['period'];first=datetime.fromisoformat(period['from'])+timedelta(hours=4,minutes=secrets.randbelow(50))
        for n in range(4):
            record_id=str(uuid.uuid4());film_ids.append(record_id)
            db("INSERT INTO films(id,slug,title,status,publish_at) VALUES(:id,:slug,:title,'published','2020-01-01')",{'id':record_id,'slug':prefix+'-film-'+str(n),'title':prefix+' film '+str(n)})
            for day in [n,n+1]:
                db('INSERT INTO film_screenings(id,film_id,starts_at) VALUES(:id,:film,:start)',{'id':str(uuid.uuid4()),'film':record_id,'start':(first+timedelta(days=day)).strftime('%Y-%m-%d %H:%M:%S')})
        run(['docker','compose','exec','-T','--user','www-data','app-test','php','bin/console.php','content:publish'])
        code,data=home();check('home contract readable',code==200)
        news=json.loads(get('/api/v1/news')[1]);films=json.loads(get('/api/v1/films?week=current&pageSize=100')[1]);places=json.loads(get('/api/v1/attractions?pageSize=100')[1])
        check('all public resources share generation',len({data['generation'],news['generation'],films['generation'],places['generation']})==1)
        check('home has exactly latest three safe news',data['news']==news['items'][:3] and {x['id'] for x in data['news']}==set(news_ids[:3]))
        check('home has first three distinct weekly films',data['films']==films['items'][:3] and data['filmTotal']==films['total'] and len({x['id'] for x in data['films']})==3)
        check('home period agrees with film API',data['period']==films['period'])
        museum=next((x for x in places['items'] if x['isMuseum']),None)
        featured=next((x for x in places['items'] if x['featured']),museum)
        check('home uses real museum and featured place',data['museum']==museum and data['featured']==featured)
        code,html,_=get('/')
        ssr=json.loads(re.search(r'id="home-publication">([\s\S]*?)</script>',html).group(1))
        check('initial HTML and API contain identical home data',ssr=={k:v for k,v in data.items() if k!='requestId'})
        check('initial home has real news links without JavaScript',all('href="'+x['url']+'"' in html for x in data['news']))
        check('initial home preserves original event and film sections','class="event-list"' in html and 'class="screening-grid"' in html and 'class="planner-banner"' in html)
        check('no new offer card section added','class="offer-grid"' not in html)
        for item in [*data['news'],*data['films']]:check('home detail link '+item['slug'],get(item['url'])[0]==200)
        result=cli([{**rows[0],'title':prefix+' changed','version':1}],True,4,True)
        check('failed render reports saved state',result['status']=='saved_publication_pending')
        check('API blocks mixed old home generation',home()[0]==503)
        check('HTML blocks mixed old home generation',get('/')[0]==503)
        run(['docker','compose','exec','-T','--user','www-data','app-test','php','bin/console.php','content:publish'])
        _,updated=home();check('retry publishes saved home update',any(x['title']==prefix+' changed' for x in updated['news']) and updated['generation']!=data['generation'])
        cli([{**rows[0],'title':prefix+' changed','version':2,'status':'archived'}],True)
        check('unpublished news disappears from home',all(x['id']!=news_ids[0] for x in home()[1]['news']))
        report={'checks':len(checks),'cases':checks,'fixtureIdsRemoved':True}
        (ROOT/'artifacts/phase5-home-results.json').write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf8')
        print(json.dumps({'checks':len(checks),'fixtureIdsRemoved':True}))
    finally:
        for record_id in news_ids:
            db('DELETE FROM news_aliases WHERE news_post_id=:id',{'id':record_id});db('DELETE FROM news_posts WHERE id=:id',{'id':record_id})
        for record_id in film_ids:
            db('DELETE FROM film_screenings WHERE film_id=:id',{'id':record_id});db('DELETE FROM films WHERE id=:id',{'id':record_id})
        run(['docker','compose','exec','-T','--user','www-data','app-test','php','bin/console.php','content:publish'])

if __name__=='__main__':
    parser=argparse.ArgumentParser();parser.add_argument('--dependency-path');options=parser.parse_args()
    if options.dependency_path:sys.path.insert(0,str(Path(options.dependency_path).resolve()))
    main()
