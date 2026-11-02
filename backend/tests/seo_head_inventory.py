"""解析實際固定頁與產生頁的 HTML head，不執行 JavaScript。"""
import json,math,urllib.request,urllib.error
from api_integration import ROOT,Client,fixture
from seo_publication_integration import Head

def main():
    http=urllib.request.build_opener(urllib.request.ProxyHandler({}));results=[]
    catalog=json.loads((ROOT/'frontend/resources/seo-pages.json').read_text(encoding='utf8'))
    paths={'/'+str(page.relative_to(ROOT/'frontend/pages')).replace('\\','/') for page in (ROOT/'frontend/pages').rglob('*.html')}
    for table,prefix in [('attractions','places'),('films','films'),('news_posts','news'),('offers','offers')]:
        for row in fixture(f"SELECT slug FROM {table} WHERE status='published' AND deleted_at IS NULL AND publish_at<=UTC_TIMESTAMP()"):
            paths.add('/'+prefix+'/'+row['slug']+'.html')
    for route,kind in [('/events.html',''),('/events.html','event'),('/events.html','city'),('/activities.html','event'),('/city-news.html','city'),('/offers.html','')]:
        # 真實總數決定所有已生成列表頁；不憑固定 fixture 猜頁數。
        endpoint='/api/v1/offers?pageSize=20' if route=='/offers.html' else '/api/v1/news?pageSize=20'+('&type='+kind if kind else '')
        code,data,_=Client().call('GET',endpoint)
        assert code==200,(endpoint,code)
        total=max(1,math.ceil(data['total']/20))
        filtered='?type='+kind if route=='/events.html' and kind else ''
        paths.add(route+filtered)
        for page in range(2,total+1):paths.add(route+filtered+('&' if filtered else '?')+'page='+str(page))
    legacy={'/'+name+'-detail.html' for name in ['place','film','news','offer']}
    public={item['path'] for item in catalog if not item['protected'] and not item['template']};titles={};descriptions={}
    for path in sorted(paths):
        request=urllib.request.Request('http://127.0.0.1:8081'+path,headers={'Host':'localhost:8081'})
        try:response=http.open(request,timeout=15)
        except urllib.error.HTTPError as error:response=error
        code=response.status;text=response.read().decode('utf8')
        if path in legacy:
            assert code==404,(path,code);results.append({'path':path,'status':code});continue
        assert code==200,(path,code);head=Head(text)
        assert len(head.titles)==len(head.links)==1 and head.titles[0],path
        for key in ['description','robots','og:title','og:description','og:url','og:image','twitter:card','twitter:title','twitter:description','twitter:image']:
            assert len(head.metas.get(key,[]))==1 and head.metas[key][0],(path,key)
        assert all(len(value)==1 for value in head.metas.values()),path
        # 測試站與主站共用 build shell，保護頁使用既有 APP_URL:8080；公開頁取測試 publication:8081。
        assert head.links==head.metas['og:url'] and head.links[0].startswith(('http://localhost:8080/','http://localhost:8081/','https://')),path
        assert '{{' not in text and 'rel="icon"' in text,path
        if path in public:
            assert head.titles[0] not in titles,(path,'duplicate public title',titles.get(head.titles[0]))
            assert head.metas['description'][0] not in descriptions,(path,'duplicate public description',descriptions.get(head.metas['description'][0]))
            titles[head.titles[0]]=path;descriptions[head.metas['description'][0]]=path
        if path.startswith('/admin/') or any(item['path']==path and item['protected'] for item in catalog):assert head.metas['robots'][0].startswith('noindex'),path
        results.append({'path':path,'status':code,'title':head.titles[0]})
    report={'pages':len(results),'independentPublicPages':len(titles),'javascriptExecuted':False,'issues':0,'results':results}
    (ROOT/'artifacts/phase7-head-inventory.json').write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf8');print(json.dumps({key:value for key,value in report.items() if key!='results'}))

if __name__=='__main__':main()
