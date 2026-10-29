"""[D API草案 C43:H43; E-29] Anonymous HTTP contract and real MariaDB readiness failures."""
import json, subprocess, time
from api_integration import Client, ROOT, fixture

checks=[]
def check(name, condition):
    if not condition: raise AssertionError(name)
    checks.append(name)

def cli(environment):
    command=['docker','compose','exec','-T']
    for key,value in environment.items():command.extend(['-e',key+'='+value])
    command.extend(['app-test','php','tests/health_probe.php'])
    result=subprocess.run(command,cwd=ROOT,capture_output=True,text=True,encoding='utf8',check=True)
    doc=json.loads(result.stdout)
    check('bounded safe failure '+','.join(environment), doc['status']==503 and doc['elapsedMs']<5000
          and set(doc['payload'])=={'status','version','code','message','fieldErrors'}
          and doc['payload']['code']=='SERVICE_UNAVAILABLE')
    return doc

def main():
    client=Client();path='/api/v1/health'
    code,body,headers=client.call('GET',path)
    check('anonymous readiness',code==200 and body['status']=='ok' and body['version']=='development')
    check('only minimal response',set(body)=={'status','version','requestId'})
    check('no cookie / no-store / requestId',not headers.get('Set-Cookie') and 'no-store' in headers.get('Cache-Control','') and headers.get('X-Request-Id')==body['requestId'])
    code,body,headers=client.call('HEAD',path);check('HEAD same status empty body',code==200 and body=={} and not headers.get('Set-Cookie'))
    code,body,headers=client.call('POST',path,{},csrf=False);check('405 Allow without CSRF/cookie',code==405 and headers.get('Allow')=='GET, HEAD' and not headers.get('Set-Cookie'))
    check('exact path only',client.call('GET',path+'/extra')[0]==404)
    baseline=fixture('SELECT * FROM schema_migrations ORDER BY version')
    row=baseline[0];version=row['version'];checksum=row['checksum']
    try:
        fixture("UPDATE schema_migrations SET checksum=REPEAT('0',64) WHERE version='"+version+"'")
        code,body,headers=client.call('GET',path);check('checksum mismatch unavailable',code==503 and body['code']=='SERVICE_UNAVAILABLE' and not headers.get('Set-Cookie'))
        fixture("UPDATE schema_migrations SET checksum='"+checksum+"',applied_at=NULL WHERE version='"+version+"'")
        check('incomplete migration unavailable',client.call('GET',path)[0]==503)
        fixture("UPDATE schema_migrations SET version='health-fixture-missing' WHERE version='"+version+"'")
        check('missing expected migration unavailable',client.call('GET',path)[0]==503)
    finally:
        fixture("UPDATE schema_migrations SET version='"+version+"',checksum='"+checksum+"',applied_at='"+row['applied_at']+"' WHERE version IN ('"+version+"','health-fixture-missing')")
    check('schema restored exactly',fixture('SELECT * FROM schema_migrations ORDER BY version')==baseline)
    check('recovered readiness',client.call('GET',path)[0]==200)
    faults=[cli(env) for env in [{'DB_HOST':'127.0.0.1'}, {'DB_PASSWORD':'isolated-wrong-password'}, {'DB_DATABASE':'health_nonexistent'},
        {'APP_KEY':'short'}, {'APP_ENV':'test','APP_VERSION':'<private-invalid>'}, {'APP_URL':'file:///private'}]]
    boundary=subprocess.run(['docker','compose','exec','-T','app-test','php','tests/connection_boundary.php'],cwd=ROOT,capture_output=True,text=True,encoding='utf8',check=True)
    check('normal API eager connection failure uses safe503',json.loads(boundary.stdout)=={'checks':2,'passed':True})
    (ROOT/'artifacts/phase8-health.json').write_text(json.dumps({'checks':checks,'faults':faults},indent=2),encoding='utf8')
    print(json.dumps({'checks':len(checks),'faults':len(faults),'passed':True}))

if __name__=='__main__':main()
