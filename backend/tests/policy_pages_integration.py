"""唯讀檢查條款頁的真實連結與版本；絕不新增同意紀錄。"""
import json,urllib.request
from api_integration import Client,ROOT,fixture
from html.parser import HTMLParser

class Links(HTMLParser):
    def __init__(self,text):
        super().__init__();self.links=[];self.feed(text)
    def handle_starttag(self,tag,attrs):
        if tag=='a':self.links.append(dict(attrs))

def main():
    http=urllib.request.build_opener(urllib.request.ProxyHandler({}));checks=[]
    def check(name,ok):
        if not ok:raise AssertionError(name)
        checks.append(name)
    def read(path):
        req=urllib.request.Request('http://127.0.0.1:8081'+path,headers={'Host':'localhost:8081'})
        with http.open(req,timeout=15) as response:
            check('200 '+path,response.status==200);return response.read().decode('utf8')
    before=fixture('SELECT COUNT(*) AS n FROM member_consents')[0]['n']
    version=Client().refresh()['termsVersion']
    for page in ['/terms.html','/privacy.html']:
        html=read(page)
        check('published current version '+page,version in html and '{{TERMS_VERSION}}' not in html)
        check('development scope explicit '+page,'正式' in html and '待業主提供' in html)
        check('return registration link '+page,any(link.get('href')=='/register.html' for link in Links(html).links))
    register=read('/register.html');links=Links(register).links
    for path in ['/terms.html','/privacy.html']:
        check('registration opens policy without losing draft '+path,any(link.get('href')==path and link.get('target')=='_blank' and 'noopener' in link.get('rel','') for link in links))
    check('registration initial version',version in register)
    check('historical consents untouched',before==fixture('SELECT COUNT(*) AS n FROM member_consents')[0]['n'])
    report={'checks':len(checks),'existingConsentsUnchanged':True,'newConsentAccepted':False,'version':version}
    (ROOT/'artifacts/phase7-policy-results.json').write_text(json.dumps(report,indent=2),encoding='utf8');print(json.dumps(report))

if __name__=='__main__':main()
