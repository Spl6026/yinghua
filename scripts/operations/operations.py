"""維運指令（備份、還原等），只需要 Python 3 與 Docker CLI，Windows 與 Linux 共用；主機不需安裝 PHP 或額外的 Python 套件。"""
import argparse, datetime, hashlib, json, os, re, subprocess, sys, tarfile, time
from pathlib import Path
from urllib.parse import urlsplit

ROOT=Path(__file__).resolve().parents[2]

class OperationError(RuntimeError): pass

class Deployment:
    def __init__(self, env_file, project):
        if not re.fullmatch(r'[a-z0-9][a-z0-9_-]{2,62}',project) or project=='yinghua':
            raise OperationError('Use a distinct explicit production/rehearsal project; local yinghua is protected')
        self.project=project
        self.command=['docker','compose','--env-file',str(Path(env_file).resolve()),'-f',str(ROOT/'compose.production.yaml')]
        self.config=json.loads(self.run(['config','--format','json']).stdout)
        if self.config['name']!=project:raise OperationError('Configuration project does not match explicit target')

    def run(self,args,input=None,check=True):
        result=subprocess.run(self.command+args,cwd=ROOT,input=input,capture_output=True)
        if check and result.returncode:
            detail='details withheld for a command that may contain data'
            if args[0] in {'start','stop','restart','up','down','pause','unpause'}:
                detail=result.stderr.decode('utf8',errors='replace').strip()[-1800:]
                for service in getattr(self,'config',{}).get('services',{}).values():
                    for key,value in service.get('environment',{}).items():
                        if any(word in key for word in ['KEY','SECRET','PASSWORD','TOKEN']) or key in ['SMTP_USERNAME','MAIL_FROM_ADDRESS']:
                            if value:detail=detail.replace(str(value),'[redacted]')
            raise OperationError('Docker operation failed: '+args[0]+' (exit '+str(result.returncode)+'): '+detail)
        return result

    def preflight(self):
        services=self.config['services']
        if set(services)!={'db','app','publisher','web'}:raise OperationError('Unexpected production services')
        env=services['app']['environment'];origin=urlsplit(env['APP_URL'])
        if origin.scheme!='https' or not origin.hostname or origin.username or origin.password or origin.query or origin.fragment or origin.path not in ('','/'):
            raise OperationError('A valid HTTPS origin is required')
        if env['SESSION_SECURE']!='true' or len(env['APP_KEY'])<32 or not re.fullmatch(r'[a-zA-Z0-9][a-zA-Z0-9._-]{0,63}',env['APP_VERSION']):
            raise OperationError('Invalid secure-cookie, APP_KEY or public version configuration')
        if services['db'].get('ports') or services['app'].get('ports') or services['publisher'].get('ports'):
            raise OperationError('Database and FPM must not expose host ports')
        if services['publisher']['image']!=services['app']['image']:raise OperationError('Worker must use the app release image')
        for name,service in services.items():
            for mount in service.get('volumes',[]):
                if mount['type']=='bind' and not (name=='web' and mount['target']=='/etc/nginx/tls' and mount.get('read_only')):
                    raise OperationError('Unexpected source bind mount')
            if service.get('logging',{}).get('options')!={'max-size':'10m','max-file':'3'}:raise OperationError('Log rotation missing')
        return {'project':self.project,'version':env['APP_VERSION'],'services':sorted(services),'databaseHostPort':False}

    def manifest(self):
        app=json.loads(self.run(['exec','-T','app','cat','/var/www/release.json']).stdout)
        web=json.loads(self.run(['run','--rm','--no-deps','--entrypoint','cat','web','/var/www/release.json']).stdout)
        environment=self.config['services']['app']['environment']
        if app!=web or app['version']!=environment['APP_VERSION'] or app.get('origin')!=environment['APP_URL'].rstrip('/') or app.get('policyVersion')!=environment['TERMS_VERSION']:
            raise OperationError('App/web/config release mismatch')
        return app

    def readiness(self,public=False):
        result=self.run(['exec','-T','-u','www-data','app','php','bin/readiness.php','public' if public else 'core'],check=False)
        if result.returncode:raise OperationError('Public publication not ready' if public else 'Core not ready')
        return json.loads(result.stdout)

    def sql(self,statement):
        return self.run(['exec','-T','db','sh','-c','MYSQL_PWD="$MARIADB_PASSWORD" exec mariadb --batch --skip-column-names -u "$MARIADB_USER" "$MARIADB_DATABASE"'],input=statement.encode()).stdout.decode('utf8')

    def running(self,service):
        return bool(self.run(['ps','--status','running','-q',service]).stdout.strip())

    def pause_writes(self):
        active=[name for name in ['web','publisher'] if self.running(name)]
        if active:self.run(['stop',*active])
        # 用 SIGQUIT 讓 FPM 處理完已收到的請求再結束，避免只停掉 Nginx 後仍有背景寫入。
        self.run(['restart','--timeout','30','app'])
        return active

    def media(self):
        return json.loads(self.run(['exec','-T','-u','www-data','app','php','bin/recovery.php','media']).stdout)

    def stream(self,args,path,restore=False):
        with path.open('rb' if restore else 'wb') as stream:
            kwargs={'stdin':stream,'stdout':subprocess.DEVNULL} if restore else {'stdout':stream}
            result=subprocess.run(self.command+args,cwd=ROOT,stderr=subprocess.PIPE,**kwargs)
        if result.returncode:raise OperationError('Backup/restore stream failed; traffic remains closed')

    def backup(self,target):
        target=private_target(target)
        if target.exists():raise OperationError('Backup destination already exists; never overwrite')
        manifest=self.manifest();self.readiness(True)
        active=self.pause_writes();started=time.monotonic()
        try:
            # 等所有 HTTP 寫入口與 worker 都停止後，才在同一個時間窗內讀取資料庫與媒體檔，確保兩者一致。
            self.readiness(True);media=self.media();target.mkdir(parents=True,mode=0o700)
            self.stream(['exec','-T','db','sh','-c','MYSQL_PWD="$MARIADB_PASSWORD" exec mariadb-dump -u "$MARIADB_USER" --single-transaction --quick --triggers --routines --events --hex-blob --skip-comments "$MARIADB_DATABASE"'],target/'database.sql')
            self.stream(['exec','-T','app','tar','-C','/var/www/media','-cf','-','.'],target/'media.tar')
            (target/'release.json').write_text(json.dumps(manifest,indent=2),encoding='utf8')
            images={}
            for name in ['app','web']:
                container=self.run(['ps','-a','-q',name]).stdout.decode().strip()
                result=subprocess.run(['docker','inspect','--format','{{.Image}}',container],capture_output=True,check=True)
                images[name]=result.stdout.decode().strip()
            report={'format':1,'sourceProject':self.project,'createdAt':datetime.datetime.now(datetime.timezone.utc).isoformat(),
                'generation':self.readiness(True)['generation'],'media':media,'images':images,
                'appKeyFingerprint':hashlib.sha256(self.config['services']['app']['environment']['APP_KEY'].encode()).hexdigest(),
                'files':{name:hash_file(target/name) for name in ['database.sql','media.tar','release.json']},'elapsedSeconds':round(time.monotonic()-started,3)}
            (target/'manifest.json').write_text(json.dumps(report,indent=2),encoding='utf8')
            # 這份清單無法取代外部受保護、加密的備份位置，也不能單靠它證明備份完整。
            verify_backup(target)
            return {'project':self.project,'backup':str(target),'mediaFiles':len(media),'elapsedSeconds':report['elapsedSeconds']}
        finally:
            # 備份失敗不會動到原資料；只恢復原本就在運行的入口，不會自動打開原本關閉的站。
            if active:self.run(['start',*active])

    def restore(self,target):
        target=private_target(target);report,release=verify_backup(target)
        if self.project==report['sourceProject']:raise OperationError('Restore must use a different explicit empty target project')
        if self.sql('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()').strip()!='0':
            raise OperationError('Restore target database is not empty; existing data will never be overwritten')
        existing=self.run(['exec','-T','app','find','/var/www/media','-mindepth','1','-print','-quit']).stdout
        if existing.strip():raise OperationError('Restore media volume must be empty')
        current=self.manifest()
        if release['migrations']!=current['migrations']:raise OperationError('Backup schema and selected release are incompatible')
        fingerprint=hashlib.sha256(self.config['services']['app']['environment']['APP_KEY'].encode()).hexdigest()
        if fingerprint!=report['appKeyFingerprint']:raise OperationError('Restore requires the separately preserved APP_KEY')
        self.pause_writes()
        started=time.monotonic()
        self.stream(['exec','-T','db','sh','-c','MYSQL_PWD="$MARIADB_PASSWORD" exec mariadb -u "$MARIADB_USER" "$MARIADB_DATABASE"'],target/'database.sql',True)
        self.stream(['exec','-T','-u','www-data','app','tar','--no-same-owner','--no-same-permissions','-C','/var/www/media','-xf','-'],target/'media.tar',True)
        self.run(['exec','-T','-e','RESTORE_AUTHORIZATION_WINDOW=confirmed-empty-target','app','php','bin/recovery.php','revoke-auth'])
        self.readiness()
        if self.media()!=report['media']:raise OperationError('Restored media/DB references mismatch')
        self.run(['exec','-T','-u','www-data','app','php','bin/console.php','content:publish'])
        public=self.readiness(True)
        return {'project':self.project,'restored':True,'trafficOpened':False,'generation':public['generation'],'elapsedSeconds':round(time.monotonic()-started,3)}

    def open(self):
        self.pause_writes()
        self.manifest();self.readiness();public=self.readiness(True);self.media()
        self.run(['up','-d','--no-build','publisher','web'])
        return {'project':self.project,'trafficOpened':True,'generation':public['generation']}

    def upgrade(self):
        # 既有站台升級：先關閉對外入口與背景工作，換上新版 app 後才執行 migration，
        # 新程式不會在舊資料結構上服務請求；migration 自帶鎖與 checksum，可安全重跑。
        self.pause_writes()
        self.run(['up','-d','--no-deps','--no-build','app'])
        self.manifest()
        self.run(['exec','-T','app','php','bin/console.php','db:migrate'])
        self.readiness();self.media()
        self.run(['exec','-T','-u','www-data','app','php','bin/console.php','content:publish'])
        return self.open()

    def rollback(self):
        self.pause_writes()
        self.run(['up','-d','--no-deps','--no-build','app'])
        self.manifest();self.readiness();self.media()
        self.run(['exec','-T','-u','www-data','app','php','bin/console.php','content:publish'])
        return self.open()

def private_target(value):
    target=Path(value).resolve()
    if target==ROOT or ROOT in target.parents and ROOT/'artifacts' not in target.parents:
        raise OperationError('Repository backups belong under ignored artifacts or outside the repository')
    return target

def hash_file(path):
    digest=hashlib.sha256()
    with path.open('rb') as stream:
        for block in iter(lambda:stream.read(1024*1024),b''):digest.update(block)
    return digest.hexdigest()

def verify_backup(target):
    try:
        report=json.loads((target/'manifest.json').read_text(encoding='utf8'))
        if not isinstance(report,dict) or report.get('format')!=1 or set(report['files'])!={'database.sql','media.tar','release.json'}:raise ValueError()
        if not isinstance(report['media'],dict) or not re.fullmatch(r'[a-f0-9]{64}',report['appKeyFingerprint']) or not isinstance(report['sourceProject'],str):raise ValueError()
        for name,checksum in report['files'].items():
            if hash_file(target/name)!=checksum:raise ValueError()
        release=json.loads((target/'release.json').read_text(encoding='utf8'))
        if not isinstance(release,dict) or release.get('format')!=1 or not re.fullmatch(r'[a-zA-Z0-9][a-zA-Z0-9._-]{0,63}',release['version']) or not re.fullmatch(r'[a-f0-9]{40}',release['commit']):raise ValueError()
        if not isinstance(release['migrations'],dict) or not release['migrations']:raise ValueError()
        for name,checksum in release['migrations'].items():
            if not re.fullmatch(r'[a-zA-Z0-9_]+\.sql',name) or not re.fullmatch(r'[a-f0-9]{64}',checksum):raise ValueError()
        actual={}
        with tarfile.open(target/'media.tar','r:') as archive:
            for member in archive.getmembers():
                if member.isdir() and member.name in ('.','./'):continue
                name=member.name.removeprefix('./')
                if not member.isfile() or member.size>8*1024*1024 or not re.fullmatch(r'[a-f0-9-]{36}\.(jpg|png|webp)',name) or name in actual:raise ValueError()
                stream=archive.extractfile(member)
                if stream is None:raise ValueError()
                digest=hashlib.sha256()
                for block in iter(lambda:stream.read(1024*1024),b''):digest.update(block)
                actual[name]=digest.hexdigest()
        if actual!=report['media']:raise ValueError()
        return report,release
    except (KeyError,TypeError,ValueError,OSError,tarfile.TarError):
        raise OperationError('Backup integrity/format verification failed; do not open traffic') from None

def main():
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--env-file',required=True);parser.add_argument('--project',required=True)
    parser.add_argument('operation',choices=['preflight','status','backup','verify-backup','restore','open','upgrade','rollback'])
    parser.add_argument('--backup-directory')
    options=parser.parse_args()
    deployment=Deployment(options.env_file,options.project)
    report=deployment.preflight()
    if options.operation in ['backup','verify-backup','restore']:
        if not options.backup_directory:raise OperationError('Explicit --backup-directory required')
        if options.operation=='verify-backup':
            verify_backup(private_target(options.backup_directory));report['backupVerified']=True
        else:report=getattr(deployment,options.operation)(options.backup_directory)
    elif options.operation in ['open','upgrade','rollback']:report=getattr(deployment,options.operation)()
    if options.operation=='status':
        report.update(core=deployment.readiness(),public=deployment.readiness(True),release=deployment.manifest()['version'])
    print(json.dumps(report,ensure_ascii=False))

if __name__=='__main__':
    try:main()
    except (OperationError,ValueError,OSError) as error:
        print('Operation refused: '+str(error),file=sys.stderr);raise SystemExit(1)
