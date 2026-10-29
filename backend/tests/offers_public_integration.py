"""[S：功能規格 11／G23；E-26] 店家、優惠期限、媒體與失敗流程；只清理本次 UUID。"""
from pathlib import Path
from datetime import datetime,timedelta,timezone
import argparse,json,secrets,sys,uuid
from news_public_integration import ROOT,run,db,get,validate,opener
from attractions_integration import test_png

prefix='p5-offers-'+secrets.token_hex(5)
checks=[];ids=[];media=[];contracts=0

def check(name,value):
    if not value:raise AssertionError(name)
    checks.append(name)

def asset_status(path):
    import urllib.request,urllib.error
    try:response=opener.open(urllib.request.Request('http://127.0.0.1:8081'+path,headers={'Host':'localhost:8081'}),timeout=20)
    except urllib.error.HTTPError as error:response=error
    with response:
        response.read()
        return response.status

def cli(module,items,apply=False,expected=0):
    import jsonschema
    envelope={'schemaVersion':1,'items':items}
    if expected==0:jsonschema.Draft202012Validator(json.loads((ROOT/'backend/resources/content-import.schema.json').read_text(encoding='utf8'))).validate(envelope)
    path=ROOT/'backend/.build'/(prefix+'.json');path.parent.mkdir(exist_ok=True);path.write_text(json.dumps(envelope,ensure_ascii=False),encoding='utf8')
    run(['docker','compose','cp',str(path),'app-test:/tmp/'+prefix+'.json'])
    args=['docker','compose','exec','-T','--user','www-data','app-test','php','bin/console.php','content:import','--module='+module,'--file=/tmp/'+prefix+'.json']
    if apply:args.append('--apply')
    return json.loads(run(args,allowed=(expected,)).stdout)

def api(path,expected=200):
    global contracts
    code,text,headers=get(path);data=json.loads(text)
    spec=json.loads((ROOT/'backend/resources/openapi.json').read_text(encoding='utf8'))
    route=path.split('?')[0]
    if route not in spec['paths']:route='/api/v1/offers/{slug}'
    schema=spec['paths'][route]['get']['responses'].get(str(code),{}).get('content',{}).get('application/json',{}).get('schema')
    if schema:validate(schema,data);contracts+=1
    # [E-29] 首次非預期回應保留安全診斷，不讓缺少total變成無法追查的KeyError；不自動重試。
    if code!=expected:
        failure={'route':route,'expected':expected,'status':code,'code':data.get('code'),'requestId':data.get('requestId')}
        (ROOT/'artifacts/phase5-offers-http-failure.json').write_text(json.dumps(failure,indent=2),encoding='utf8')
        raise AssertionError(json.dumps(failure))
    return code,data

def main():
    today=datetime.now(timezone(timedelta(hours=8))).date()
    merchant_id=str(uuid.uuid4());merchant={'id':merchant_id,'name':prefix+' store','district':'北區'}
    items=[]
    for n in range(26):
        row={'id':str(uuid.uuid4()),'title':prefix+' '+str(n),'slug':prefix+'-'+str(n),'merchantId':merchant_id,'discount':'九折','terms':'每人一次','status':'published','publishAt':'2020-01-01T00:00:00+00:00'}
        if n==0:row.update(validFrom=str(today),validTo=str(today))
        if n==21:row['validTo']=str(today-timedelta(days=1))
        if n==22:row['validFrom']=str(today+timedelta(days=1))
        if n==23:row['status']='draft'
        if n==24:row['publishAt']='2099-01-01T00:00:00+00:00'
        if n==25:row['status']='archived'
        items.append(row);ids.append(row['id'])
    try:
        check('independent store can be previewed',cli('merchants',[merchant])['status']=='dry_run')
        check('store preview writes nothing',not db('SELECT id FROM merchants WHERE id=:id',{'id':merchant_id}))
        check('independent store can be created',cli('merchants',[merchant],True)['status']=='saved')
        check('store has no forced attraction link',db('SELECT attraction_id FROM merchants WHERE id=:id',{'id':merchant_id})[0]['attraction_id'] is None)
        check('store import safely reruns',cli('merchants',[merchant],True)['status']=='unchanged')
        for terms in ['', '<p> </p>','<p>&nbsp;</p>']:
            check('terms cannot be blank '+repr(terms),cli('offers',[{**items[0],'terms':terms}],False,2)['status']=='validation_failed')
        check('missing merchant rejected',cli('offers',[{**items[0],'merchantId':str(uuid.uuid4())}],False,2)['status']=='validation_failed')
        check('reverse period rejected',cli('offers',[{**items[0],'validFrom':'2026-11-02','validTo':'2026-11-01'}],False,2)['status']=='validation_failed')
        check('invalid calendar rejected',cli('offers',[{**items[0],'validFrom':'2026-02-30'}],False,2)['status']=='validation_failed')
        check('batch apply stores actual offers',cli('offers',items,True)['status']=='saved')
        code,data=api('/api/v1/offers?q='+prefix+'&pageSize=100')
        check('only current offers appear by default',code==200 and data['total']==21)
        check('same-day start and end remain active',next(x for x in data['items'] if x['id']==ids[0])['validity']=='active')
        check('empty date bounds preserved',next(x for x in data['items'] if x['id']==ids[1])['validTo'] is None)
        check('active false does not reveal drafts or schedules',api('/api/v1/offers?active=false&q='+prefix+'&pageSize=100')[1]['total']==23)
        check('district filter reads store profile',api('/api/v1/offers?district='+__import__('urllib.parse',fromlist=['quote']).quote('東區')+'&q='+prefix)[1]['total']==0)
        check('invalid active query rejected',api('/api/v1/offers?active=maybe',expected=422)[0]==422)
        page2=api('/api/v1/offers?page=2')[1]
        code,html,_=get('/offers.html?page=2')
        check('offers deep page directly readable',code==200 and all(x['title'] in html for x in page2['items']))
        check('offers canonical keeps page','href="http://localhost:8081/offers.html?page=2"' in html)
        check('expired offer remains readable with warning','這項優惠已結束' in get('/offers/'+items[21]['slug']+'.html')[1])
        check('upcoming offer clearly says not started','這項優惠尚未開始' in get('/offers/'+items[22]['slug']+'.html')[1])
        check('original offer article layout retained','class="detail-layout" id="offer-detail-content"' in get('/offers/'+items[0]['slug']+'.html')[1])
        check('detail API matches same offer',api('/api/v1/offers/'+items[0]['slug'])[1]['offer']['id']==ids[0])
        for n in [23,24,25]:check('nonpublic detail hidden '+str(n),get('/offers/'+items[n]['slug']+'.html')[0]==404)
        changed={**items[0],'slug':prefix+'-renamed','version':1}
        cli('offers',[changed],True)
        code,_,headers=get('/offers/'+items[0]['slug']+'.html')
        check('old offer slug redirects',code==301 and headers.get('Location')=='/offers/'+changed['slug']+'.html')
        check('stale changed offer conflicts',cli('offers',[{**changed,'discount':'stale'}],True,3)['status']=='conflict')
        check('store deactivation saved',cli('merchants',[{**merchant,'status':'inactive','version':1}],True)['status']=='saved')
        check('inactive store hides all public offers',api('/api/v1/offers?active=false&q='+prefix)[1]['total']==0)
        check('inactive store detail is not leaked',get('/offers/'+changed['slug']+'.html')[0]==404)
        cli('merchants',[{**merchant,'status':'active','version':2}],True)
        check('store restore republishes related offers',api('/api/v1/offers?q='+prefix+'&pageSize=100')[1]['total']==21)
        invalid={**items[2],'version':1,'merchantId':str(uuid.uuid4())}
        check('invalid batch fails before first update',cli('offers',[{**items[1],'version':1,'title':'should not save'},invalid],True,2)['status']=='validation_failed')
        check('invalid batch preserves old version',db('SELECT title,version FROM offers WHERE id=:id',{'id':ids[1]})[0]=={'title':items[1]['title'],'version':1})
        image_path=ROOT/'backend/.build'/(prefix+'.png');image_path.write_bytes(test_png())
        run(['docker','compose','cp',str(image_path),'app-test:/tmp/'+prefix+'.png'])
        result=json.loads(run(['docker','compose','exec','-T','--user','www-data','app-test','php','bin/console.php','media:import','--module=offers','--file=/tmp/'+prefix+'.png','--alt=優惠圖片']).stdout)
        asset=result['media'];media.append(asset)
        check('unreferenced offer image is private',get(asset['url'])[0]==404)
        image_offer={**items[3],'version':1,'coverMediaId':asset['id']}
        cli('offers',[image_offer],True)
        check('published offer image can be read',asset_status(asset['url'])==200)
        news={'id':str(uuid.uuid4()),'title':'cross-module','type':'city','coverMediaId':asset['id']}
        check('news cannot borrow private offer module asset',cli('news',[news],False,2)['status']=='validation_failed')
        cli('offers',[{**image_offer,'version':2,'status':'archived'}],True)
        check('archived last image reference is private again',get(asset['url'])[0]==404)
        check('offers never enter news resource',json.loads(get('/api/v1/news?q='+prefix)[1])['total']==0)
        report={'checks':len(checks),'contractResponses':contracts,'cases':checks,'fixtureIdsRemoved':True}
        (ROOT/'artifacts/phase5-offers-results.json').write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf8')
        print(json.dumps({'checks':len(checks),'contractResponses':contracts,'fixtureIdsRemoved':True}))
    finally:
        for record_id in ids:
            db('DELETE FROM offer_aliases WHERE offer_id=:id',{'id':record_id});db('DELETE FROM offers WHERE id=:id',{'id':record_id})
        db('DELETE FROM merchants WHERE id=:id',{'id':merchant_id})
        for asset in media:
            db('DELETE FROM media_assets WHERE id=:id',{'id':asset['id']})
            # 只刪本次上傳的 UUID 圖片，路徑不來自外部輸入。
            run(['docker','compose','exec','-T','app-test','php','-r',"$key="+json.dumps(asset['url'].split('/')[-1])+";if(!preg_match('/^[a-f0-9-]{36}\\.(jpg|png|webp)$/D',$key))exit(1);@unlink('/var/www/media/'.$key);"])
        run(['docker','compose','exec','-T','--user','www-data','app-test','php','bin/console.php','content:publish'])

if __name__=='__main__':
    parser=argparse.ArgumentParser();parser.add_argument('--dependency-path');options=parser.parse_args()
    if options.dependency_path:sys.path.insert(0,str(Path(options.dependency_path).resolve()))
    main()
