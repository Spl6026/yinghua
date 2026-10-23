"""[E-29] New-volume production packaging rehearsal. Only explicit spl-p0-* projects."""
import argparse, http.client, http.cookiejar, json, secrets, socket, ssl, subprocess, sys, time, urllib.error, urllib.request, uuid
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
sys.path.insert(0,str(ROOT/'scripts/operations'))
from operations import Deployment

class LoopbackTLS(http.client.HTTPSConnection):
    def connect(self):
        self._create_connection=lambda address,timeout=None,source_address=None:socket.create_connection(('127.0.0.1',address[1]),timeout,source_address)
        super().connect()

class TLSHandler(urllib.request.HTTPSHandler):
    def https_open(self,request):return self.do_open(LoopbackTLS,request,context=self._context)

class Client:
    def __init__(self,origin,certificate):
        self.origin=origin;self.token=None;self.cookies=http.cookiejar.CookieJar()
        context=ssl.create_default_context(cafile=str(certificate))
        self.http=urllib.request.build_opener(urllib.request.ProxyHandler({}),TLSHandler(context=context),urllib.request.HTTPCookieProcessor(self.cookies))
    def call(self,method,path,body=None):
        headers={'Origin':self.origin,'Content-Type':'application/json'}
        if self.token:headers['X-CSRF-Token']=self.token
        request=urllib.request.Request(self.origin+path,data=None if body is None else json.dumps(body).encode(),method=method,headers=headers)
        try:response=self.http.open(request,timeout=6)
        except urllib.error.HTTPError as error:response=error
        raw=response.read();data=json.loads(raw) if raw and response.headers.get('Content-Type','').startswith('application/json') else raw.decode('utf8')
        if isinstance(data,dict) and data.get('csrfToken'):self.token=data['csrfToken']
        return response.status,data,response.headers

checks=[]
def check(name,condition):
    if not condition:raise AssertionError(name)
    checks.append(name)
    print(json.dumps({'check':name,'passed':True}),flush=True)

def sql(deployment,statement):
    return deployment.run(['exec','-T','db','sh','-c','MYSQL_PWD="$MARIADB_PASSWORD" exec mariadb --batch --skip-column-names -u "$MARIADB_USER" "$MARIADB_DATABASE"'],input=statement.encode()).stdout.decode('utf8')

def await_status(client,path,status,seconds=25):
    start=time.monotonic();seen=[]
    while time.monotonic()-start<seconds:
        try:actual,_,_=client.call('GET',path)
        except (urllib.error.URLError,ConnectionError):actual='connection-unavailable'
        seen.append(actual)
        if actual==status:return seen
        time.sleep(.5)
    raise AssertionError('State transition timed out: '+path+' '+str(seen))

def main():
    parser=argparse.ArgumentParser();parser.add_argument('--env-file',required=True);parser.add_argument('--project',required=True);parser.add_argument('--resume-initialized',action='store_true');options=parser.parse_args()
    if not options.project.startswith('spl-p0-'):raise RuntimeError('Owned rehearsal projects only')
    d=Deployment(options.env_file,options.project);d.preflight()
    if not options.resume_initialized and sql(d,"SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()").strip()!='0':raise RuntimeError('Cold-start rehearsal requires a completely empty new database')
    env=d.config['services']['app']['environment'];origin=env['APP_URL']
    tls=next(Path(mount['source']) for mount in d.config['services']['web']['volumes'] if mount['target']=='/etc/nginx/tls')
    client=Client(origin,tls/'fullchain.pem')
    password='Rehearsal-'+secrets.token_urlsafe(24)
    credentials=ROOT/'artifacts/phase8-rehearsal-credentials.json'
    if options.resume_initialized:
        password=json.loads(credentials.read_text(encoding='utf8'))['password']
        if sql(d,"SELECT COUNT(*) FROM admin_users WHERE email='admin@example.test' AND is_root=1").strip()!='1':raise RuntimeError('Owned initialized fixture required')
    else:credentials.write_text(json.dumps({'password':password,'email':'admin@example.test'}),encoding='utf8')
    report={'checks':checks,'project':options.project,'transitions':{}}
    try:
        if not options.resume_initialized:
            d.run(['up','-d','--no-build','--wait','--wait-timeout','40','web'])
            code,body,headers=client.call('GET','/api/v1/health')
            check('empty schema HTTP503, no Cookie',code==503 and body['code']=='SERVICE_UNAVAILABLE' and not headers.get('Set-Cookie'))
            check('HEAD empty schema 503',client.call('HEAD','/api/v1/health')[0]==503)
            d.run(['exec','-T','app','php','bin/console.php','db:migrate']);d.run(['exec','-T','app','php','bin/console.php','db:migrate'])
            d.run(['exec','-T','app','php','bin/console.php','db:seed'])
            d.run(['exec','-T','app','php','bin/console.php','admin:init'],input=('admin@example.test\n'+password+'\n'+password+'\n').encode())
            check('migrated core200 and public503 before first publication',client.call('GET','/api/v1/health')[0]==200 and client.call('GET','/')[0]==503)
        csrf=client.call('GET','/api/v1/admin/auth/csrf')[1];client.token=csrf['token']
        code,body,headers=client.call('POST','/api/v1/admin/auth/login',{'account':'admin','password':password})
        cookies=headers.get_all('Set-Cookie',[])
        check('HTTPS login succeeds with Secure/HttpOnly/SameSite cookies',code==200 and bool(cookies) and all('secure' in value.lower() and 'httponly' in value.lower() and 'samesite=lax' in value.lower() for value in cookies))
        check('TLS HSTS and no-store',headers.get('Strict-Transport-Security')=='max-age=31536000' and 'no-store' in headers.get('Cache-Control',''))
        d.run(['exec','-T','-u','www-data','app','php','bin/console.php','content:publish'])
        check('first publication root and public API200',client.call('GET','/')[0]==200 and client.call('GET','/api/v1/site-settings/public')[0]==200)
        manifest=d.manifest();report['release']=manifest['version'];report['manifestCommit']=manifest['commit']
        check('shared release 18 migrations and 67 HTML routes',len(manifest['migrations'])==18 and len([name for name in manifest['assets'] if name.endswith('.html')])==67)
        result=d.run(['exec','-T','app','sh','-c','test ! -e /var/www/app/tests && test ! -e /var/www/tools/vendor && test ! -e /var/www/app/.env && test ! -e /var/www/frontend-source/tests && test ! -e /var/www/app/resources/attractions-demo.json'])
        check('release excludes tests/tools/secrets/demo fixtures',result.returncode==0)
        redirect_port=next(port['published'] for port in d.config['services']['web']['ports'] if port['target']==80)
        redirect=http.client.HTTPConnection('127.0.0.1',int(redirect_port),timeout=5)
        redirect.request('GET','/cinema.html');response=redirect.getresponse()
        check('HTTP308 to exact HTTPS canonical',response.status==308 and response.getheader('Location')==origin+'/cinema.html');redirect.close()
        d.run(['restart','web']);check('TLS web restart is idempotent',await_status(client,'/api/v1/health',200)[-1]==200)
        d.run(['pause','db'])
        try:
            start=time.monotonic();code,body,_=client.call('GET','/api/v1/health');elapsed=time.monotonic()-start
            report['databasePauseMs']=round(elapsed*1000)
            check('unresponsive database HTTP503 within 5 seconds',code==503 and body['code']=='SERVICE_UNAVAILABLE' and elapsed<5)
        finally:d.run(['unpause','db'])
        check('database unpause readiness recovered',await_status(client,'/api/v1/health',200)[-1]==200)
        d.run(['stop','app'])
        try:check('FPM stop never pretends healthy',client.call('GET','/api/v1/health')[0] in (502,503,504))
        finally:d.run(['start','app'])
        report['transitions']['appRestart']=await_status(client,'/api/v1/health',200)
        place=str(uuid.uuid4());report['scheduledPlace']=place
        sql(d,"INSERT INTO attractions(id,slug,name,category,summary,description,address,status,publish_at) VALUES('"+place+"','p0-rehearsal-scheduled','Rehearsal scheduled','景點','','','','published',UTC_TIMESTAMP()+INTERVAL 3 SECOND)")
        d.run(['exec','-T','-u','www-data','app','php','bin/console.php','content:publish'])
        time.sleep(4)
        check('due content refuses stale HTML with worker stopped',client.call('GET','/')[0]==503)
        d.run(['up','-d','--no-build','publisher'])
        report['transitions']['workerRecovery']=await_status(client,'/',200)
        public=client.call('GET','/api/v1/site-settings/public')[1]
        check('worker publishes current generation',public['generation']==d.readiness(True)['generation'])
        check('scheduled detail generated',client.call('GET','/places/p0-rehearsal-scheduled.html')[0]==200)
        d.run(['restart','publisher'])
        check('worker restart preserves publication',d.readiness(True)['generation']==public['generation'])
        report['passed']=True
    finally:
        (ROOT/'artifacts/phase8-production-rehearsal.json').write_text(json.dumps(report,indent=2),encoding='utf8')
    print(json.dumps({'checks':len(checks),'passed':True}))

if __name__=='__main__':main()
