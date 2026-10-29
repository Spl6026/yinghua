"""[E-29] 從已提交、乾淨的發布來源建置一組新標籤，拒絕覆寫既有映像。"""
import argparse, subprocess
from operations import Deployment, ROOT, OperationError

parser=argparse.ArgumentParser(description=__doc__)
parser.add_argument('--env-file',required=True);parser.add_argument('--project',required=True)
options=parser.parse_args();deployment=Deployment(options.env_file,options.project);deployment.preflight()
head=subprocess.check_output(['git','rev-parse','HEAD'],cwd=ROOT,text=True).strip()
args=deployment.config['services']['app']['build']['args']
if args['VCS_REF']!=head:raise OperationError('RELEASE_COMMIT must be the current full committed HEAD')
inputs=['backend/src','backend/config','backend/public','backend/bin','backend/database',
        'backend/composer.json','backend/composer.lock','backend/resources/openapi.json','backend/resources/role-templates.json',
        'frontend/assets','frontend/pages','frontend/templates','frontend/resources','frontend/scripts',
        'docker','compose.production.yaml','.dockerignore','scripts/operations/release-manifest.mjs']
dirty=subprocess.check_output(['git','status','--porcelain','--',*inputs],cwd=ROOT,text=True)
if dirty:raise OperationError('Commit release inputs before building; a commit label must identify actual source')
for service in ['app','web']:
    image=deployment.config['services'][service]['image']
    if subprocess.run(['docker','image','inspect',image],capture_output=True).returncode==0:
        raise OperationError('Release image already exists; select a new immutable image tag')
deployment.run(['build','app','web'])
print('New app/web release images built from committed source; run isolated acceptance before opening traffic.')
