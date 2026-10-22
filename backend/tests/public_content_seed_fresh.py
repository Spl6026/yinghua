"""[E-26] 全新專用隨機 schema 的初始化順序、原型店家與不覆寫；最後刪除自有 schema。"""
import json,re,secrets
from public_content_schema_upgrade import ROOT,root_sql,php

def main():
    suffix=secrets.token_hex(5);name='yinghua_p5_fresh_'+suffix
    user=next(line.split('=',1)[1].strip() for line in (ROOT/'.env').read_text('utf8').splitlines() if line.startswith('DB_USERNAME='))
    assert re.fullmatch(r'[A-Za-z][A-Za-z0-9_]*',user)
    root_sql(f'CREATE DATABASE `{name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;')
    try:
        root_sql(f"GRANT ALL ON `{name}`.* TO '{user}'@'%';")
        code=r"""
        (new Yinghua\Adapters\SchemaMigrator($db))->migrate('/var/www/app/database/migrations');
        (new Yinghua\Adapters\IdentitySeeder($db))->seed('/var/www/app/resources/role-templates.json');
        $accounts=new Yinghua\Repositories\AccountRepository($db);
        $root=(new Yinghua\Services\RootInitializer($accounts,new Yinghua\Services\PasswordHasher(),new Yinghua\Adapters\PdoTransactions($db),new Yinghua\Repositories\SessionRepository($db,$config,new Yinghua\Adapters\SystemClock()),new Yinghua\Repositories\ResetRepository($db)))->initialize('fresh@example.test',bin2hex(random_bytes(24)));
        (new Yinghua\Adapters\AttractionSeeder($db,new Yinghua\Repositories\AttractionRepository($db),new Yinghua\Adapters\PdoTransactions($db)))->seed('/var/www/app/resources/attractions-demo.json');
        $directory='/tmp/spl-phase5-seed-REPLACE_SUFFIX';mkdir($directory,0700);putenv('PUBLICATION_PATH='.$directory);
        $checks=0;$check=static function(bool $ok) use (&$checks):void {if(!$ok)throw new RuntimeException('Fresh seed assertion failed at '.($checks+1));$checks++;};
        try {
            $composition=new Yinghua\Composition($config);$commands=$composition->publicContentCommands($db);
            $first=$commands->run(['console.php','content:seed-public-demo']);
            $check($first===0);
            $merchantCount=count($db->rows("SELECT id FROM attractions WHERE category<>'景點'"));
            $check($merchantCount>0&&count($db->rows('SELECT id FROM merchants'))===$merchantCount);
            $check(count($db->rows("SELECT id FROM merchants WHERE status='active' AND attraction_id IS NOT NULL"))===$merchantCount);
            $check(count($composition->publication($db)->read()['offers'])===3);
            $check(count($composition->publication($db)->read()['news'])===4);
            $check(count($db->rows('SELECT id FROM offers WHERE legacy_id BETWEEN 101 AND 103'))===3);
            $db->execute("UPDATE news_posts SET title='管理員編寫內容',version=version+1 WHERE legacy_id=0");
            $db->execute("UPDATE merchants SET status='inactive',name='已停用店家',version=version+1 WHERE id=(SELECT merchant_id FROM offers WHERE legacy_id=101)");
            $second=$commands->run(['console.php','content:seed-public-demo']);$check($second===0);
            $check(($db->row('SELECT title FROM news_posts WHERE legacy_id=0')['title']??'')==='管理員編寫內容');
            $check(count($db->rows("SELECT id FROM merchants WHERE status='inactive' AND name='已停用店家'"))===1);
            $check(count($composition->publication($db)->read()['offers'])===2);
            echo json_encode(['freshDemoChecks'=>$checks,'merchantProfilesReused'=>true,'editedContentPreserved'=>true,'disabledMerchantPreserved'=>true,'temporarySchemaRemoved'=>true]);
        } finally {
            $remove=static function(string $path) use (&$remove,$directory):void {
                if($path!==$directory&&!str_starts_with($path,$directory.'/'))throw new RuntimeException('Cleanup boundary.');
                if(is_link($path)||is_file($path)){unlink($path);return;}
                foreach(new DirectoryIterator($path) as $entry)if(!$entry->isDot())$remove($entry->getPathname());
                rmdir($path);
            };$remove($directory);
        }
        """.replace('REPLACE_SUFFIX',suffix)
        # PublicContentCommands 使用 fwrite(STDOUT)，不能用 output buffering 攔截；取最後一個 JSON 物件。
        output=php(name,code)
        result=json.loads(output[output.rfind('{"freshDemoChecks"'):])
    finally:
        root_sql(f"REVOKE ALL ON `{name}`.* FROM '{user}'@'%';")
        root_sql(f'DROP DATABASE `{name}`;')
    (ROOT/'artifacts/phase5-fresh-seed-results.json').write_text(json.dumps(result,indent=2),encoding='utf8')
    print(json.dumps(result))

if __name__=='__main__':main()
