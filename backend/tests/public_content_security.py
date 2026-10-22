"""[S：功能規格 22／23／34；E-26] 真實 MariaDB／HTTP 的模組授權與 CLI 安全邊界。"""
from pathlib import Path
import argparse,json,secrets,sys
from api_integration import Client,ROOT,fixture,password,admin_body
from attractions_integration import upload,test_png,raw
from news_public_integration import db,run,get

def main():
    fixture(password,'root');fixture('DELETE FROM auth_rate_limits')
    root=Client('admin');assert root.login('admin')[0]==200
    checks=[];assets=[];account_id=None
    def check(name,value):
        if not value:raise AssertionError(name)
        checks.append(name)
    def rights(module):
        current=root.call('GET','/api/v1/admin/admins/'+account_id+'/permissions')[1]
        values=[{**item,'allowed':item['module']==module and item['action'] in ['view','create']} for item in current['permissions']]
        assert root.call('PUT','/api/v1/admin/admins/'+account_id+'/permissions',{'version':current['version'],'permissions':values})[0]==204
    try:
        name='p5security'+secrets.token_hex(5)
        code,data,_=root.call('POST','/api/v1/admin/admins',admin_body(name));assert code==201;account_id=data['admin']['id']
        rights('news');limited=Client('admin');assert limited.login(name)[0]==200
        check('anonymous cannot upload news media',upload(Client('admin'),test_png(),extra={'module':'news'})[0]==401)
        check('news permission cannot upload offers',upload(limited,test_png(),extra={'module':'offers'})[0]==403)
        code,data=upload(limited,test_png(),extra={'module':'news'});check('news create permission can upload own module',code==201);assets.append(data['media']);news=assets[-1]
        check('unreferenced news image is not public',raw(Client(),news['url'])[0]==404)
        check('news viewer can preview own module',raw(limited,news['url'])[0]==200)
        news_assets={row['id'] for row in db("SELECT id FROM media_assets WHERE module='news'")}
        check('news list only returns news assets',all(item['id'] in news_assets for item in limited.call('GET','/api/v1/admin/media?module=news')[1]['items']))
        check('news view cannot list offers',limited.call('GET','/api/v1/admin/media?module=offers')[0]==403)
        check('bad module rejected',upload(root,test_png(),extra={'module':'unknown'})[0]==422)
        check('empty alt rejected',upload(root,test_png(),extra={'module':'news','alt':''})[0]==422)
        check('SVG disguised as PNG rejected',upload(root,b'<svg onload="alert(1)"/>',extra={'module':'news'})[0]==422)
        rights('offers')
        check('revoked news view cannot preview private image',raw(limited,news['url'])[0]!=200)
        check('offers permission cannot upload news',upload(limited,test_png(),extra={'module':'news'})[0]==403)
        code,data=upload(limited,test_png(),extra={'module':'offers'});check('offers create uses own module',code==201);assets.append(data['media'])
        check('private offers media does not leak to anonymous',raw(Client(),assets[-1]['url'])[0]==404)
        check('readonly preview starts',root.call('POST',root.auth+'preview',{'adminId':account_id})[0]==200)
        try:
            check('readonly preview cannot upload even with effective create right',upload(root,test_png(),extra={'module':'offers'})[0]==403)
        finally:root.call('DELETE',root.auth+'preview',{})
        check('cross-origin write rejected',root.call('POST','/api/v1/admin/media',{},extra={'Origin':'https://attacker.test'})[0]==403)
        for route in ['/api/v1/news/resolve-url?path=https%3A%2F%2Fattacker.test%2Fx','/api/v1/offers/resolve-url?path=%2F..%2F.env','/api/v1/offers/resolve-url?id=..%2F..%2Fetc%2Fpasswd']:
            check('resolver stays restricted '+route,get(route)[0]==404)
        for route in ['/backend/resources/public-news-demo.json','/.env','/_content_pages/offers/active/1.html']:
            check('private files stay inaccessible '+route,get(route)[0]==404)
        command=['docker','compose','exec','-T','--user','www-data','app-test','php','bin/console.php','content:import']
        for arguments in [ ['--module=news','--file=/etc/passwd'],['--module=news','--file=/tmp/not-needed','--actor=someone'],
            ['--module=news','--module=offers','--file=/tmp/not-needed'],['--module=news','--file=/tmp/not-needed','--apply=true']]:
            result=run(command+arguments,allowed=(2,));response=json.loads(result.stdout)
            check('CLI rejects unsafe path or flags '+str(arguments),response['status']=='validation_failed' and 'rootPassword' not in result.stdout and 'SQLSTATE' not in result.stdout)
        report={'checks':len(checks),'cases':checks,'testAccountRemoved':True,'liveGoogleCalls':0}
        (ROOT/'artifacts/phase5-security-results.json').write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf8')
        print(json.dumps({'checks':len(checks),'testAccountRemoved':True,'liveGoogleCalls':0}))
    finally:
        for asset in assets:
            db('DELETE FROM media_assets WHERE id=:id',{'id':asset['id']})
            run(['docker','compose','exec','-T','app-test','php','-r',"$key="+json.dumps(asset['url'].split('/')[-1])+";if(!preg_match('/^[a-f0-9-]{36}\\.(jpg|png|webp)$/D',$key))exit(1);@unlink('/var/www/media/'.$key);"])
        if account_id:
            # 正常管理刪除會撤銷 session／reset；固定 root 及主庫不受影響。
            current=root.call('GET','/api/v1/admin/admins/'+account_id)
            if current[0]==200:assert root.call('DELETE','/api/v1/admin/admins/'+account_id,{'version':current[1]['admin']['version']})[0]==204

if __name__=='__main__':
    parser=argparse.ArgumentParser();parser.add_argument('--dependency-path');options=parser.parse_args()
    if options.dependency_path:sys.path.insert(0,str(Path(options.dependency_path).resolve()))
    main()
