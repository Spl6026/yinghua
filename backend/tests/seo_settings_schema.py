"""只在新建立的隔離 schema 中，驗證 SEO 設定 migration 在空資料庫與既有資料上的升級。"""
import json,re,secrets,subprocess
from pathlib import Path

ROOT=Path(__file__).resolve().parents[2]
def run(args,source=None):
    result=subprocess.run(args,input=source,capture_output=True,text=True,encoding='utf8',cwd=ROOT)
    if result.returncode:raise AssertionError(result.stdout+'\n'+result.stderr)
    return result.stdout
def root_sql(sql):
    return run(['docker','compose','exec','-T','db','sh','-c','MYSQL_PWD="$MARIADB_ROOT_PASSWORD" mariadb -uroot --batch --skip-column-names'],sql)
def php(name,code):
    assert re.fullmatch('yinghua_p7_[a-f0-9]{10}_(fresh|upgrade)',name)
    return run(['docker','compose','exec','-T','-e','DB_DATABASE='+name,'app-test','php'],"<?php require 'vendor/autoload.php'; $config=new Yinghua\\Config(); if($config->string('APP_ENV')!=='test')throw new RuntimeException('Test required'); $db=Yinghua\\Repositories\\Database::connect($config); "+code)
def main():
    suffix=secrets.token_hex(5)
    user=next(line.split('=',1)[1].strip() for line in (ROOT/'.env').read_text(encoding='utf8').splitlines() if line.startswith('DB_USERNAME='))
    assert re.fullmatch('[A-Za-z][A-Za-z0-9_]*',user)
    created=[];results={}
    try:
        for mode in ('fresh','upgrade'):
            name='yinghua_p7_'+suffix+'_'+mode
            root_sql(f'CREATE DATABASE `{name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;');created.append(name)
            root_sql(f"GRANT ALL ON `{name}`.* TO '{user}'@'%';")
            if mode=='upgrade':
                php(name,r"""
                $directory='/tmp/spl-seo-before-REPLACE_SUFFIX';mkdir($directory,0700);
                try {
                    foreach(glob('/var/www/app/database/migrations/*.sql')?:[] as $file)if(basename($file)<'017')copy($file,$directory.'/'.basename($file));
                    (new Yinghua\Adapters\SchemaMigrator($db))->migrate($directory);
                } finally {
                    foreach(glob($directory.'/*.sql')?:[] as $file)unlink($file);
                    rmdir($directory);
                }
                $db->execute("INSERT INTO members(id,email,display_name,password_hash) VALUES('06000000-0000-4000-8000-000000000001','before@example.test','升級前會員','hash-fixture')");
                $db->execute("INSERT INTO attractions(id,slug,name,category,summary,description,address,publish_at) VALUES('06000000-0000-4000-8000-000000000002','p6-before','升級前景點','景點','','','',UTC_TIMESTAMP())");
                $db->execute("INSERT INTO favorites(member_id,attraction_id) VALUES('06000000-0000-4000-8000-000000000001','06000000-0000-4000-8000-000000000002')");
                $db->execute("INSERT INTO itineraries(id,member_id,current_slot) VALUES('07000000-0000-4000-8000-000000000003','06000000-0000-4000-8000-000000000001',1)");
                $db->execute("INSERT INTO itinerary_items(id,itinerary_id,attraction_id,sort_order,snapshot_name) VALUES('07000000-0000-4000-8000-000000000004','07000000-0000-4000-8000-000000000003','06000000-0000-4000-8000-000000000002',1,'Before SEO')");
                """.replace('REPLACE_SUFFIX',suffix))
            for _ in range(2):php(name,"(new Yinghua\\Adapters\\SchemaMigrator($db))->migrate('/var/www/app/database/migrations');")
            result=json.loads(php(name,r"""
            $tables=(int)$db->row('SELECT COUNT(*) AS n FROM information_schema.tables WHERE table_schema=DATABASE()')['n'];
            $migrations=(int)$db->row('SELECT COUNT(*) AS n FROM schema_migrations')['n'];
            if($tables!==33||$migrations!==count(glob('/var/www/app/database/migrations/*.sql')))throw new RuntimeException('Schema counts');
            echo json_encode(['tables'=>$tables,'migrations'=>$migrations,'members'=>(int)$db->row('SELECT COUNT(*) AS n FROM members')['n'],'attractions'=>(int)$db->row('SELECT COUNT(*) AS n FROM attractions')['n'],'favorites'=>(int)$db->row('SELECT COUNT(*) AS n FROM favorites')['n'],'stops'=>(int)$db->row('SELECT COUNT(*) AS n FROM itinerary_items')['n']]);
            """))
            assert result['members']==result['attractions']==result['favorites']==result['stops']==(1 if mode=='upgrade' else 0)
            results[mode]=result
    finally:
        for name in created:
            root_sql(f"REVOKE ALL ON `{name}`.* FROM '{user}'@'%';")
            root_sql(f'DROP DATABASE `{name}`;')
    print(json.dumps({'seoSchema':results,'temporarySchemasRemoved':True}))
if __name__=='__main__':main()
