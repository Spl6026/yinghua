"""驗證公開內容 migration 在空資料庫與既有資料上的升級；只建立與清除本程式新建的隨機測試 schema。"""
from pathlib import Path
import json,re,secrets,subprocess

ROOT=Path(__file__).resolve().parents[2]
def run(args,input=None):
    result=subprocess.run(args,cwd=ROOT,input=input,text=True,encoding='utf-8',capture_output=True)
    if result.returncode:raise AssertionError('Schema verification failed: '+result.stderr)
    return result.stdout
def root_sql(sql):
    return run(['docker','compose','exec','-T','db','sh','-c','MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb -uroot --batch --skip-column-names'],sql)
def php(name,body):
    assert re.fullmatch(r'yinghua_p5_(?:fresh|upgrade)_[a-f0-9]{10}',name)
    source=r"""<?php require 'vendor/autoload.php';
    $config=new Yinghua\Config();
    if ($config->string('APP_ENV')!=='test' || !preg_match('/^yinghua_p5_(fresh|upgrade)_[a-f0-9]{10}$/D',$config->string('DB_DATABASE'))) throw new RuntimeException('Isolated schema required.');
    $db=Yinghua\Repositories\Database::connect($config);
    """+body
    return run(['docker','compose','exec','-e','DB_DATABASE='+name,'-T','app-test','php'],source)

def main():
    user=next(line.split('=',1)[1].strip() for line in (ROOT/'.env').read_text('utf-8').splitlines() if line.startswith('DB_USERNAME='))
    assert re.fullmatch(r'[a-zA-Z][a-zA-Z0-9_]*',user)
    suffix=secrets.token_hex(5);created=[];reports={}
    directory=ROOT/'backend/.build'/('phase5-before-schema-'+suffix);directory.mkdir(parents=True)
    for path in (ROOT/'backend/database/migrations').glob('*.sql'):
        if path.name<'012':(directory/path.name).write_bytes(path.read_bytes())
    try:
        for mode in ['fresh','upgrade']:
            name='yinghua_p5_'+mode+'_'+suffix
            # CREATE 不使用 IF NOT EXISTS；若意外同名即停止，不動既有 schema。
            root_sql(f"CREATE DATABASE `{name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;")
            created.append(name)
            root_sql(f"GRANT ALL ON `{name}`.* TO '{user}'@'%';")
            if mode=='upgrade':
                php(name,f"(new Yinghua\\Adapters\\SchemaMigrator($db))->migrate('/var/www/app/.build/{directory.name}');"+r"""
                $db->execute("INSERT INTO members(id,email,display_name,password_hash) VALUES('04000000-0000-4000-8000-000000000001','schema@example.test','升級前會員',:hash)",['hash'=>password_hash('schema-fixture-only',PASSWORD_ARGON2ID)]);
                $db->execute("INSERT INTO attractions(id,slug,name,category,summary,description,address,publish_at) VALUES('04000000-0000-4000-8000-000000000002','schema-before','升級前景點','景點','原摘要','原介紹','',UTC_TIMESTAMP())");
                $db->execute("INSERT INTO attractions(id,slug,name,category,summary,description,address,phone,website_url,status,publish_at) VALUES('05000000-0000-4000-8000-000000000001','old-shop','升級前合作店家','在地店家','','','原地址','03-1234567','https://example.test','published','2020-01-01')");
                $db->execute("INSERT INTO merchants(attraction_id,created_at) VALUES('05000000-0000-4000-8000-000000000001','2020-01-02')");
                """)
            for _ in range(2):php(name,"(new Yinghua\\Adapters\\SchemaMigrator($db))->migrate('/var/www/app/database/migrations');")
            body=r"""
            $check=static function(bool $value):void {if(!$value)throw new RuntimeException('Schema assertion failed.');};
            // 以完整 migration 集合複驗原有電影／店家約束；表數由 migration 推算（另加 schema_migrations 紀錄表）。
            $files=glob('/var/www/app/database/migrations/*.sql');$expected=1+array_sum(array_map(static fn($f)=>preg_match_all('/CREATE TABLE/i',file_get_contents($f)),$files));
            $tables=$db->rows('SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE()');$check(count($tables)===$expected);
            $migrations=$db->rows('SELECT version FROM schema_migrations WHERE applied_at IS NOT NULL');$check(count($migrations)===count(glob('/var/www/app/database/migrations/*.sql')));
            $columns=$db->rows("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='film_screenings'");
            $check(!array_intersect(['ends_at','venue','status'],array_column($columns,'COLUMN_NAME')));
            $check(count($db->rows("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='merchants'"))===12);
            $id='04000000-0000-4000-8000-000000000003';
            $db->execute("INSERT INTO films(id,slug,title) VALUES(:id,'schema-minimum','僅片名')",['id'=>$id]);
            $db->execute("INSERT INTO film_screenings(id,film_id,starts_at) VALUES('04000000-0000-4000-8000-000000000004',:id,'2026-10-19 06:00:00')",['id'=>$id]);
            $failures=0;
            foreach (["INSERT INTO film_screenings(id,film_id,starts_at) VALUES('04000000-0000-4000-8000-000000000005','$id','2026-10-19 06:00:00')",
                "INSERT INTO films(id,slug,title,poster_media_id) VALUES('04000000-0000-4000-8000-000000000006','bad-poster','無效海報','04000000-0000-4000-8000-000000000099')",
                "INSERT INTO film_screenings(id,film_id,starts_at) VALUES('04000000-0000-4000-8000-000000000007','04000000-0000-4000-8000-000000000099','2026-10-19 06:00:00')"] as $sql) {
                try {$db->execute($sql);}catch(PDOException $error){$check($error->getCode()==='23000');$failures++;}
            }
            $check($failures===3);
            echo json_encode(['tables'=>count($tables),'migrations'=>count($migrations),'foreignAndUniqueFailures'=>$failures,'minimumFilmSaved'=>true,'deferredColumnsAbsent'=>true]);
            """
            reports[mode]=json.loads(php(name,body))
            if mode=='upgrade':
                result=php(name,r"""
                $member=$db->row("SELECT display_name,password_hash FROM members WHERE email='schema@example.test'");
                $place=$db->row("SELECT name,summary,description FROM attractions WHERE slug='schema-before'");
                if(($member['display_name']??'')!=='升級前會員'||!password_verify('schema-fixture-only',$member['password_hash']??'')||$place!==['name'=>'升級前景點','summary'=>'原摘要','description'=>'原介紹'])throw new RuntimeException('Prior data changed.');
                $merchant=$db->row("SELECT id,attraction_id,name,address,phone,website,status,created_at FROM merchants WHERE attraction_id='05000000-0000-4000-8000-000000000001'");
                if($merchant!==['id'=>'05000000-0000-4000-8000-000000000001','attraction_id'=>'05000000-0000-4000-8000-000000000001','name'=>'升級前合作店家','address'=>'原地址','phone'=>'03-1234567','website'=>'https://example.test','status'=>'active','created_at'=>'2020-01-02 00:00:00'])throw new RuntimeException('Merchant profile changed during upgrade.');
                echo 'preserved';
                """)
                assert result=='preserved';reports[mode]['priorMemberAttractionMerchantPreserved']=True
    finally:
        for name in created:
            assert re.fullmatch(r'yinghua_p5_(?:fresh|upgrade)_[a-f0-9]{10}',name)
            root_sql(f"REVOKE ALL ON `{name}`.* FROM '{user}'@'%';")
            root_sql(f"DROP DATABASE `{name}`;")
    (ROOT/'artifacts/phase5-schema-results.json').write_text(json.dumps(reports,ensure_ascii=False,indent=2),encoding='utf-8')
    print(json.dumps(reports,ensure_ascii=False))

if __name__=='__main__':main()
