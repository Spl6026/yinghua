"""以 yinghua_test 實際驗證 HTML／XML、即時發布，以及 Schema.org 官方詞彙。

請在共用同一份測試資料的其他測試之後執行。只有傳入 --keep-browser 時才保留
瀏覽器檢查用的內容；設定變更與故障注入一律在 finally 還原。
"""
import argparse,copy,json,re,urllib.request,urllib.error,xml.etree.ElementTree as ET
from datetime import datetime,timezone
from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import urlsplit
from api_integration import ROOT,Client,fixture
from phase8_browser_fixture import publish,literal
from schema_vocabulary import SchemaVocabulary

checks=[];http_results=[];graphs=0;namespace={'m':'http://www.sitemaps.org/schemas/sitemap/0.9'}
class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self,*args,**kwargs):return None
http=urllib.request.build_opener(urllib.request.ProxyHandler({}),NoRedirect())

def check(name,value):
    assert value,name
    checks.append(name)

def get(path,method='GET',headers=None):
    req=urllib.request.Request('http://localhost:8081'+path,method=method,headers=headers or {})
    try:response=http.open(req,timeout=20)
    except urllib.error.HTTPError as error:response=error
    text=response.read().decode('utf8');http_results.append({'path':path,'method':method,'status':response.status})
    return response.status,text,response.headers

class Page(HTMLParser):
    def __init__(self,text):
        super().__init__(convert_charrefs=True);self.head=False;self.script=False;self.ld=False;self.blocks=[];self.canonical=[];self.metas={};self.visible=[];self.feed(text)
    def handle_starttag(self,tag,attrs):
        attrs=dict(attrs)
        if tag=='head':self.head=True
        if tag=='script':
            self.script=True;self.ld=attrs.get('type')=='application/ld+json'
            if self.ld:self.blocks.append('')
        if self.head and tag=='link' and attrs.get('rel')=='canonical':self.canonical.append(attrs['href'])
        if self.head and tag=='meta':self.metas[attrs.get('name',attrs.get('property'))]=attrs.get('content','')
    def handle_endtag(self,tag):
        if tag=='head':self.head=False
        if tag=='script':self.script=False;self.ld=False
    def handle_data(self,text):
        if self.ld:self.blocks[-1]+=text
        elif not self.head and not self.script:self.visible.append(text)

def main(schema_vocabulary,keep_browser=False):
    global graphs
    m=json.loads((ROOT/'artifacts/phase8-browser-fixture.json').read_text(encoding='utf8'));assert not m['cleaned']
    admin=Client('admin');check('owned admin login',admin.login(m['prefix'],m['password'])[0]==200)
    prefix=m['prefix'];created=[];merchant_id=None;seo_original=None;fault_id=None;keep=False;settings_touched=False
    stored_settings=fixture("SELECT * FROM site_settings WHERE `key`='seo'");stored_overrides=fixture('SELECT * FROM content_seo')
    vocabulary=SchemaVocabulary(schema_vocabulary)
    def call(method,path,body=None,status=200):
        code,result,_=admin.call(method,path,body);check(method+' '+path+' '+str(status),code==status);return result
    def sitemap():
        code,body,headers=get('/sitemap.xml');check('XML 200 and content type',code==200 and headers.get('Content-Type','').startswith('application/xml'))
        document=ET.fromstring(body);check('XML namespace urlset',document.tag=='{'+namespace['m']+'}urlset')
        return {row.find('m:loc',namespace).text:row.find('m:lastmod',namespace).text if row.find('m:lastmod',namespace) is not None else None for row in document.findall('m:url',namespace)},body
    def save_settings(changes=None,overrides=None):
        nonlocal settings_touched
        current=call('GET','/api/v1/admin/settings/seo');body={k:copy.deepcopy(current[k]) for k in ['version','defaults','overrides']}
        if changes:body['defaults'].update(changes)
        if overrides is not None:body['overrides']=overrides
        result=call('PUT','/api/v1/admin/settings/seo',body);settings_touched=True;return result
    def inspect(path,expected_type=None,expected_origin=None):
        nonlocal vocabulary
        global graphs
        code,body,_=get(path);check('HTML 200 '+path,code==200)
        page=Page(body);check('one canonical '+path,len(page.canonical)==1)
        if expected_origin:check('self canonical '+path,page.canonical==[expected_origin+path])
        check('one JSON-LD '+path,len(page.blocks)==1)
        graph=json.loads(page.blocks[0]);vocabulary.validate(graph);graphs+=1
        visible=' '.join(' '.join(page.visible).split())
        entities=[n for n in graph['@graph'] if n['@id'].endswith('#content')]
        for entity in entities:
            check('visible entity name '+path,' '.join(entity['name'].split()) in visible)
            if entity.get('description'):check('visible entity description '+path,' '.join(entity['description'].split()) in visible)
        if expected_type:check('content schema '+expected_type,any(n['@type']==expected_type for n in entities))
        check('no fabricated prices ratings '+path,not any(k in node for node in graph['@graph'] for k in ['aggregateRating','price','review','author']))
        return page,graph
    try:
        news={'title':prefix+' 城市索引驗收','slug':prefix+'-index-city','type':'city','summary':'可見消息摘要','body':'<p>真實已發布的測試正文。</p>','status':'published'}
        n=call('POST','/api/v1/admin/news',news,201)['record'];created.append(('news',n['id']));fault_id=n['id']
        event={**news,'title':prefix+' 活動索引驗收','slug':prefix+'-index-event','type':'event','eventStartAt':'2050-01-01T10:00:00+08:00','eventEndAt':'2050-01-01T12:00:00+08:00','eventLocation':'新竹市民廣場'}
        e=call('POST','/api/v1/admin/news',event,201)['record'];created.append(('news',e['id']))
        merchant=call('POST','/api/v1/admin/offers/merchants',{'name':prefix+' 索引合作店家'},201)['record'];merchant_id=merchant['id']
        offer={'title':prefix+' 優惠索引驗收','slug':prefix+'-index-offer','merchantId':merchant_id,'description':'可見優惠介紹','discount':'第二杯半價','terms':'每人限用一次','status':'published','validFrom':'2020-01-01','validTo':'2050-12-31'}
        o=call('POST','/api/v1/admin/offers',offer,201)['record'];created.append(('offers',o['id']))
        draft=call('POST','/api/v1/admin/news',{**news,'title':prefix+' 私人草稿','slug':prefix+'-index-draft','status':'draft'},201)['record'];created.append(('news',draft['id']))
        publish()
        urls,xml=sitemap();base='http://localhost:8081'
        for path,kind in [('/places/'+prefix+'-place.html','TouristAttraction'),('/films/'+prefix+'-film.html','Movie'),('/news/'+n['slug']+'.html','Article'),('/news/'+e['slug']+'.html','Event'),('/offers/'+o['slug']+'.html','Offer')]:
            check('published URL indexed '+path,base+path in urls);inspect(path,kind,base)
            check('lastmod actual source '+path,urls[base+path] is not None)
        check('draft absent from XML',draft['slug'] not in xml)
        check('internal and private URLs absent',not any(word in xml for word in ['/admin/','/api/','planner.html','favorites.html','search.html','login.html','register.html','_content_pages']))
        for url in urls:inspect(urlsplit(url).path+('?' + urlsplit(url).query if urlsplit(url).query else ''),expected_origin=base)
        for path in ['/planner.html','/favorites.html','/search.html','/login.html','/register.html','/admin/settings.html']:
            # 會員專屬頁沒有登入 cookie 時會被導向登入頁；帶上 cookie 才能檢查頁面本身的 head。
            member_only=path in ('/planner.html','/favorites.html')
            code,body,_=get(path,headers={'Cookie':'spl_member_session=seo-check'} if member_only else None);page=Page(body);check('private shell no schema '+path,code==200 and not page.blocks and page.metas.get('robots','').startswith('noindex'))
        code,robots,headers=get('/robots.txt');check('robots 200 plain text',code==200 and headers.get('Content-Type','').startswith('text/plain'))
        check('robots admin and same origin sitemap',robots=='User-agent: *\nDisallow: /admin/\nSitemap: '+base+'/sitemap.xml\n')
        code,body,_=get('/sitemap.xml','HEAD');check('sitemap HEAD without body',code==200 and body=='')
        check('missing part 404',get('/sitemap-999.xml')[0]==404)
        # lastmod 只在內容更新時改變，單純重複發布不應該改動它。
        old_path='/news/'+n['slug']+'.html'
        fixture("UPDATE news_posts SET updated_at='2020-01-01 01:02:03' WHERE id="+literal(n['id']));publish();urls,_=sitemap()
        stamp=call('GET','/api/v1/admin/settings/seo').get('updatedAt')
        known='2020-01-01T01:02:03.000Z'
        if stamp:known=max(known,datetime.fromisoformat(stamp).replace(tzinfo=timezone.utc).isoformat(timespec='milliseconds').replace('+00:00','Z'))
        check('known DB timestamp reflected',urls[base+old_path]==known)
        previous=urls[base+old_path];publish();check('republish does not invent now lastmod',sitemap()[0][base+old_path]==previous)
        n=call('PUT','/api/v1/admin/news/'+n['id'],{**news,'slug':prefix+'-index-renamed','version':n['version']})['record']
        new_path='/news/'+n['slug']+'.html';urls,_=sitemap();check('rename replaces sitemap URL',base+new_path in urls and base+old_path not in urls)
        code,_,headers=get(old_path);check('old slug 301',code==301 and headers.get('Location','').endswith(new_path))
        seo_original=call('GET','/api/v1/admin/settings/seo')
        changed=save_settings(overrides=[*seo_original['overrides'],{'target':{'type':'news','id':n['id']},'robots':'noindex,follow'}])
        check('noindex removed from sitemap',base+new_path not in sitemap()[0])
        code,body,_=get(new_path);check('noindex HTML remains public without graph',code==200 and not Page(body).blocks and Page(body).metas['robots']=='noindex,follow')
        save_settings({'canonicalOrigin':'https://index-validation.example.test'},seo_original['overrides'])
        official_urls,_=sitemap();check('configured HTTPS origin used',all(url.startswith('https://index-validation.example.test/') for url in official_urls))
        inspect(new_path,'Article','https://index-validation.example.test')
        check('robots canonical setting used','Sitemap: https://index-validation.example.test/sitemap.xml' in get('/robots.txt')[1])
        save_settings(seo_original['defaults'],seo_original['overrides']);seo_original=None
        # 只讓測試自有的紀錄渲染失敗；發布檢查必須擋下過期的檔案。
        fixture('UPDATE news_posts SET slug='+literal(prefix+'_invalid')+' WHERE id='+literal(fault_id))
        import subprocess
        failed=subprocess.run(['docker','compose','exec','-T','-u','www-data','app-test','php','bin/console.php','content:publish'],cwd=ROOT,capture_output=True)
        check('owned invalid content fails publication',failed.returncode!=0)
        for path in ['/sitemap.xml','/robots.txt',new_path]:check('stale generation blocked '+path,get(path)[0]==503)
        fixture('UPDATE news_posts SET slug='+literal(n['slug'])+' WHERE id='+literal(fault_id));publish()
        check('index recovery 200',get('/sitemap.xml')[0]==200)
        n=call('PUT','/api/v1/admin/news/'+n['id'],{**news,'slug':n['slug'],'version':n['version'],'status':'archived'})['record']
        check('archived excluded from sitemap',base+new_path not in sitemap()[0]);check('archived detail 404',get(new_path)[0]==404)
        # 只有執行者明確要求時，才留下一份已發布內容供介面檢查使用。
        if keep_browser:
            n=call('PUT','/api/v1/admin/news/'+n['id'],{**news,'slug':n['slug'],'version':n['version'],'status':'published'})['record']
            (ROOT/'artifacts/phase12/browser-content.json').write_text(json.dumps({'prefix':prefix,'newsPath':new_path,'eventPath':'/news/'+e['slug']+'.html','offerPath':'/offers/'+o['slug']+'.html','created':created,'merchantId':merchant_id}),encoding='utf8')
            keep=True
    finally:
        if fault_id and 'n' in locals():fixture('UPDATE news_posts SET slug='+literal(n['slug'])+' WHERE id='+literal(fault_id))
        if seo_original is not None:
            current=call('GET','/api/v1/admin/settings/seo');body={k:copy.deepcopy(seo_original[k]) for k in ['defaults','overrides']};body['version']=current['version'];call('PUT','/api/v1/admin/settings/seo',body)
        if settings_touched:
            owner=fixture("SELECT updated_by FROM site_settings WHERE `key`='seo'")
            assert owner and owner[0]['updated_by']==m['adminId'],'Other actor changed test settings; preserve their data'
            def sql(value):return 'NULL' if value is None else str(value) if isinstance(value,(int,float)) else literal(value)
            fixture('DELETE FROM content_seo');fixture("DELETE FROM site_settings WHERE `key`='seo'")
            for table,rows in [('site_settings',stored_settings),('content_seo',stored_overrides)]:
                for row in rows:fixture('INSERT INTO '+table+'('+','.join('`'+key+'`' for key in row)+') VALUES('+','.join(sql(value) for value in row.values())+')')
            check('original SEO rows restored exactly',fixture("SELECT * FROM site_settings WHERE `key`='seo'")==stored_settings and fixture('SELECT * FROM content_seo ORDER BY id')==sorted(stored_overrides,key=lambda row:row['id']))
        if not keep:
            for topic,identifier in reversed(created):
                table='news_posts' if topic=='news' else 'offers';alias='news_aliases' if topic=='news' else 'offer_aliases';fk='news_post_id' if topic=='news' else 'offer_id'
                fixture('DELETE FROM '+alias+' WHERE '+fk+'='+literal(identifier));fixture('DELETE FROM '+table+' WHERE id='+literal(identifier))
            if merchant_id:fixture('DELETE FROM merchants WHERE id='+literal(merchant_id))
        publish()
    result={'checks':len(checks),'graphsValidatedAgainstOfficialVocabulary':graphs,'httpResponses':len(http_results),'schemaVocabulary':str(schema_vocabulary),'passed':checks,'http':http_results,'externalPlatformVerified':False}
    (ROOT/'artifacts/phase12/integration.json').write_text(json.dumps(result,ensure_ascii=False,indent=2),encoding='utf8')
    print(json.dumps({k:v for k,v in result.items() if k not in ['passed','http']}))

if __name__=='__main__':
    parser=argparse.ArgumentParser();parser.add_argument('--schema-vocabulary',type=Path,required=True);parser.add_argument('--keep-browser',action='store_true');args=parser.parse_args()
    main(args.schema_vocabulary,args.keep_browser)
