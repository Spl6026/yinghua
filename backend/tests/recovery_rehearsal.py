"""[E-29] Real dump/media recovery, rejection and authorization revocation on new volumes."""
import argparse, copy, hashlib, json, secrets, shutil, sys, tarfile, time, uuid
from pathlib import Path
from production_rehearsal import Client, check, checks, sql, await_status, ROOT
sys.path.insert(0,str(ROOT/'scripts/operations'))
from operations import Deployment, OperationError, verify_backup, hash_file

def refused(name,operation):
    try:operation()
    except OperationError:check(name,True);return
    raise AssertionError(name)

def make_target(source,project,port):
    path=ROOT/('.env.'+project)
    if path.exists():raise RuntimeError('New owned target configuration required')
    text=Path(source).read_text(encoding='utf8')
    values={}
    for line in text.splitlines():
        if '=' in line and not line.startswith('#'):
            key,value=line.split('=',1);values[key]=value
    values.update(PRODUCTION_PROJECT=project,DB_DATABASE=project.replace('-','_'),APP_URL='https://p0.invalid:'+str(port),HTTPS_PORT=str(port),HTTP_REDIRECT_PORT=str(port+1000),
                  RELEASE_APP_IMAGE='spl-p0-app:'+project,RELEASE_WEB_IMAGE='spl-p0-web:'+project)
    path.write_text('\n'.join(key+'='+value for key,value in values.items())+'\n',encoding='utf8')
    d=Deployment(path,project);d.preflight()
    # 不同還原 origin 必須重建同一來源的 app/web，避免固定頁 canonical／條款仍是舊站設定。
    d.run(['build','app','web']);d.run(['up','-d','--no-build','db','app'])
    return d

def data_snapshot(d):
    tables=sql(d,'SELECT table_name FROM information_schema.tables WHERE table_schema=DATABASE() ORDER BY table_name').splitlines()
    excluded={'sessions','password_reset_tokens','captcha_challenges','content_publications'}
    result={}
    for table in tables:
        if table in excluded:continue
        # [E-38] Search is regenerated with a fresh publication generation on restore.
        # Compare every public field, not the expected-to-change generation identifier.
        fields='resource_type,resource_id,type_order,title,summary,search_text,slug,url,thumbnail,thumbnail_alt' if table=='search_documents' else '*'
        result[table]=hashlib.sha256(sql(d,'SELECT '+fields+' FROM `'+table+'` ORDER BY 1,2').encode()).hexdigest()
    return result


def extended_delivery(source,root,prefix,member,published,second):
    """[E-38] Later modules must contain real data before the backup is taken."""
    body=root.call('GET','/api/v1/admin/settings')[1]
    settings={key:body[key] for key in ['version','seoVersion','siteName','description','values']}
    settings['values'].update(contactEmail='delivery@example.test',termsVersion='delivery-v1',privacyVersion='delivery-v1',termsText='Delivery recovery terms',privacyText='Delivery recovery privacy')
    check('delivery general settings published',root.call('PUT','/api/v1/admin/settings',settings)[0]==200)
    code,route,_=root.call('POST','/api/v1/admin/routes',{'title':prefix+' route','status':'published','stops':[{'attractionId':second,'suggestedTime':'10:30','note':'second first'},{'attractionId':published,'suggestedTime':'11:30','note':'first second'}]})
    check('delivery ordered route created',code==201)
    other=str(uuid.uuid4())
    sql(source,"INSERT INTO itineraries(id,member_id,title,version,current_slot) VALUES('"+other+"','"+member+"','Second saved trip',7,NULL)")
    sql(source,"INSERT INTO itinerary_items(id,itinerary_id,attraction_id,sort_order,planned_time,note,snapshot_name) VALUES('"+str(uuid.uuid4())+"','"+other+"','"+published+"',1,'13:45:00','Second trip note','Recovery place')")
    source.run(['exec','-T','-u','www-data','app','php','bin/console.php','content:publish'])
    check('delivery searchable projection populated',int(sql(source,'SELECT COUNT(*) FROM search_documents').strip())>=2)
    return route['record']['id']

def main():
    parser=argparse.ArgumentParser();parser.add_argument('--env-file',required=True);parser.add_argument('--project',required=True);parser.add_argument('--extended-delivery',action='store_true');options=parser.parse_args()
    if not options.project.startswith('spl-p0-'):raise RuntimeError('Owned rehearsal only')
    source=Deployment(options.env_file,options.project);source.preflight();source.readiness(True)
    origin=source.config['services']['app']['environment']['APP_URL']
    tls=next(Path(mount['source']) for mount in source.config['services']['web']['volumes'] if mount['target']=='/etc/nginx/tls')
    credential=json.loads((ROOT/'artifacts/phase8-rehearsal-credentials.json').read_text(encoding='utf8'));password=credential['password']
    root=Client(origin,tls/'fullchain.pem');root.token=root.call('GET','/api/v1/admin/auth/csrf')[1]['token']
    check('source root login',root.call('POST','/api/v1/admin/auth/login',{'account':'admin','password':password})[0]==200)
    actor=sql(source,'SELECT id FROM admin_users WHERE is_root=1').strip()
    prefix='p0-restore-'+secrets.token_hex(4);email=prefix+'@example.test'
    published=str(uuid.uuid4());second=str(uuid.uuid4());archived=str(uuid.uuid4());member=str(uuid.uuid4());trip=str(uuid.uuid4());media=str(uuid.uuid4())
    # 真 GD 圖檔，fixture 直接寫自有隔離 media volume；上傳安全由既有 HTTP 套件另驗。
    image=source.run(['exec','-T','app','php','-r','$im=imagecreatetruecolor(8,8);imagepng($im);']).stdout
    key=media+'.png';source.run(['exec','-T','-u','www-data','app','sh','-c','cat > /var/www/media/'+key],input=image)
    sql(source,"INSERT INTO media_assets(id,storage_key,mime_type,width,height,byte_size,alt,module,uploaded_by) VALUES('"+media+"','"+key+"','image/png',8,8,"+str(len(image))+",'Recovery image','attractions','"+actor+"')")
    for id,slug,status in [(published,prefix+'-place','published'),(second,prefix+'-second','published'),(archived,prefix+'-archived','archived')]:
        sql(source,"INSERT INTO attractions(id,slug,name,category,summary,description,address,status,publish_at,media_id) VALUES('"+id+"','"+slug+"','Recovery place','景點','Summary','Description','','"+status+"','2020-01-01','"+media+"')")
    sql(source,"INSERT INTO attraction_aliases(path,attraction_id) VALUES('/places/"+prefix+"-old.html','"+published+"'),('/places/"+prefix+"-archived-old.html','"+archived+"')")
    sql(source,"INSERT INTO members(id,email,display_name,password_hash,email_verified_at) SELECT '"+member+"','"+email+"','Recovery member',password_hash,UTC_TIMESTAMP() FROM admin_users WHERE is_root=1")
    sql(source,"INSERT INTO favorites(member_id,attraction_id) VALUES('"+member+"','"+published+"')")
    sql(source,"INSERT INTO itineraries(id,member_id,title,version,current_slot) VALUES('"+trip+"','"+member+"','Recovery trip',42,1)")
    for order,id in enumerate([second,published],1):
        sql(source,"INSERT INTO itinerary_items(id,itinerary_id,attraction_id,sort_order,planned_time,note,snapshot_name) VALUES('"+str(uuid.uuid4())+"','"+trip+"','"+id+"',"+str(order)+",'09:30:00','Recovery note','Recovery place')")
    reset=str(uuid.uuid4());challenge=str(uuid.uuid4())
    sql(source,"INSERT INTO password_reset_tokens(id,member_id,token_hash,purpose,expires_at) VALUES('"+reset+"','"+member+"','"+secrets.token_hex(32)+"','member-reset',UTC_TIMESTAMP()+INTERVAL 1 HOUR)")
    sql(source,"INSERT INTO captcha_challenges(id,token_hash,purpose,mode,answer_hmac,session_binding_hash,max_attempts,expires_at) VALUES('"+challenge+"','"+secrets.token_hex(32)+"','register','email',REPEAT('d',64),REPEAT('e',64),3,UTC_TIMESTAMP()+INTERVAL 1 HOUR)")
    body=root.call('GET','/api/v1/admin/settings/seo')[1]
    doc={name:copy.deepcopy(body[name]) for name in ['version','defaults','overrides']};doc['defaults']['siteName']='Recovery rehearsal'
    check('real settings write and audit',root.call('PUT','/api/v1/admin/settings/seo',doc)[0]==200)
    admin='restore'+secrets.token_hex(4)
    code,created,_=root.call('POST','/api/v1/admin/admins',{'account':admin,'displayName':'Recovery admin','email':admin+'@example.test','password':password,'roleId':'viewer','status':'active'})
    check('real permission-bearing account fixture',code==201)
    sql(source,"UPDATE admin_role_permissions SET allowed=1 WHERE admin_id='"+created['admin']['id']+"' AND permission_id IN (SELECT id FROM permissions WHERE module_key='attractions' AND action_key='create')")
    member_client=Client(origin,tls/'fullchain.pem');member_client.token=member_client.call('GET','/api/v1/auth/csrf')[1]['token']
    check('source member login',member_client.call('POST','/api/v1/auth/login',{'email':email,'password':password})[0]==200)
    source.run(['exec','-T','-u','www-data','app','php','bin/console.php','content:publish'])
    route_id=extended_delivery(source,root,prefix,member,published,second) if options.extended_delivery else None
    before=data_snapshot(source);source_media=source.media()
    backup=ROOT/('artifacts/phase8-backup-'+secrets.token_hex(4));backup_report=source.backup(backup)
    check('database/media/release backup verifies',verify_backup(backup)[0]['media']==source_media)
    target=make_target(options.env_file,'spl-p0-restore-'+secrets.token_hex(3),18444)
    report={'checks':checks,'backup':backup_report,'target':target.project}
    try:
        corrupted=backup.with_name(backup.name+'-corrupt');shutil.copytree(backup,corrupted)
        with (corrupted/'database.sql').open('ab') as stream:stream.write(b'\nCORRUPTED\n')
        refused('corrupt dump refused',lambda:target.restore(corrupted))
        missing=backup.with_name(backup.name+'-missing');shutil.copytree(backup,missing)
        with tarfile.open(missing/'media.tar','w'):pass
        metadata=json.loads((missing/'manifest.json').read_text(encoding='utf8'));metadata['files']['media.tar']=hash_file(missing/'media.tar');(missing/'manifest.json').write_text(json.dumps(metadata),encoding='utf8')
        refused('missing media refused despite outer hash update',lambda:target.restore(missing))
        incompatible=backup.with_name(backup.name+'-incompatible');shutil.copytree(backup,incompatible)
        release=json.loads((incompatible/'release.json').read_text(encoding='utf8'));release['migrations'][next(iter(release['migrations']))]='0'*64;(incompatible/'release.json').write_text(json.dumps(release),encoding='utf8')
        metadata=json.loads((incompatible/'manifest.json').read_text(encoding='utf8'));metadata['files']['release.json']=hash_file(incompatible/'release.json');(incompatible/'manifest.json').write_text(json.dumps(metadata),encoding='utf8')
        refused('incompatible schema refused',lambda:target.restore(incompatible))
        check('refusals do not write target schema/open traffic',sql(target,'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()').strip()=='0' and not target.running('web'))
        restored=target.restore(backup);report['restore']=restored
        check('all business tables byte-for-byte row snapshots retained',data_snapshot(target)==before)
        check('media exact hash retained',target.media()==source_media)
        for table in ['sessions','password_reset_tokens','captcha_challenges']:
            check('restored '+table+' authorization revoked',sql(target,'SELECT COUNT(*) FROM '+table+' WHERE revoked_at IS NULL').strip()=='0')
        check('restore does not open traffic',not target.running('web') and not target.running('publisher'))
        refused('nonempty restore target protected',lambda:target.restore(backup))
        target.open();restored_client=Client(target.config['services']['app']['environment']['APP_URL'],tls/'fullchain.pem')
        await_status(restored_client,'/api/v1/health',200)
        for client,path in [(root,'/api/v1/admin/auth/me'),(member_client,'/api/v1/auth/me')]:
            client.origin=restored_client.origin
            check('old actual session cookie rejected '+path,client.call('GET',path)[0]==401)
        check('archived detail and old alias stay404',restored_client.call('GET','/places/'+prefix+'-archived.html')[0]==404 and restored_client.call('GET','/places/'+prefix+'-archived-old.html')[0]==404)
        check('restored media served anonymously',restored_client.call('HEAD','/media/'+key)[0]==200)
        refused('root trigger protection survives logical restore',lambda:sql(target,"UPDATE admin_users SET account='must-fail' WHERE is_root=1"))
        root.token=root.call('GET','/api/v1/admin/auth/csrf')[1]['token']
        check('retained password still logs in after revocation',root.call('POST','/api/v1/admin/auth/login',{'account':'admin','password':password})[0]==200)
        if options.extended_delivery:
            check('delivery general policy and contact restored',restored_client.call('GET','/api/v1/site-settings/public')[1]['general']['contactEmail']=='delivery@example.test')
            check('delivery ordered route restored',any(row['id']==route_id and len(row['stops'])==2 for row in restored_client.call('GET','/api/v1/routes')[1]['items']))
            check('delivery multiple trips retained',sql(target,"SELECT COUNT(*) FROM itineraries WHERE member_id='"+member+"'").strip()=='2')
            check('delivery search rebuilt and serves published content',restored_client.call('GET','/api/v1/search?q=Recovery')[1]['total']>=2)
        failed=make_target(options.env_file,'spl-p0-pubfail-'+secrets.token_hex(3),18445);report['publicationFailureTarget']=failed.project
        failed.run(['exec','-T','app','chmod','0555','/var/www/publication'])
        try:
            refused('publication failure during restore keeps traffic closed',lambda:failed.restore(backup))
            check('failed restore has revoked authorization and closed web',sql(failed,'SELECT COUNT(*) FROM sessions WHERE revoked_at IS NULL').strip()=='0' and not failed.running('web'))
        finally:failed.run(['exec','-T','app','chmod','0755','/var/www/publication'])
        failed.run(['exec','-T','-u','www-data','app','php','bin/console.php','content:publish']);failed.open()
        check('publication repaired without reimporting nonempty data',failed.readiness(True)['status']=='ok')
        report['passed']=True
    finally:(ROOT/'artifacts/phase8-recovery-rehearsal.json').write_text(json.dumps(report,indent=2),encoding='utf8')
    print(json.dumps({'checks':len(checks),'passed':True}))

if __name__=='__main__':main()
