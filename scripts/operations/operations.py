"""[E-29] Python 3 + Docker CLI，Windows/Linux 共用；不需主機 PHP 或額外 Python 套件。"""
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
        if check and result.returncode:raise OperationError('Docker operation failed: '+args[0]+' (details withheld; inspect safe service logs)')
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

def main():
    parser=argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--env-file',required=True);parser.add_argument('--project',required=True)
    parser.add_argument('operation',choices=['preflight','status'])
    options=parser.parse_args()
    deployment=Deployment(options.env_file,options.project)
    report=deployment.preflight()
    if options.operation=='status':
        report.update(core=deployment.readiness(),public=deployment.readiness(True),release=deployment.manifest()['version'])
    print(json.dumps(report,ensure_ascii=False))

if __name__=='__main__':
    try:main()
    except (OperationError,ValueError,OSError) as error:
        print('Operation refused: '+str(error),file=sys.stderr);raise SystemExit(1)
