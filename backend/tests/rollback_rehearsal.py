"""[E-29] Two committed-source release labels, compatible schema and current-data rollback."""
import argparse,json,secrets,time
from production_rehearsal import Client,ROOT,check,checks,sql,await_status
from recovery_rehearsal import data_snapshot,refused
from operations import Deployment
from pathlib import Path

def main():
    parser=argparse.ArgumentParser();parser.add_argument('--current-env',required=True);parser.add_argument('--previous-env',required=True);parser.add_argument('--project',required=True);options=parser.parse_args()
    if not options.project.startswith('spl-p0-'):raise RuntimeError('Owned rehearsal only')
    current=Deployment(options.current_env,options.project);previous=Deployment(options.previous_env,options.project)
    current.preflight();previous.preflight()
    if current.config['services']['app']['environment']['DB_DATABASE']!=previous.config['services']['app']['environment']['DB_DATABASE']:raise RuntimeError('Same current database required')
    tls=next(Path(mount['source']) for mount in current.config['services']['web']['volumes'] if mount['target']=='/etc/nginx/tls')
    client=Client(current.config['services']['app']['environment']['APP_URL'],tls/'fullchain.pem')
    report={'checks':checks,'project':options.project,'scope':'same committed application source, different release labels/images; no arbitrary historical code compatibility claim'}
    try:
        current.rollback();await_status(client,'/api/v1/health',200)
        check('selected newer release activated',client.call('GET','/api/v1/health')[1]['version']==current.config['services']['app']['environment']['APP_VERSION'])
        row=sql(current,"SELECT id,slug FROM attractions WHERE status='published' AND slug LIKE 'p0-restore-%-place' ORDER BY id LIMIT 1").strip().split('\t')
        if len(row)!=2:raise RuntimeError('Recovery fixture required before rollback rehearsal')
        id,slug=row
        sql(current,"UPDATE attractions SET status='archived',version=version+1 WHERE id='"+id+"'")
        current.run(['exec','-T','-u','www-data','app','php','bin/console.php','content:publish'])
        check('current content archived before rollback',client.call('GET','/places/'+slug+'.html')[0]==404)
        before=data_snapshot(current)
        previous.rollback();await_status(client,'/api/v1/health',200)
        check('previous release version active',client.call('GET','/api/v1/health')[1]['version']==previous.config['services']['app']['environment']['APP_VERSION'])
        check('rollback preserves all current business data',data_snapshot(previous)==before)
        check('rollback never resurrects archived HTML',client.call('GET','/places/'+slug+'.html')[0]==404)
        migration=sql(previous,'SELECT version,checksum FROM schema_migrations ORDER BY version LIMIT 1').strip().split('\t')
        sql(previous,"UPDATE schema_migrations SET checksum=REPEAT('0',64) WHERE version='"+migration[0]+"'")
        try:
            refused('incompatible current schema blocks rollback opening',previous.rollback)
            check('incompatible rollback keeps web/worker closed',not previous.running('web') and not previous.running('publisher'))
        finally:sql(previous,"UPDATE schema_migrations SET checksum='"+migration[1]+"' WHERE version='"+migration[0]+"'")
        previous.rollback();await_status(client,'/api/v1/health',200)
        marker='phase8-private-'+secrets.token_hex(12)
        client.call('GET','/api/v1/health?token='+marker)
        previous.run(['stop','app'])
        try:check('upstream failure with sensitive query remains non200',client.call('GET','/api/v1/health?token='+marker)[0] in (502,503,504))
        finally:previous.run(['start','app'])
        await_status(client,'/api/v1/health',200)
        logs=previous.run(['logs','--no-log-prefix','--tail','300','app','web','publisher']).stdout
        check('normal and upstream-error logs omit query secret',marker.encode() not in logs)
        report['releaseManifest']=previous.manifest();report['passed']=True
    finally:(ROOT/'artifacts/phase8-rollback-rehearsal.json').write_text(json.dumps(report,indent=2),encoding='utf8')
    print(json.dumps({'checks':len(checks),'passed':True}))

if __name__=='__main__':main()
