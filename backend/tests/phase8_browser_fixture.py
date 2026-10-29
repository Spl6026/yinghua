"""[E-29] Named yinghua_test-only UI fixtures. Cleanup preserves normal audit/account references."""
import argparse,json,re,secrets,subprocess,uuid
from datetime import datetime,timezone,timedelta
from api_integration import ROOT,fixture

manifest=ROOT/'artifacts/phase8-browser-fixture.json'
def literal(value):return 'CONVERT(0x'+str(value).encode('utf8').hex()+' USING utf8mb4)'
def php(code,input=b''):
    result=subprocess.run(['docker','compose','exec','-T','app-test','php','-r',code],cwd=ROOT,input=input,capture_output=True,check=True)
    return result.stdout
def publish():subprocess.run(['docker','compose','exec','-T','-u','www-data','app-test','php','bin/console.php','content:publish'],cwd=ROOT,capture_output=True,check=True)
def main(cleanup):
    if cleanup:
        data=json.loads(manifest.read_text(encoding='utf8'));prefix=data['prefix'];admin=data['adminId'];member=data['memberId']
        assert re.fullmatch(r'p8ui[a-f0-9]{8}',prefix)
        for id in [admin,member]:assert re.fullmatch(r'[a-f0-9-]{36}',id)
        current=fixture("SELECT updated_by FROM site_settings WHERE `key`='seo'")
        if current and current[0]['updated_by']==admin:
            fixture("DELETE FROM site_settings WHERE `key`='seo'")
            for row in data['settings']:
                columns=','.join('`'+key+'`' for key in row);values=','.join('NULL' if value is None else literal(value) for value in row.values())
                fixture('INSERT INTO site_settings('+columns+') VALUES('+values+')')
        fixture("DELETE FROM favorites WHERE member_id='"+member+"'")
        fixture("DELETE FROM itinerary_items WHERE itinerary_id IN (SELECT id FROM itineraries WHERE member_id='"+member+"')")
        fixture("DELETE FROM itineraries WHERE member_id='"+member+"'")
        places=fixture('SELECT id FROM attractions WHERE name LIKE '+literal(prefix+'%'))
        films=fixture('SELECT id FROM films WHERE title LIKE '+literal(prefix+'%'))
        for row in places:
            id=row['id'];fixture("DELETE FROM attraction_aliases WHERE attraction_id='"+id+"'")
            fixture("DELETE FROM merchants WHERE attraction_id='"+id+"'");fixture("DELETE FROM attractions WHERE id='"+id+"'")
        for row in films:
            id=row['id'];fixture("DELETE FROM film_screenings WHERE film_id='"+id+"'");fixture("DELETE FROM film_aliases WHERE film_id='"+id+"'");fixture("DELETE FROM films WHERE id='"+id+"'")
        keys=[row['storage_key'] for row in fixture("SELECT storage_key FROM media_assets WHERE uploaded_by='"+admin+"'")]
        fixture("DELETE FROM media_assets WHERE uploaded_by='"+admin+"'")
        php('$keys=json_decode(stream_get_contents(STDIN),true,flags:JSON_THROW_ON_ERROR);foreach($keys as $key){if(!preg_match("/^[a-f0-9-]{36}\\.(png|jpg|webp)$/D",$key)){throw new RuntimeException("Invalid owned key");} $p=getenv("MEDIA_PATH")."/".$key;if(is_file($p)){unlink($p);}}',json.dumps(keys).encode())
        fixture("UPDATE sessions SET revoked_at=UTC_TIMESTAMP() WHERE admin_id='"+admin+"' OR member_id='"+member+"'")
        fixture("UPDATE admin_users SET status='disabled',deleted_at=UTC_TIMESTAMP() WHERE id='"+admin+"' AND account="+literal(prefix))
        fixture("UPDATE members SET status='disabled',deleted_at=UTC_TIMESTAMP() WHERE id='"+member+"' AND email="+literal(prefix+'@example.test'))
        publish();data['cleaned']=True;manifest.write_text(json.dumps(data,ensure_ascii=False,indent=2),encoding='utf8');print('Owned UI fixtures cleaned; normal audit retained.');return
    if manifest.exists() and not json.loads(manifest.read_text(encoding='utf8')).get('cleaned'):raise RuntimeError('Clean previous owned UI fixture before creating another')
    prefix='p8ui'+secrets.token_hex(4);password='UI-only-'+secrets.token_urlsafe(24);admin=str(uuid.uuid4());member=str(uuid.uuid4());place=str(uuid.uuid4());film=str(uuid.uuid4())
    actor=fixture('SELECT id FROM admin_users WHERE is_root=1')[0]['id']
    hashed=php('require "vendor/autoload.php";echo (new Yinghua\\Services\\PasswordHasher())->hash(stream_get_contents(STDIN));',password.encode()).decode()
    data=dict(prefix=prefix,password=password,adminId=admin,memberId=member,placeId=place,filmId=film,cleaned=False,settings=fixture('SELECT * FROM site_settings'))
    manifest.write_text(json.dumps(data,ensure_ascii=False,indent=2),encoding='utf8')
    fixture("INSERT INTO admin_users(id,account,email,display_name,password_hash,role_id,status,must_change_password) VALUES('"+admin+"',"+literal(prefix)+','+literal(prefix+'@example.test')+","+literal('隔離驗收管理員')+','+literal(hashed)+",'viewer','active',0)")
    fixture("INSERT INTO admin_role_permissions(admin_id,permission_id,allowed,updated_by) SELECT '"+admin+"',id,1,'"+actor+"' FROM permissions")
    fixture("INSERT INTO members(id,email,display_name,password_hash,email_verified_at) VALUES('"+member+"',"+literal(prefix+'@example.test')+','+literal('隔離驗收會員')+','+literal(hashed)+',UTC_TIMESTAMP())')
    fixture("INSERT INTO attractions(id,slug,name,category,summary,description,address,status,publish_at) VALUES('"+place+"',"+literal(prefix+'-place')+','+literal(prefix+' 景點驗收')+",'景點','測試摘要','測試介紹','新竹市','published','2020-01-01')")
    sample=json.loads((ROOT/'backend/resources/films-demo.json').read_text(encoding='utf8'))[0]
    fixture("INSERT INTO films(id,slug,title,genre,synopsis,poster_url,poster_alt,status,publish_at,created_by,updated_by) VALUES('"+film+"',"+literal(prefix+'-film')+','+literal(prefix+' 電影驗收')+','+literal(sample['genre'])+','+literal(sample['synopsis'])+','+literal(sample['posterUrl'])+",'隔離海報','published','2020-01-01','"+actor+"','"+actor+"')")
    # 只給獨立驗收電影當週合成場次，未替原始無年份的電影推定年份。
    local=datetime.now(timezone(timedelta(hours=8))).replace(hour=12,minute=0,second=0,microsecond=0)
    for offset in [0,6]:
        starts=(local+timedelta(hours=offset)).astimezone(timezone.utc).strftime('%Y-%m-%d %H:%M:%S')
        fixture("INSERT INTO film_screenings(id,film_id,starts_at) VALUES('"+str(uuid.uuid4())+"','"+film+"','"+starts+"')")
    image=php('$im=imagecreatetruecolor(320,240);imagefill($im,0,0,imagecolorallocate($im,70,100,80));imagepng($im);')
    (ROOT/'artifacts/phase8-ui-image.png').write_bytes(image)
    publish();print(json.dumps({'created':True,'prefix':prefix,'destination':'yinghua_test / localhost:8081'}))

if __name__=='__main__':
    parser=argparse.ArgumentParser();parser.add_argument('--cleanup',action='store_true');options=parser.parse_args();main(options.cleanup)
