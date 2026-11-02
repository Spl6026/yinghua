"""只停掉演練專用的資料庫；公開 API 與 HTML 必須安全地失敗，並在恢復後回復正常。"""
import argparse,json,sys,time,urllib.error
from pathlib import Path
from production_rehearsal import Client,ROOT,await_status
sys.path.insert(0,str(ROOT/'scripts/operations'))
from operations import Deployment
parser=argparse.ArgumentParser();parser.add_argument('--env-file',required=True);parser.add_argument('--project',required=True);parser.add_argument('--observe',action='store_true');options=parser.parse_args()
if not options.project.startswith('spl-p0-'):raise RuntimeError('Owned isolated project required')
d=Deployment(options.env_file,options.project);d.preflight();d.readiness(True)
tls=next(Path(mount['source']) for mount in d.config['services']['web']['volumes'] if mount['target']=='/etc/nginx/tls')
client=Client(d.config['services']['app']['environment']['APP_URL'],tls/'fullchain.pem');observed=[]
d.run(['stop','db'])
try:
    for path in ['/api/v1/health','/api/v1/attractions','/']:
        start=time.monotonic()
        try:
            status,body,headers=client.call('GET',path)
            code=body.get('code') if isinstance(body,dict) else None
        except (TimeoutError,urllib.error.URLError):status='transport-timeout';code=None
        observed.append({'path':path,'status':status,'code':code,'elapsedMs':round((time.monotonic()-start)*1000)})
finally:d.run(['start','db'])
await_status(client,'/api/v1/health',200)
assert client.call('GET','/')[0]==200
result={'observationOnly':options.observe,'responses':observed,'recovered':True}
(ROOT/('artifacts/phase8-db-outage-'+('before' if options.observe else 'after')+'.json')).write_text(json.dumps(result,indent=2),encoding='utf8')
print(json.dumps(result))
if not options.observe:assert all(row['status']==503 for row in observed)
