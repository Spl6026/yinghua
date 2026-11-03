"""透過真實的 MariaDB、Nginx、PHP 與 Node 驗證電影公開頁的發布。"""
import datetime, json, secrets, subprocess, urllib.request, urllib.error, uuid
from api_integration import BASE, ROOT, Client, fixture, password


def main():
    fixture(password, 'root')
    suffix = secrets.token_hex(5)
    film_id = str(uuid.uuid4())
    slug = 'public-film-' + suffix
    checks = []
    def check(label, value):
        assert value, label
        checks.append(label)
    def publish():
        result = subprocess.run(['docker','compose','exec','-u','www-data','-T','app-test','php','bin/console.php','content:publish'],cwd=ROOT,capture_output=True,text=True,encoding='utf-8')
        assert result.returncode == 0, result.stderr
    def html(path):
        with urllib.request.build_opener(urllib.request.ProxyHandler({})).open(BASE + path, timeout=15) as response:
            return response.read().decode()
    utc = datetime.timezone.utc
    local = datetime.datetime.now(datetime.timezone(datetime.timedelta(hours=8)))
    monday = local.date() - datetime.timedelta(days=local.weekday())
    start = datetime.datetime.combine(monday,datetime.time(14),local.tzinfo).astimezone(utc)
    stamp = start.strftime('%Y-%m-%d %H:%M:%S')
    next_stamp = (start+datetime.timedelta(minutes=30)).strftime('%Y-%m-%d %H:%M:%S')
    try:
        fixture(f"INSERT INTO films(id,slug,title,synopsis,status,prototype_id) VALUES('{film_id}','{slug}','公開電影 {suffix}','純文字 <script> unsafe $1','published',991234);")
        for when in [stamp,next_stamp]:
            fixture(f"INSERT INTO film_screenings(id,film_id,starts_at) VALUES('{uuid.uuid4()}','{film_id}','{when}')")
        fixture(f"INSERT INTO film_aliases(path,film_id) VALUES('/films/old-{suffix}.html','{film_id}')")
        publish()
        guest=Client()
        status,data,_=guest.call('GET','/api/v1/films?week=current&pageSize=100')
        check('weekly public API',status==200 and data['period']['timeZone']=='Asia/Taipei')
        item=next(row for row in data['items'] if row['id']==film_id)
        check('same film groups two different starts',len(item['screenings'])==2)
        check('management fields private',not {'status','deletedAt','posterMediaId','posterSource','posterLicense'} & set(item))
        status,detail,_=guest.call('GET','/api/v1/films/'+slug)
        check('detail uses same generation',status==200 and detail['generation']==data['generation'])
        page=html('/films/'+slug+'.html')
        check('HTML readable without JS and escaped',('公開電影 '+suffix) in page and '&lt;script&gt;' in page and '<script> unsafe' not in page and '$1' in page)
        check('SSR title and canonical',f'rel="canonical" href="http://localhost:8081/films/{slug}.html"' in page)
        check('weekly cinema and homepage wired',('公開電影 '+suffix) in html('/cinema.html') and 'data-managed-films="true"' in html('/index.html'))
        check('attractions coexist',guest.call('GET','/api/v1/attractions')[0]==200)
        check('valid range',guest.call('GET',f'/api/v1/films?from={monday.isoformat()}&to={(monday+datetime.timedelta(days=7)).isoformat()}')[0]==200)
        for query in ['week=unknown','from=2026-02-30&to=2026-03-03','week=current&from=2026-01-01&to=2026-01-02','from=2026-02-02&to=2026-02-01']:
            check('invalid range '+query,guest.call('GET','/api/v1/films?'+query)[0]==422)
        class NoRedirect(urllib.request.HTTPRedirectHandler):
            def redirect_request(self,*args): return None
        opener=urllib.request.build_opener(urllib.request.ProxyHandler({}),NoRedirect())
        probe=Client();probe.http=opener
        # X-Original-URI 只由 Nginx 內部轉址設定（直接打 API 時會被清空），所以從實際的舊詳情頁網址驗證轉向。
        request=urllib.request.Request('http://127.0.0.1:8081/film-detail.html?id='+film_id,headers={'Host':'localhost:8081'})
        try:response=opener.open(request,timeout=15)
        except urllib.error.HTTPError as error:response=error
        check('film detail id redirects to canonical page',response.status==301 and response.headers.get('Location','').endswith('/films/'+slug+'.html'))
        check('direct API call cannot forge original URI',probe.call('GET','/api/v1/films/resolve-url',extra={'X-Original-URI':'/film-detail.html?id='+film_id})[0]==404)
        check('publication gate succeeds only for current snapshot',guest.call('GET','/api/v1/publication/access')[0]==204)
        check('media gate rejects malformed path',guest.call('GET','/api/v1/admin/media/access',extra={'X-Original-URI':'/media/not-an-image.txt'})[0]==403)
        check('attraction resolver remains restricted',probe.call('GET','/api/v1/attractions/resolve-url',extra={'X-Original-URI':'/places/unknown-'+suffix+'.html'})[0]==404)
        for path in ['/films/old-'+suffix+'.html','/film-detail.html?id=991234','/film-detail.html?id='+film_id]:
            try: response=opener.open(urllib.request.Request(BASE+path,headers={'Host':'localhost:8081'}),timeout=15)
            except urllib.error.HTTPError as error: response=error
            check('canonical redirect '+path,response.code==301 and urllib.parse.urlsplit(response.headers['Location']).path=='/films/'+slug+'.html')
        fixture(f"UPDATE films SET status='draft' WHERE id='{film_id}'")
        publish()
        check('draft API hidden',guest.call('GET','/api/v1/films/'+slug)[0]==404)
        try: html('/films/'+slug+'.html'); hidden=False
        except urllib.error.HTTPError as error: hidden=error.code==404
        check('draft HTML no static fallback',hidden)
    finally:
        fixture(f"DELETE FROM film_aliases WHERE film_id='{film_id}'")
        fixture(f"DELETE FROM film_screenings WHERE film_id='{film_id}'")
        fixture(f"DELETE FROM films WHERE id='{film_id}'")
        publish()
    print(json.dumps({'filmPublicChecks':len(checks),'checks':checks},ensure_ascii=False))

if __name__=='__main__': main()

