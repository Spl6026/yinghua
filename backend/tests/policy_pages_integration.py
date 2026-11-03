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
    # 後台填了條款／隱私版本名稱時，頁面顯示該名稱；同意紀錄存的是內部版本代碼（policy-雜湊），兩者不同。
    guest=Client();code,settings,_=guest.call('GET','/api/v1/site-settings/public')
    check('public settings readable',code==200)
    general=settings.get('general') or {}
    for page,kind in [('/terms.html','terms'),('/privacy.html','privacy')]:
        html=read(page)
        label=general.get(kind+'Version') or version
        check('published current version '+page,label in html and '{{TERMS_VERSION}}' not in html)
        # 條款與隱私權政策面向一般會員，不能再出現開發／測試用說明（使用者 2026-11-02 指定改為大眾文案）。
        check('public wording without development notes '+page,not any(word in html for word in ['開發測試','DEVELOPMENT NOTICE','測試資料','待業主提供']))
        check('return registration link '+page,any(link.get('href')=='/register.html' for link in Links(html).links))
    register=read('/register.html');links=Links(register).links
    for path in ['/terms.html','/privacy.html']:
        check('registration opens policy without losing draft '+path,any(link.get('href')==path and link.get('target')=='_blank' and 'noopener' in link.get('rel','') for link in links))
    # 註冊頁的版本在建置時寫入預設值，載入後再由前端換成目前版本；沒有自訂政策時兩者相同。
    check('registration initial version',version in register if not version.startswith('policy-') else 'data-terms-version' in register)
    check('historical consents untouched',before==fixture('SELECT COUNT(*) AS n FROM member_consents')[0]['n'])
    report={'checks':len(checks),'existingConsentsUnchanged':True,'newConsentAccepted':False,'version':version}
    (ROOT/'artifacts/phase7-policy-results.json').write_text(json.dumps(report,indent=2),encoding='utf8');print(json.dumps(report))

if __name__=='__main__':main()
