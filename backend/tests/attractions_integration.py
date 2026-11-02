"""在隔離的 MariaDB、HTTP 與 Node 發布環境中驗收景點管理與公開頁面。"""
import base64
import http.cookiejar
import json
import os
import re
import secrets
import subprocess
import urllib.error
import urllib.parse
import urllib.request
import uuid
import struct
import zlib
from api_integration import Client, ROOT, BASE, ORIGIN, fixture, password, admin_body

checks = []
prefix = 'phase3-' + secrets.token_hex(5)
created = []

def test_png(width=32,height=24):
    def chunk(name,data):
        return struct.pack('>I',len(data))+name+data+struct.pack('>I',zlib.crc32(name+data)&0xffffffff)
    rows=b''.join(b'\0'+bytes([80,120,90])*width for _ in range(height))
    return b'\x89PNG\r\n\x1a\n'+chunk(b'IHDR',struct.pack('>IIBBBBB',width,height,8,2,0,0,0))+chunk(b'tEXt',b'Comment\0Test metadata')+chunk(b'IDAT',zlib.compress(rows))+chunk(b'IEND',b'')

def check(name, result):
    if not result:
        raise AssertionError(name)
    checks.append(name)

def cli(command, mode=None):
    arguments = ['docker','compose','exec','-T']
    if mode is None:
        arguments += ['-u','www-data','app-test','php','bin/console.php',command]
    else:
        arguments += ['app-test','php','tests/content-fixture.php',mode]
    result = subprocess.run(arguments,cwd=ROOT,capture_output=True,text=True,encoding='utf-8')
    if result.returncode:
        raise AssertionError('Isolated content command failed: '+result.stderr)
    return result.stdout

def raw(client,path):
    request=urllib.request.Request(BASE+path,headers={'Origin':ORIGIN})
    try: response=client.http.open(request,timeout=20)
    except urllib.error.HTTPError as error: response=error
    return response.status,response.read(),response.headers

def upload(client,data,mime='image/png',name='test.png',extra=None):
    client.refresh()
    boundary='spl-'+secrets.token_hex(12)
    fields={'action':'create','alt':'測試圖片替代文字','source':'測試程式生成','license':'測試用途'}
    if extra: fields.update(extra)
    body=b''
    for key,value in fields.items():
        body+=f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode()
    body+=f'--{boundary}\r\nContent-Disposition: form-data; name="image"; filename="{name}"\r\nContent-Type: {mime}\r\n\r\n'.encode()+data+f'\r\n--{boundary}--\r\n'.encode()
    req=urllib.request.Request(BASE+'/api/v1/admin/media',data=body,method='POST',headers={
        'Origin':ORIGIN,'X-CSRF-Token':client.token,'Content-Type':'multipart/form-data; boundary='+boundary})
    try: response=client.http.open(req,timeout=20)
    except urllib.error.HTTPError as error: response=error
    return response.status,json.loads(response.read())

def body(media_id, slug=None, **changes):
    result=dict(slug=slug or prefix,name='階段三測試 '+prefix,category='景點',district='東區',
        typeLabel='驗收景點',summary='公開摘要',description='真實資料庫內容',hours='09:00–17:00',address='新竹市東區測試路',
        latitude=24.8,longitude=120.97,transport='步行',websiteUrl='',mapUrl='',googlePlaceId='',
        mediaId=media_id,featured=False,isMuseum=False,status='draft',publishAt='2026-10-10T00:00:00+08:00',seoTitle='',seoDescription='')
    result.update(changes)
    return result

def write(root, value, target=None, expect=200):
    response=root.call('PUT' if target else 'POST','/api/v1/admin/attractions'+('/'+target if target else ''),value)
    if response[0] != expect:
        raise AssertionError('Content write failed: '+str(response[0])+' '+json.dumps(response[1],ensure_ascii=False))
    item=response[1]['attraction']
    if not target: created.append(item['id'])
    return item,response[1]

def main():
    fixture(password,'root');fixture('DELETE FROM auth_rate_limits')
    root=Client('admin');guest=Client()
    check('root authenticates',root.login('admin')[0]==200)
    check('anonymous admin list rejected',guest.call('GET','/api/v1/admin/attractions')[0]==401)
    check('public projection reads real content',guest.call('GET','/api/v1/attractions')[0]==200)
    check('published museum available',guest.call('GET','/api/v1/museum')[0]==200)
    png=test_png()
    code,data=upload(root,png)
    check('real multipart image upload',code==201)
    media=data['media']; media_id=media['id']
    check('image metadata is typed',media['width']==32 and media['height']==24 and media['mimeType']=='image/png')
    check('unpublished image not anonymous',raw(guest,media['url'])[0]==404)
    check('authorized image preview',raw(root,media['url'])[0]==200)
    check('image is reencoded',raw(root,media['url'])[1]!=png)
    check('SVG rejected',upload(root,b'<svg xmlns="http://www.w3.org/2000/svg"><script>bad()</script></svg>',mime='image/svg+xml',name='bad.svg')[0]==422)
    check('forged JPEG rejected',upload(root,b'not an image',mime='image/jpeg',name='fake.jpg')[0]==422)
    check('missing alt rejected',upload(root,png,extra={'alt':''})[0]==422)
    check('oversized dimensions rejected',upload(root,test_png(7000,1))[0]==422)
    initial=body(media_id)
    check('write without CSRF rejected',root.call('POST','/api/v1/admin/attractions',initial,csrf=False)[0]==403)
    check('cross-origin write rejected',root.call('POST','/api/v1/admin/attractions',initial,extra={'Origin':'https://attacker.test'})[0]==403)
    item,result=write(root,initial,expect=201)
    identifier=item['id']; path='/api/v1/admin/attractions/'+identifier
    check('draft saved and publication synchronized',result['publication']['status']=='succeeded')
    check('draft excluded from public API',guest.call('GET','/api/v1/attractions/'+prefix)[0]==404)
    check('draft HTML is true 404',raw(guest,'/places/'+prefix+'.html')[0]==404)
    check('admin detail loads draft',root.call('GET',path)[1]['attraction']['version']==1)
    check('duplicate slug becomes conflict',root.call('POST','/api/v1/admin/attractions',initial)[0]==409)
    bad=body(media_id,slug=prefix+'-invalid',latitude=100)
    check('coordinate bounds enforced',root.call('POST','/api/v1/admin/attractions',bad)[0]==422)
    check('unknown input rejected',root.call('POST','/api/v1/admin/attractions',dict(initial,unlisted=True))[0]==422)
    update=dict(initial,status='published',version=item['version'],name='<img src=x onerror=alert(1)>',description='<script>alert(1)</script>\n\n安全文字')
    item,result=write(root,update,identifier)
    check('published content in API',guest.call('GET','/api/v1/attractions/'+prefix)[0]==200)
    code,html,_=raw(guest,'/places/'+prefix+'.html')
    html=html.decode()
    check('direct HTML includes escaped content',code==200 and '&lt;script&gt;alert(1)&lt;/script&gt;' in html and '<img src=x onerror=alert(1)>' not in html)
    check('server HTML canonical and SEO',f'rel="canonical" href="{ORIGIN}/places/{prefix}.html"' in html and 'property="og:url"' in html)
    check('published image anonymous',raw(guest,media['url'])[0]==200)
    check('old version rejected',root.call('PUT',path,update)[0]==409)
    check('public projection omits admin fields',all(key not in guest.call('GET','/api/v1/attractions/'+prefix)[1]['attraction'] for key in ['status','deletedAt','googlePlaceId']))
    update.update(version=item['version'],slug=prefix+'-renamed',name='已改名景點 '+prefix)
    item,result=write(root,update,identifier)
    # urllib 會跟著永久 301 轉址，最終位置必須指向新的 slug。
    check('old slug resolves to renamed content',raw(guest,'/places/'+prefix+'.html')[0]==200)
    check('unknown slug true 404',raw(guest,'/places/'+prefix+'-missing.html')[0]==404)
    check('id-based detail page resolves',raw(guest,'/place-detail.html?id=2')[0]==200)
    check('unknown prototype id is 404',raw(guest,'/place-detail.html?id=999999')[0]==404)
    page=root.call('GET','/api/v1/admin/attractions?q='+urllib.parse.quote('已改名景點 '+prefix))[1]
    check('admin query hits persisted record',page['total']==1 and page['items'][0]['id']==identifier)
    check('public category filter',all(row['category']=='美食' for row in guest.call('GET','/api/v1/attractions?category='+urllib.parse.quote('美食'))[1]['items']))
    check('public pagination stable',guest.call('GET','/api/v1/attractions?pageSize=1')[1]['pageSize']==1)
    viewer_body=admin_body('p3viewer'+secrets.token_hex(4))
    viewer_id=root.call('POST','/api/v1/admin/admins',viewer_body)[1]['admin']['id']
    fixture("UPDATE admin_users SET must_change_password=0 WHERE id='"+viewer_id+"'")
    viewer=Client('admin'); check('viewer login',viewer.login(viewer_body['account'])[0]==200)
    check('viewer can read',viewer.call('GET',path)[0]==200)
    check('viewer cannot write',viewer.call('PUT',path,dict(update,version=item['version']))[0]==403)
    check('viewer cannot upload',upload(viewer,png)[0]==403)
    check('viewer cannot resolve',viewer.call('POST','/api/v1/admin/places/resolve-map-url',{'mapUrl':'https://www.google.com/maps?q=test','action':'create'})[0]==403)
    check('root enters read-only preview',root.call('POST',root.auth+'preview',{'adminId':viewer_id})[0]==200)
    check('preview can read content',root.call('GET',path)[0]==200)
    check('preview cannot update',root.call('PUT',path,dict(update,version=item['version']))[1].get('code')=='PREVIEW_READ_ONLY')
    check('preview cannot upload',upload(root,png)[1].get('code')=='PREVIEW_READ_ONLY')
    root.call('DELETE',root.auth+'preview',{})
    for url in ['http://127.0.0.1/','https://google.com.attacker.test/maps','https://www.google.com@127.0.0.1/maps','https://www.google.com:8443/maps','file:///etc/passwd','https://127.0.0.1/maps','https://goo.gl/notmaps/123']:
        check('unsafe map URL rejected '+str(len(checks)),root.call('POST','/api/v1/admin/places/resolve-map-url',{'mapUrl':url,'action':'edit'})[0]==422)
    scheduled,_=write(root,body(media_id,slug=prefix+'-scheduled',status='published',publishAt='2099-01-01T00:00:00+08:00'),expect=201)
    check('scheduled content not early',guest.call('GET','/api/v1/attractions/'+prefix+'-scheduled')[0]==404)
    fixture("UPDATE attractions SET publish_at='2026-10-10 00:00:00' WHERE id='"+scheduled['id']+"'")
    check('schedule boundary blocks stale projection',guest.call('GET','/api/v1/attractions')[0]==503)
    cli('content:tick')
    check('schedule tick activates HTML',raw(guest,'/places/'+prefix+'-scheduled.html')[0]==200)
    route_id=str(uuid.uuid4())
    # 路線表要求 slug，這裡明確帶入，再確認被路線引用的景點不能刪除。
    fixture("INSERT INTO routes (id,slug,title) VALUES ('"+route_id+"','p0-reference-"+route_id+"','P0 reference test')")
    fixture("INSERT INTO route_attractions (route_id,attraction_id,sort_order) VALUES ('"+route_id+"','"+identifier+"',1)")
    check('referenced attraction delete blocked',root.call('DELETE',path,{'version':item['version']})[0]==409)
    fixture("DELETE FROM route_attractions WHERE route_id='"+route_id+"'")
    fixture("DELETE FROM routes WHERE id='"+route_id+"'")
    try:
        cli('', 'renderer-fail')
        update.update(version=item['version'],status='archived')
        item,result=write(root,update,identifier,expect=202)
        check('render failure recorded separately',result['publication']['status']=='failed')
        check('failed unpublish does not expose old API',guest.call('GET','/api/v1/attractions')[0]==503)
        check('failed unpublish does not expose old HTML',raw(guest,'/places/'+prefix+'-renamed.html')[0]==503)
    finally:
        cli('', 'renderer-restore')
    check('authorized retry succeeds',root.call('POST','/api/v1/admin/attractions/publish-retry',{})[0]==200)
    check('archived detail becomes 404',raw(guest,'/places/'+prefix+'-renamed.html')[0]==404)
    for target in created:
        current=root.call('GET','/api/v1/admin/attractions/'+target)[1]['attraction']
        check('cleanup delete '+str(len(checks)),root.call('DELETE','/api/v1/admin/attractions/'+target,{'version':current['version']})[0]==200)
    check('deleted detail admin 404',root.call('GET',path)[0]==404)
    check('published generation journal exists',bool(fixture("SELECT id FROM content_publications WHERE status='succeeded' LIMIT 1")))
    check('content audit recorded',bool(fixture("SELECT id FROM audit_logs WHERE resource_type='attraction' AND resource_id='"+identifier+"' LIMIT 1")))
    # 真正外部 Places 另以 --live-map 明確執行一次，與本機安全驗收分開。
    live={'status':'not_run'}
    if '--live-map' in os.sys.argv:
        code,result,_=root.call('POST','/api/v1/admin/places/resolve-map-url',{'mapUrl':'https://www.google.com/maps/search/?api=1&query='+urllib.parse.quote('新竹市影像博物館'),'action':'edit'})
        live={'status':'passed' if code==200 and result.get('candidates') else 'failed','httpStatus':code,'candidateCount':len(result.get('candidates',[])),'errorCode':result.get('code')}
    report={'checks':len(checks),'passed':checks,'liveGooglePlaces':live}
    (ROOT/'artifacts/phase3-attractions-results.json').write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf-8')
    (ROOT/'artifacts/phase3-browser-credentials.json').write_text(json.dumps({'rootPassword':password,'base':BASE}),encoding='utf-8')
    print(json.dumps({'checks':len(checks),'liveGooglePlaces':live},ensure_ascii=False))

if __name__=='__main__':
    main()
