"""以真實 MariaDB、公開 API 與獨立的 OpenAPI 驗證全站搜尋；只使用測試自有的內容。"""
import argparse,json,sys,uuid,urllib.request,urllib.error,subprocess
from pathlib import Path
from urllib.parse import urlencode
from datetime import datetime,timezone,timedelta
from api_integration import ROOT,Client,fixture
from phase8_browser_fixture import literal,publish

parser=argparse.ArgumentParser();parser.add_argument('--dependency-path',required=True);parser.add_argument('--keep-browser',action='store_true');args=parser.parse_args();sys.path.insert(0,str(Path(args.dependency_path).resolve()))
import jsonschema
checks=[];responses=0
def check(name,condition):assert condition,name;checks.append(name)
m=json.loads((ROOT/'artifacts/phase8-browser-fixture.json').read_text(encoding='utf8'));assert not m['cleaned']
prefix=m['prefix'];admin=Client('admin');guest=Client();assert admin.login(prefix,m['password'])[0]==200
spec=json.loads((ROOT/'backend/resources/openapi.json').read_text(encoding='utf8'));schema=spec['paths']['/api/v1/search']['get']['responses']['200']['content']['application/json']['schema']
validator=jsonschema.Draft202012Validator(spec).evolve(schema=schema)
def search(q=prefix,expected=200,**kw):
    global responses
    code,data,_=guest.call('GET','/api/v1/search?'+urlencode({'q':q,**kw}));responses+=1;check('search status '+str(expected),code==expected)
    if code==200:check('OpenAPI search response',not list(validator.iter_errors(data)))
    else:check('safe error envelope',all(k in data for k in ['code','message','fieldErrors','requestId']))
    return data
def create(path,body):
    code,data,_=admin.call('POST','/api/v1/admin/'+path,body);check('create '+path,code==201);return data['record']
ids=[str(uuid.uuid4()) for _ in range(27)];food=str(uuid.uuid4())
manifest={'prefix':prefix,'newsIds':ids,'foodId':food,'created':[],'cleaned':False}
target=ROOT/'artifacts/phase13';target.mkdir(exist_ok=True);mp=target/'fixture.json';mp.write_text(json.dumps(manifest),encoding='utf8')
keep_success=False
try:
    merchant=create('offers/merchants',{'name':prefix+' 搜尋店家'});manifest['merchantId']=merchant['id'];mp.write_text(json.dumps(manifest),encoding='utf8')
    offer=create('offers',{'title':prefix+' 搜尋優惠','merchantId':merchant['id'],'discount':'搜尋好康','terms':'每人一次','status':'published'})
    manifest['created'].append(['offers',offer['id']]);mp.write_text(json.dumps(manifest),encoding='utf8')
    fixture('INSERT INTO attractions(id,slug,name,category,summary,description,address,status,publish_at) VALUES('+','.join(map(literal,[food,prefix+'-food',prefix+' 搜尋美食','美食','咖啡','咖啡館','新竹','published','2020-01-01']))+')')
    values=[]
    for i,identifier in enumerate(ids):
        status='draft' if i==25 else 'published';date='2050-01-01' if i==26 else '2020-01-01'
        title=prefix+(' %_ <img src=x onerror=alert(1)>' if i==0 else ' 搜尋消息 '+str(i))
        values.append('('+','.join(map(literal,[identifier,prefix+'-search-'+str(i),'city',title,'竹塹全文摘要','<p>公開正文關鍵字</p>',status,date,m['adminId'],m['adminId']]))+')')
    fixture('INSERT INTO news_posts(id,slug,type,title,summary,body,status,publish_at,created_by,updated_by) VALUES '+','.join(values));publish()
    all_items=search(pageSize=100);items=all_items['items'];types=[r['type'] for r in items]
    check('five public groups present',set(types)=={'place','food','film','news','offer'})
    order={'place':0,'food':1,'film':2,'news':3,'offer':4};check('group order stable',types==sorted(types,key=order.get))
    check('draft and future excluded',all(r['id'] not in ids[25:] for r in items))
    check('no account or secret fields',all(set(r)=={'id','type','title','summary','slug','url','thumbnail','thumbnailAlt'} for r in items))
    page1=search(pageSize=7);page2=search(page=2,pageSize=7);check('disjoint stable pages',[r['id'] for r in page1['items']+page2['items']]==[r['id'] for r in items[:14]])
    check('same generation',page1['generation']==page2['generation']==all_items['generation'])
    for kind in order:check(kind+' filter',all(r['type']==kind for r in search(type=kind)['items']))
    check('empty query no bulk disclosure',search('')['total']==0)
    check('beyond last page empty',search(page=999)['items']==[])
    check('Chinese one-character search',search('竹')['total']>=25)
    check('literal wildcard search',search(prefix+' %_')['total']==1)
    check('SQL injection literal',search("' OR 1=1 --")['total']==0)
    check('HTML query is literal',any(row['id']==ids[0] for row in search('<img src=x onerror=alert(1)>')['items']))
    check('body text searchable',search('公開正文關鍵字')['total']==25)
    for params in [{'type':'member'},{'page':0},{'pageSize':101}]:search(expected=422,**params)
    invalid=search('竹'*256,expected=422);check('length error explains the actual limit','255' in invalid['fieldErrors']['q']);search('竹'*255)
    check('blank has no prototype data',search('not-present-'+prefix)['items']==[])
    for row in items:
        req=urllib.request.Request('http://localhost:8081'+row['url']);http=urllib.request.build_opener(urllib.request.ProxyHandler({}));response=http.open(req,timeout=20);check('direct public detail '+row['type'],response.status==200)
    # 下架的項目在重新發布後必須從搜尋結果消失，不能殘留舊資料。
    fixture('UPDATE news_posts SET status='+literal('archived')+' WHERE id='+literal(ids[1]));publish();check('downed excluded',all(r['id']!=ids[1] for r in search(pageSize=100)['items']))
    fixture('UPDATE offers SET valid_to='+literal('2020-01-01')+' WHERE id='+literal(offer['id']));publish();check('expired offer excluded',search(type='offer')['total']==0)
    fixture('UPDATE offers SET valid_to=NULL WHERE id='+literal(offer['id']));publish()
    oldslug=prefix+'-search-0';newslug=prefix+'-search-renamed'
    code,record,_=admin.call('GET','/api/v1/admin/news/'+ids[0]);body={k:record['record'][k] for k in ['title','type','summary','body','status','version']};body['slug']=newslug
    code,_,_=admin.call('PUT','/api/v1/admin/news/'+ids[0],body);check('rename saved',code==200);check('search real renamed URL',search(prefix+' %_')['items'][0]['url']=='/news/'+newslug+'.html')
    fixture('UPDATE news_posts SET slug='+literal('bad/slug')+' WHERE id='+literal(ids[0]))
    try:
        # 直接改資料庫不經發布時，網站繼續提供上一版；發布遇到格式錯誤的網址必須失敗，錯誤網址不能上線。
        check('direct database change keeps last published version',all('bad/slug' not in r['url'] for r in search(pageSize=100)['items']))
        from phase8_browser_fixture import php
        result=php('require "vendor/autoload.php";$p=(new Yinghua\\Composition(new Yinghua\\Config()))->publication();echo $p->retry()?"ready":"failed";').decode();check('failed publish rejected',result=='failed')
        check('failed publish keeps last good version',all('bad/slug' not in r['url'] for r in search(pageSize=100)['items']))
    finally:fixture('UPDATE news_posts SET slug='+literal(newslug)+' WHERE id='+literal(ids[0]));publish()
    search();check('old index generations pruned',int(fixture('SELECT COUNT(DISTINCT generation) AS n FROM search_documents')[0]['n'])==1)
    search()
    subprocess.run([sys.executable,str(ROOT/'backend/tests/phase13_profile.py')],cwd=ROOT,check=True,capture_output=True)
    manifest['newsPath']='/news/'+newslug+'.html';mp.write_text(json.dumps(manifest),encoding='utf8')
    report={'checks':len(checks),'contractResponses':responses,'contractErrors':0,'passed':checks,'fixtureKeptForBrowser':args.keep_browser};(target/'integration.json').write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf8');print(json.dumps({k:v for k,v in report.items() if k!='passed'}))
    keep_success=args.keep_browser
finally:
    if not keep_success:
        subprocess.run([sys.executable,str(ROOT/'backend/tests/phase13_cleanup.py'),'--content-only'],cwd=ROOT,check=True)
