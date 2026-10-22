"""[S：功能規格 9；E-26] 受控 CLI→MariaDB→HTML/API；只清理本次 UUID fixture。"""
from pathlib import Path
import argparse,base64,json,secrets,subprocess,urllib.request,urllib.error,uuid,sys

ROOT=Path(__file__).resolve().parents[2]
BASE='http://127.0.0.1:8081'
prefix='p5-news-'+secrets.token_hex(5)
checks=[];ids=[]
contract_checks=0

def validate(schema,payload):
    global contract_checks
    import jsonschema
    spec=json.loads((ROOT/'backend/resources/openapi.json').read_text(encoding='utf8'))
    jsonschema.Draft202012Validator({**schema,'components':spec['components']}).validate(payload)
    contract_checks+=1

def run(args,source=None,allowed=(0,)):
    result=subprocess.run(args,cwd=ROOT,input=source,text=True,encoding='utf-8',capture_output=True)
    if result.returncode not in allowed:raise AssertionError('Command failed: '+result.stderr+' '+result.stdout[:500])
    return result

def db(sql,params=None):
    encoded=base64.b64encode(json.dumps([sql,params or {}]).encode()).decode()
    source="<?php require 'vendor/autoload.php'; $db=Yinghua\\Repositories\\Database::connect(new Yinghua\\Config()); if(($db->row('SELECT DATABASE() AS name')['name']??'')!=='yinghua_test')throw new RuntimeException('Isolated database required.'); [$sql,$params]=json_decode(base64_decode('"+encoded+"'),true,64,JSON_THROW_ON_ERROR); echo json_encode($db->execute($sql,$params)->fetchAll(),JSON_THROW_ON_ERROR);"
    return json.loads(run(['docker','compose','exec','-T','app-test','php'],source).stdout)

def check(name,value):
    if not value:raise AssertionError(name)
    checks.append(name)

def cli(items,apply=False,expected=0,broken_renderer=False):
    path=ROOT/'backend/.build'/(prefix+'.json');path.parent.mkdir(exist_ok=True)
    envelope={'schemaVersion':1,'items':items}
    if expected==0:
        import jsonschema
        jsonschema.Draft202012Validator(json.loads((ROOT/'backend/resources/content-import.schema.json').read_text(encoding='utf8'))).validate(envelope)
    path.write_text(json.dumps(envelope,ensure_ascii=False),encoding='utf-8')
    run(['docker','compose','cp',str(path),'app-test:/tmp/'+prefix+'.json'])
    args=['docker','compose','exec','-T']
    if broken_renderer:args+=['-e','FRONTEND_SOURCE_PATH=/tmp/phase5-deliberately-missing']
    args+=['--user','www-data','app-test','php','bin/console.php','content:import','--module=news','--file=/tmp/'+prefix+'.json']
    if apply:args.append('--apply')
    result=run(args,allowed=(expected,))
    return json.loads(result.stdout)

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*args,**kwargs):return None
opener=urllib.request.build_opener(urllib.request.ProxyHandler({}),NoRedirect())

def get(path):
    request=urllib.request.Request(BASE+path,headers={'Host':'localhost:8081'})
    try:r=opener.open(request,timeout=20)
    except urllib.error.HTTPError as e:r=e
    with r:
        text=r.read().decode('utf8')
        if path.startswith('/api/v1/news') and r.headers.get('Content-Type','').startswith('application/json'):
            spec=json.loads((ROOT/'backend/resources/openapi.json').read_text(encoding='utf8'))
            route=path.split('?')[0]
            if route not in spec['paths']:route='/api/v1/news/{slug}'
            schema=spec['paths'][route]['get']['responses'].get(str(r.status),{}).get('content',{}).get('application/json',{}).get('schema')
            if schema:validate(schema,json.loads(text))
        return r.status,text,dict(r.headers)

def main():
    items=[]
    for n in range(23):
        record={'id':str(uuid.uuid4()),'title':prefix+' '+str(n),'slug':prefix+'-'+str(n),'type':'event' if n<21 else 'city',
                'status':'published','publishAt':'2020-01-01T00:00:00+00:00','summary':'隔離摘要','body':'<p><strong>正文</strong><script>alert(1)</script></p>'}
        ids.append(record['id']);items.append(record)
    try:
        preview=cli(items)
        check('dry-run reports all creates',len(preview['items'])==23 and preview['status']=='dry_run')
        check('dry-run writes no records',not db('SELECT id FROM news_posts WHERE id=:id',{'id':ids[0]}))
        check('sanitization is reported','body_sanitized' in preview['items'][0]['warnings'])
        result=cli(items,True)
        check('apply publishes all records',result['status']=='saved' and all(x['version']==1 for x in result['items']))
        check('original input is safely rerunnable',cli(items,True)['status']=='unchanged')
        code,text,_=get('/api/v1/news?q='+prefix+'&pageSize=100')
        data=json.loads(text)
        check('API reads all imported articles',code==200 and data['total']==23)
        check('API exposes no author or raw fields',all('created_by' not in x and 'coverMediaId' not in x for x in data['items']))
        check('rich text is safe in API',all('<script' not in x['body'] and '<strong>正文</strong>' in x['body'] for x in data['items']))
        check('classification is isolated',json.loads(get('/api/v1/news?type=city&q='+prefix)[1])['total']==2)
        check('offer category rejected',get('/api/v1/news?type=offers')[0]==422)
        check('invalid page rejected',get('/api/v1/news?page=0')[0]==422)
        api2=json.loads(get('/api/v1/news?page=2')[1])
        code,html,_=get('/events.html?page=2')
        check('deep page directly renders correct items',code==200 and all(x['title'] in html for x in api2['items']))
        check('canonical preserves deep page','href="http://localhost:8081/events.html?page=2"' in html)
        check('private render files cannot bypass gate',get('/_content_pages/events/all/1.html')[0]==404)
        for path in ['/activities.html','/city-news.html','/events.html?type=city']:
            code,html,_=get(path);check('real page '+path,code==200 and 'public-news-state' in html)
        item=items[0];code,html,_=get('/news/'+item['slug']+'.html')
        check('basic detail has real safe body',code==200 and '<strong>正文</strong>' in html and '<script>alert(1)' not in html)
        check('detail API exposes same article',json.loads(get('/api/v1/news/'+item['slug'])[1])['article']['id']==item['id'])
        check('missing detail is explicit 404',get('/api/v1/news/'+prefix+'-missing')[0]==404)
        check('detail does not invent neighbors','article-neighbor-grid' not in html)
        changed={**item,'title':prefix+' renamed','slug':prefix+'-renamed','version':1}
        result=cli([changed],True)
        check('versioned update works',result['items'][0]['version']==2)
        code,_,headers=get('/news/'+item['slug']+'.html')
        check('old slug redirects to canonical',code==301 and headers.get('Location')=='/news/'+changed['slug']+'.html')
        check('stale changed import conflicts',cli([{**changed,'title':'stale'}],True,3)['status']=='conflict')
        check('resolver API returns the same canonical',get('/api/v1/news/resolve-url?path=/news/'+item['slug']+'.html')[0]==301)
        hidden=[]
        for state,stamp in [('draft','2020-01-01T00:00:00+00:00'),('published','2099-01-01T00:00:00+00:00')]:
            hidden_id=str(uuid.uuid4());ids.append(hidden_id)
            hidden.append({**item,'id':hidden_id,'slug':prefix+'-'+str(len(ids)),'status':state,'publishAt':stamp})
        cli(hidden,True)
        for row in hidden:
            check('unpublished detail stays hidden '+row['status'],get('/news/'+row['slug']+'.html')[0]==404)
            check('unpublished resolver stays hidden '+row['status'],get('/api/v1/news/resolve-url?id='+row['id'])[0]==404)
        bad={**items[1],'title':'should not save','version':1}
        invalid={**items[2],'type':'offers','version':1}
        check('whole batch rejects invalid row',cli([bad,invalid],True,2)['status']=='validation_failed')
        check('invalid batch changed no first row',db('SELECT title,version FROM news_posts WHERE id=:id',{'id':ids[1]})[0]=={'title':items[1]['title'],'version':1})
        blank={**items[3],'version':1,'body':'<img src="https://example.test/image.jpg">'}
        check('body image alt required',cli([blank],False,2)['status']=='validation_failed')
        check('blank summary body and dates accepted',cli([{**items[4],'version':1,'summary':'','body':''}])['status']=='dry_run')
        pending={**items[5],'title':prefix+' pending','version':1}
        check('saved but failed HTML returns distinct exit 4',cli([pending],True,4,True)['status']=='saved_publication_pending')
        check('committed content survives renderer failure',db('SELECT title,version FROM news_posts WHERE id=:id',{'id':pending['id']})[0]=={'title':pending['title'],'version':2})
        check('pending publication cannot serve stale list',get('/api/v1/news')[0]==503)
        run(['docker','compose','exec','-T','--user','www-data','app-test','php','bin/console.php','content:publish'])
        check('retry publishes without another update',json.loads(get('/api/v1/news/'+pending['slug'])[1])['article']['title']==pending['title'])
        cli([{**changed,'version':2,'status':'archived'}],True)
        check('archived canonical stays hidden',get('/news/'+changed['slug']+'.html')[0]==404)
        check('archived alias stays hidden',get('/news/'+item['slug']+'.html')[0]==404)
        check('publication remains readable',get('/api/v1/news')[0]==200)
        report={'checks':len(checks),'contractResponses':contract_checks,'cases':checks,'fixtureIdsRemoved':True}
        (ROOT/'artifacts/phase5-news-results.json').write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf8')
        print(json.dumps({'checks':len(checks),'contractResponses':contract_checks,'fixtureIdsRemoved':True}))
    finally:
        for record_id in ids:
            db('DELETE FROM news_aliases WHERE news_post_id=:id',{'id':record_id})
            db('DELETE FROM news_posts WHERE id=:id',{'id':record_id})
        run(['docker','compose','exec','-T','--user','www-data','app-test','php','bin/console.php','content:publish'])

if __name__=='__main__':
    parser=argparse.ArgumentParser();parser.add_argument('--dependency-path');options=parser.parse_args()
    if options.dependency_path:sys.path.insert(0,str(Path(options.dependency_path).resolve()))
    main()
