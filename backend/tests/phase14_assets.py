"""只檢查 HTTP 的資產傳送；這裡明確不涵蓋瀏覽器與 CLS 的驗收。"""
import json,re,urllib.request,socket
from pathlib import Path
from api_integration import ROOT
connect=socket.create_connection
socket.create_connection=lambda address,*args,**kwargs: connect(('127.0.0.1',address[1]) if address[0]=='localhost' else address,*args,**kwargs)
http=urllib.request.build_opener(urllib.request.ProxyHandler({}));results=[]
for origin in ['http://localhost:8080','http://localhost:8081']:
    for path in ['/', '/planner.html', '/admin/members.html','/admin/settings.html','/admin/index.html']:
        with http.open(origin+path,timeout=15) as response:
            html=response.read().decode();assert response.status==200
        assert '/assets/fonts/NotoSansTC-common.woff2' in html and '/assets/fonts/NotoSerifTC-common.woff2' in html,(origin,path,'both font preloads')
        assert html.index('/assets/css/fonts.css')<html.index('/assets/css/style.css'),(origin,path,'early font CSS')
        if path=='/planner.html':
            assert 'id="trip-picker-host"' in html and 'id="save-trip"' in html
            assert 'id="planner-user-name">城市旅人' not in html
        if 'id="member-navigation"' in html:
            initial=html.split('id="member-navigation"',1)[1].split('</span></div>',1)[0]
            assert '確認帳號中' in initial
        assets=set(re.findall(r'(?:src|href)="(/assets/[^"?#]+)',html))
        for asset in assets:
            request=urllib.request.Request(origin+asset,method='HEAD')
            with http.open(request,timeout=15) as response:assert response.status==200,(origin,path,asset)
        results.append({'origin':origin,'path':path,'assets':len(assets),'ok':True})
    for name in ['NotoSansTC-common.woff2','NotoSerifTC-common.woff2']:
        request=urllib.request.Request(origin+'/assets/fonts/'+name,method='HEAD')
        with http.open(request,timeout=15) as response:
            assert response.status==200 and int(response.headers['Content-Length'])==(ROOT/'frontend/assets/fonts'/name).stat().st_size
    with http.open(origin+'/assets/css/fonts.css',timeout=15) as response:css=response.read().decode()
    # 每個 @font-face 都必須用 optional：來不及載入就維持系統字型，不在之後才換字造成版面跳動。
    faces=css.count('@font-face');assert faces>=2 and len(re.findall(r'font-display:\s*optional',css))==faces and 'fonts.googleapis.com' not in css
(ROOT/'artifacts/phase14/assets.json').write_text(json.dumps(results,ensure_ascii=False,indent=2),encoding='utf8')
print(json.dumps({'pages':len(results),'passed':True,'scope':'HTTP assets, not Browser layout or CLS'}))
