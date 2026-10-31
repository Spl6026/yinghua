"""[S L31/L33; E-34] Read-only sitemap/canonical/JSON-LD checks; no fixture or JavaScript required."""
import argparse,json,re,urllib.request,urllib.error,xml.etree.ElementTree as ET
from pathlib import Path
from urllib.parse import urlsplit
from phase12_integration import Page,NoRedirect
from schema_vocabulary import SchemaVocabulary

def main():
    parser=argparse.ArgumentParser();parser.add_argument('--base-url',default='http://localhost:8081');parser.add_argument('--schema-vocabulary',type=Path,required=True);parser.add_argument('--output',type=Path,required=True);args=parser.parse_args()
    http=urllib.request.build_opener(urllib.request.ProxyHandler({}),NoRedirect());vocabulary=SchemaVocabulary(args.schema_vocabulary);namespace={'m':'http://www.sitemaps.org/schemas/sitemap/0.9'};pages=[];files=[]
    def read(path):
        response=http.open(args.base_url+path,timeout=20);return response.status,response.read().decode('utf8'),response.headers
    code,robots,headers=read('/robots.txt');assert code==200 and headers['Content-Type'].startswith('text/plain') and 'Disallow: /admin/' in robots
    def urls(path):
        code,xml,headers=read(path);assert code==200 and headers['Content-Type'].startswith('application/xml');document=ET.fromstring(xml);files.append(path)
        if document.tag=='{'+namespace['m']+'}sitemapindex':
            return [url for element in document.findall('m:sitemap/m:loc',namespace) for url in urls(urlsplit(element.text).path)]
        assert document.tag=='{'+namespace['m']+'}urlset'
        values=[element.text for element in document.findall('m:url/m:loc',namespace)];assert len(values)<=50000
        return values
    indexed=urls('/sitemap.xml');assert len(set(indexed))==len(indexed)
    for url in indexed:
        parsed=urlsplit(url);path=parsed.path+('?' + parsed.query if parsed.query else '');assert not any(x in path for x in ['/admin/','/api/','/planner.html','/favorites.html','/login.html','/register.html','/search.html','/_content_pages/'])
        code,html,_=read(path);page=Page(html);assert code==200 and page.canonical==[url] and len(page.blocks)==1,(path,code,page.canonical)
        assert not re.search(r'(?:noindex|none)',page.metas.get('robots',''))
        assert page.metas['og:url']==url and page.metas['twitter:card'] in ['summary','summary_large_image']
        graph=json.loads(page.blocks[0]);vocabulary.validate(graph)
        pages.append({'path':path,'canonical':url,'types':[node['@type'] for node in graph['@graph']]})
    if indexed:
        origin=urlsplit(indexed[0]);assert 'Sitemap: '+origin.scheme+'://'+origin.netloc+'/sitemap.xml' in robots
    output={'indexedPages':len(pages),'sitemapFiles':files,'issues':0,'javascriptExecuted':False,'pages':pages};args.output.write_text(json.dumps(output,ensure_ascii=False,indent=2),encoding='utf8');print(json.dumps({key:value for key,value in output.items() if key!='pages'}))

if __name__=='__main__':main()
