"""以真實環境驗證電影 CRUD、海報上傳與各模組分開的權限；只使用 yinghua_test。"""
import concurrent.futures, copy, json, secrets, urllib.parse, uuid
from api_integration import Client, ROOT, fixture, password, admin_body
from attractions_integration import upload, test_png, raw, cli

def main():
    checks=[];created=[];prefix='film-admin-'+secrets.token_hex(5)
    def check(label,result):
        assert result,label
        checks.append(label)
    fixture(password,'root');fixture('DELETE FROM auth_rate_limits')
    root=Client('admin');guest=Client()
    check('root login',root.login('admin')[0]==200)
    def create(body,client=root):
        code,data,_=client.call('POST','/api/v1/admin/films',body)
        assert code==201,(code,data)
        created.append(data['film']['id']);return data['film']
    def update(film,**changes):
        body=dict(title=film['title'],version=film['version'],**changes)
        code,data,_=root.call('PUT','/api/v1/admin/films/'+film['id'],body)
        assert code==200,(code,data)
        return data['film']
    def restricted(label,rights):
        name='f4'+secrets.token_hex(5)
        code,data,_=root.call('POST','/api/v1/admin/admins',admin_body(name))
        assert code==201
        identifier=data['admin']['id'];path='/api/v1/admin/admins/'+identifier+'/permissions'
        matrix=root.call('GET',path)[1]
        for right in matrix['permissions']:right['allowed']=(right['module'],right['action']) in rights
        assert root.call('PUT',path,dict(version=matrix['version'],permissions=matrix['permissions']))[0]==204
        client=Client('admin');check(label+' login',client.login(name)[0]==200)
        return client,identifier
    try:
        check('anonymous list denied',guest.call('GET','/api/v1/admin/films')[0]==401)
        check('CSRF required',root.call('POST','/api/v1/admin/films',{'title':'test'},csrf=False)[0]==403)
        check('cross origin denied',root.call('POST','/api/v1/admin/films',{'title':'test'},extra={'Origin':'https://attacker.test'})[0]==403)
        for body in [{},{'title':' '},{'title':'ok','isRoot':True},{'title':'ok','ticketUrl':'javascript:alert(1)'},{'title':'ok','posterUrl':'file:///etc/passwd'},{'title':'ok','posterUrl':'https://example.test/a.png'},{'title':'ok','posterMediaId':str(uuid.uuid4())}]:
            check('invalid metadata '+str(len(checks)),root.call('POST','/api/v1/admin/films',body)[0]==422)
        film=create({'title':'電影 '+prefix})
        check('minimal draft has defaults',film['status']=='draft' and film['screenings']==[] and film['synopsis']=='' and film['posterMediaId'] is None)
        path='/api/v1/admin/films/'+film['id']
        check('draft private',guest.call('GET','/api/v1/films/'+film['slug'])[0]==404)
        film=update(film,status='published',slug=prefix,synopsis='<script>unsafe $1</script>')
        check('zero screenings may publish',guest.call('GET','/api/v1/films/'+prefix)[0]==200)
        check('HTML escaped',b'&lt;script&gt;unsafe $1&lt;/script&gt;' in raw(guest,film['url'])[1])
        check('stale update denied',root.call('PUT',path,{'title':'old','version':1})[0]==409)
        check('duplicate slug denied',root.call('POST','/api/v1/admin/films',{'title':'other','slug':prefix})[0]==409)
        film=update(film,slug=prefix+'-new')
        check('old slug resolves',raw(guest,'/films/'+prefix+'.html')[0]==200)
        check('old slug reserved',root.call('POST','/api/v1/admin/films',{'title':'other','slug':prefix})[0]==409)
        # 編輯電影資訊時沒有帶場次，不能因此刪掉資料庫裡既有的場次。
        screen_id=str(uuid.uuid4());fixture(f"INSERT INTO film_screenings(id,film_id,starts_at) VALUES('{screen_id}','{film['id']}','2032-01-02 06:00:00')")
        film=update(film,genre='紀錄片')
        check('omitted screenings preserved',film['screenings'][0]['id']==screen_id and len(film['screenings'])==1)
        code,data=upload(root,test_png(),extra={'module':'films','alt':'基本海報說明'})
        check('film poster upload',code==201);media=data['media']
        check('draft poster anonymous hidden',raw(guest,media['url'])[0]==404)
        check('film admin poster preview',raw(root,media['url'])[0]==200)
        check('SVG rejected',upload(root,b'<svg/>',mime='image/svg+xml',name='bad.svg',extra={'module':'films'})[0]==422)
        check('unknown module rejected',upload(root,test_png(),extra={'module':'admins'})[0]==422)
        check('two poster sources rejected',root.call('PUT',path,dict(title=film['title'],version=film['version'],posterMediaId=media['id'],posterUrl='https://example.test/a.png'))[0]==422)
        film=update(film,posterMediaId=media['id'],posterUrl='',posterAlt=None,posterSource='',posterLicense='')
        check('base poster alt inherited',film['posterAlt']=='基本海報說明')
        check('published poster public',raw(guest,media['url'])[0]==200)
        second=create({'title':'共用海報 '+prefix,'posterMediaId':media['id']})
        film=update(film,posterAlt='此電影的說明')
        check('per-film alt does not change shared asset',root.call('GET','/api/v1/admin/films/'+second['id'])[1]['film']['posterAlt']=='基本海報說明')
        film=update(film,posterMediaId=None,posterUrl='https://example.test/public-poster.jpg',posterAlt='網址海報')
        check('external poster preserved',film['image']=='https://example.test/public-poster.jpg')
        check('unreferenced media anonymous hidden',raw(guest,media['url'])[0]==404)
        code,attr_media=upload(root,test_png())
        check('attraction upload still works',code==201)
        check('other module asset cannot attach',root.call('PUT',path,dict(title=film['title'],version=film['version'],posterMediaId=attr_media['media']['id'],posterUrl=''))[0]==422)
        creator,creator_id=restricted('create only',{('films','create')})
        viewer,_=restricted('view only',{('films','view')})
        editor,_=restricted('edit only',{('films','edit')})
        remover,_=restricted('delete only',{('films','delete')})
        places,_=restricted('attractions only',{('attractions','view'),('attractions','create')})
        made=create({'title':'新增權限 '+prefix},creator)
        check('create does not imply view',creator.call('GET','/api/v1/admin/films')[0]==403 and creator.call('GET',path)[0]==403)
        check('create may upload film media',upload(creator,test_png(),extra={'module':'films'})[0]==201)
        check('create cannot use edit upload',upload(creator,test_png(),extra={'module':'films','action':'edit'})[0]==403)
        check('create cannot upload attractions',upload(creator,test_png())[0]==403)
        check('film view can read',viewer.call('GET',path)[0]==200)
        check('film view cannot write',viewer.call('PUT',path,dict(title='denied',version=film['version']))[0]==403)
        check('film view cannot upload',upload(viewer,test_png(),extra={'module':'films'})[0]==403)
        check('module-specific listing',viewer.call('GET','/api/v1/admin/media?module=films')[0]==200 and viewer.call('GET','/api/v1/admin/media')[0]==403)
        check('attraction permissions cannot list films',places.call('GET','/api/v1/admin/media?module=films')[0]==403)
        check('attraction permissions cannot preview draft film image',raw(places,media['url'])[0]==404)
        check('film permissions cannot preview attraction image',raw(viewer,attr_media['media']['url'])[0]==404)
        check('edit without view may edit known id',editor.call('PUT',path,dict(title=film['title'],version=film['version']))[0]==200)
        film=root.call('GET',path)[1]['film']
        check('delete without create may delete known id',remover.call('DELETE','/api/v1/admin/films/'+made['id'],{'version':made['version']})[0]==200)
        check('root begins readonly preview',root.call('POST',root.auth+'preview',{'adminId':creator_id})[0]==200)
        check('readonly preview cannot create',root.call('POST','/api/v1/admin/films',{'title':'denied'})[1].get('code')=='PREVIEW_READ_ONLY')
        check('readonly preview cannot upload',upload(root,test_png(),extra={'module':'films'})[1].get('code')=='PREVIEW_READ_ONLY')
        root.call('DELETE',root.auth+'preview',{})
        with concurrent.futures.ThreadPoolExecutor(2) as pool:
            codes=list(pool.map(lambda client:client.call('PUT',path,dict(title=film['title'],version=film['version']))[0],[root.clone(),root.clone()]))
        check('concurrent same version one conflict',sorted(codes)==[200,409])
        film=root.call('GET',path)[1]['film']
        try:
            cli('','renderer-fail')
            code,data,_=root.call('PUT',path,dict(title=film['title'],version=film['version'],synopsis='發布故障 '+prefix))
            check('saved but render fails returns 202 '+str(code),code==202 and data['publication']['status']=='failed')
            check('pending blocks stale film API',guest.call('GET','/api/v1/films/'+film['slug'])[0]==503)
            check('pending blocks stale HTML',raw(guest,film['url'])[0]==503)
        finally:cli('','renderer-restore')
        check('film authorized publication retry',root.call('POST','/api/v1/admin/films/publish-retry',{})[0]==200)
        film=root.call('GET',path)[1]['film'];film=update(film,status='archived')
        check('archived HTML 404',raw(guest,film['url'])[0]==404)
        check('content audit exists',bool(fixture(f"SELECT id FROM audit_logs WHERE resource_type='film' AND resource_id='{film['id']}'")))
    finally:
        root.call('DELETE',root.auth+'preview',{})
        for identifier in created:
            code,data,_=root.call('GET','/api/v1/admin/films/'+identifier)
            if code==200:root.call('DELETE','/api/v1/admin/films/'+identifier,{'version':data['film']['version']})
        cli('content:publish')
    report={'filmAdminChecks':len(checks),'checks':checks}
    (ROOT/'artifacts/phase4-admin-results.json').write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf-8')
    print(json.dumps(report,ensure_ascii=False))

if __name__=='__main__':main()
