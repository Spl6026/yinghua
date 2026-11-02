"""以真實 HTTP 驗證 SEO head、同一版發布與故障恢復。"""
import argparse,copy,json,secrets,subprocess,sys,urllib.request,urllib.error,uuid
from html.parser import HTMLParser
from pathlib import Path
parser=argparse.ArgumentParser();parser.add_argument('--dependency-path');options=parser.parse_args()
if options.dependency_path:sys.path.insert(0,str(Path(options.dependency_path).resolve()))
sys.argv=[sys.argv[0]]
import jsonschema
from api_integration import Client,ROOT,fixture,password
from seo_settings_integration import restore,publish,payload

base='/api/v1/admin/settings/seo';public='/api/v1/site-settings/public';checks=[];contracts=0;prefix='p7-pub-'+secrets.token_hex(4)
spec=json.loads((ROOT/'backend/resources/openapi.json').read_text(encoding='utf8'));validator=jsonschema.Draft202012Validator(spec)
def check(name,ok):
    if not ok:raise AssertionError(name)
    checks.append(name)
def call(client,method,path,body=None):
    global contracts
    if method!='GET':client.refresh()
    code,result,headers=client.call(method,path,body)
    if path in [base,public]:
        response=spec['paths'][path][method.lower()]['responses'].get(str(code));check('contract status '+str(code),response is not None)
        validator.evolve(schema=response['content']['application/json']['schema']).validate(result);contracts+=1
    return code,result,headers
http=urllib.request.build_opener(urllib.request.ProxyHandler({}))
def html(path):
    request=urllib.request.Request('http://127.0.0.1:8081'+path,headers={'Host':'localhost:8081'})
    try:response=http.open(request,timeout=15)
    except urllib.error.HTTPError as error:response=error
    return response.status,response.read().decode('utf8'),response.headers
class Head(HTMLParser):
    def __init__(self,text):
        super().__init__(convert_charrefs=True);self.metas={};self.links=[];self.titles=[];self.in_head=False;self.in_title=False;self.feed(text)
    def handle_starttag(self,tag,attrs):
        attrs=dict(attrs)
        if tag=='head':self.in_head=True
        if not self.in_head:return
        if tag=='title':self.in_title=True;self.titles.append('')
        if tag=='meta':self.metas.setdefault(attrs.get('name',attrs.get('property','')),[]).append(attrs.get('content',''))
        if tag=='link' and attrs.get('rel')=='canonical':self.links.append(attrs.get('href',''))
    def handle_endtag(self,tag):
        if tag=='head':self.in_head=False
        if tag=='title':self.in_title=False
    def handle_data(self,text):
        if self.in_head and self.in_title:self.titles[-1]+=text

def main():
    fixture(password,'root');root=Client('admin');check('root login',root.login('admin')[0]==200)
    actor=root.call('GET',root.auth+'me')[1]['admin']['id'];stored=fixture('SELECT * FROM site_settings');old_overrides=fixture('SELECT * FROM content_seo')
    places=[str(uuid.uuid4()),str(uuid.uuid4())];film=str(uuid.uuid4());offer=str(uuid.uuid4());merchant=str(uuid.uuid4());news=[str(uuid.uuid4()) for _ in range(21)];trigger='p7_publish_'+secrets.token_hex(4)
    try:
        for index,place in enumerate(places):fixture(f"INSERT INTO attractions(id,slug,name,category,summary,description,address,status,publish_at) VALUES('{place}','{prefix}-place-{index}','景點 {index}','景點','公開摘要','公開介紹','','{'published' if index==0 else 'draft'}','2020-01-01')")
        fixture(f"INSERT INTO films(id,slug,title,synopsis,status,publish_at) VALUES('{film}','{prefix}-film','SEO 測試電影','電影介紹','published','2020-01-01')")
        values=','.join(f"('{id}','{prefix}-news-{index}','city','測試消息 {index}','消息摘要','<p>驗收文章</p>','published','2020-01-01','{actor}','{actor}')" for index,id in enumerate(news))
        fixture('INSERT INTO news_posts(id,slug,type,title,summary,body,status,publish_at,created_by,updated_by) VALUES'+values)
        fixture(f"INSERT INTO merchants(id,name,status) VALUES('{merchant}','SEO 測試店家','active')")
        fixture(f"INSERT INTO offers(id,slug,merchant_id,title,discount_text,terms,status,publish_at,created_by,updated_by) VALUES('{offer}','{prefix}-offer','{merchant}','SEO 測試優惠','九折','請出示測試畫面','published','2020-01-01','{actor}','{actor}')")
        publish();guest=Client();check('public settings anonymous',call(guest,'GET',public)[0]==200)
        current=payload(call(root,'GET',base)[1]);current['defaults'].update({'siteName':'隔離 SEO','titleSuffix':'｜隔離 SEO','canonicalOrigin':'https://example.test','googleVerification':'google_public_test','bingVerification':'BING_PUBLIC_TEST','twitterSite':'@spl','ogImageUrl':'https://example.test/assets/image-placeholder.svg'})
        current['overrides']=[{'target':{'type':'page','path':'/food.html'},'title':'獨立美食標題','description':'美食的獨立摘要'},
            {'target':{'type':'attraction','id':places[0]},'title':'景點 SEO 覆寫'},
            {'target':{'type':'attraction','id':places[1]},'title':'不應公開的草稿 SEO'},
            {'target':{'type':'film','id':film},'title':'電影 SEO 覆寫'},
            {'target':{'type':'news','id':news[0]},'title':'消息 SEO 覆寫'},
            {'target':{'type':'offer','id':offer},'title':'優惠 SEO 覆寫'},
            {'target':{'type':'page','path':'/planner.html'},'robots':'index,follow'}]
        code,saved,_=call(root,'PUT',base,current);check('save publishes immediately',code==200 and saved['publicationReady'])
        pub=call(guest,'GET',public)[1];check('public version matches saved',pub['version']==saved['version'] and pub['siteName']=='隔離 SEO')
        # 公開回應會帶出已發布的一般設定，但管理欄位與尚未發布的草稿內容都不能出現。
        check('public excludes admin/private metadata',set(pub)=={'siteName','seoDefaults','verification','version','generation','requestId','general'} and '不應公開' not in json.dumps(pub,ensure_ascii=False))
        code="<?php require 'vendor/autoload.php'; $c=new Yinghua\\Config(); if($c->string('DB_DATABASE')!=='yinghua_test')throw new RuntimeException('Test required'); $p=(new Yinghua\\Composition($c))->publication()->read(); echo json_encode(array_column($p['seo']['overrides'],'title'));"
        projection=subprocess.run(['docker','compose','exec','-T','app-test','php'],cwd=ROOT,input=code,capture_output=True,text=True,encoding='utf8',check=True)
        check('successful snapshot excludes draft overrides','不應公開的草稿 SEO' not in json.loads(projection.stdout))
        for path,title in [('/food.html','獨立美食標題'),('/places/'+prefix+'-place-0.html','景點 SEO 覆寫'),('/films/'+prefix+'-film.html','電影 SEO 覆寫'),('/news/'+prefix+'-news-0.html','消息 SEO 覆寫'),('/offers/'+prefix+'-offer.html','優惠 SEO 覆寫')]:
            code,text,headers=html(path);head=Head(text);check('200 unique head '+path,code==200 and head.titles==[title] and all(len(value)==1 for value in head.metas.values()))
            check('self canonical '+path,head.links==['https://example.test'+path] and head.metas['og:url']==head.links)
            check('verification and cache '+path,head.metas['google-site-verification']==['google_public_test'] and head.metas['msvalidate.01']==['BING_PUBLIC_TEST'] and 'no-store' in headers.get('Cache-Control',''))
            check('no executable head data '+path,'不應公開的草稿 SEO' not in text)
        check('food body preserved','沿著香氣' in html('/food.html')[1])
        index=Head(html('/index.html')[1]);check('home suffix updated once',index.titles[0].endswith('｜隔離 SEO') and index.titles[0].count('｜隔離 SEO')==1)
        first=Head(html('/city-news.html')[1]);second=Head(html('/city-news.html?page=2&utm_source=test')[1]);check('published pagination canonical',second.links==['https://example.test/city-news.html?page=2'] and first.titles!=second.titles and first.metas['description']!=second.metas['description'])
        for path in ['/admin/login.html','/admin/settings.html','/login.html','/register.html','/planner.html?place=secret','/favorites.html','/search.html','/reset-password.html?token=secret']:
            code,text,_=html(path);head=Head(text);check('protected page '+path,code==200 and head.metas['robots'][0].startswith('noindex') and all('secret' not in value for value in head.links))
        check('draft detail absent',html('/places/'+prefix+'-place-1.html')[0]==404)
        check('snapshot not directly exposed',html('/.snapshot.json')[0]==404)
        check('share asset anonymously available',html('/assets/image-placeholder.svg')[0]==200)
        invalid=payload(call(root,'GET',base)[1]);invalid['defaults']['ogImageUrl']='https://localhost/media/07000000-0000-4000-8000-000000000099.jpg'
        check('private media input rejected',call(root,'PUT',base,invalid)[0]==422)
        fixture(f"CREATE TRIGGER {trigger} BEFORE INSERT ON content_publications FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='owned publication failure'")
        try:
            pending=payload(call(root,'GET',base)[1]);pending['defaults']['titleSuffix']='｜待發布'
            code,result,_=call(root,'PUT',base,pending);check('saved but publication pending explicit',code==200 and not result['publicationReady'])
            check('public settings refuses mixed version',call(guest,'GET',public)[0]==503)
            check('public HTML refuses stale head',html('/food.html')[0]==503)
            check('admin recovery still works',call(root,'GET',base)[0]==200 and html('/admin/login.html')[0]==200 and html('/login.html')[0]==200)
        finally:fixture('DROP TRIGGER IF EXISTS '+trigger)
        publish();check('publication retry restores same saved version',call(guest,'GET',public)[1]['version']==result['version'] and call(root,'GET',base)[1]['publicationReady'])
        check('new suffix reached HTML','｜待發布' in Head(html('/index.html')[1]).titles[0])
        fixture(f"UPDATE attractions SET status='archived',version=version+1 WHERE id='{places[0]}'")
        publish();check('archived detail absent',html('/places/'+prefix+'-place-0.html')[0]==404)
    finally:
        fixture('DROP TRIGGER IF EXISTS '+trigger);restore('content_seo',old_overrides);restore('site_settings',stored)
        fixture("DELETE FROM films WHERE id='"+film+"'");fixture("DELETE FROM offers WHERE id='"+offer+"'")
        fixture("DELETE FROM merchants WHERE id='"+merchant+"'")
        for place in places:fixture("DELETE FROM attractions WHERE id='"+place+"'")
        fixture('DELETE FROM news_posts WHERE id IN ('+','.join("'"+id+"'" for id in news)+')');publish()
    report={'checks':len(checks),'contracts':contracts,'fixturesRemoved':True,'googleCalls':0}
    (ROOT/'artifacts/phase7-publication-results.json').write_text(json.dumps(report,indent=2),encoding='utf8');print(json.dumps(report))
if __name__=='__main__':main()
