"""驗證電影搜尋、狀態篩選，以及管理端與公開端的穩定分頁；測試資料只放在 yinghua_test。"""
import datetime,json,secrets,sys,urllib.parse,uuid
from api_integration import Client,ROOT,fixture,password
from attractions_integration import cli

def cleanup(ids):
    for identifier in ids:
        fixture(f"DELETE FROM film_screenings WHERE film_id='{identifier}'")
        fixture(f"DELETE FROM film_aliases WHERE film_id='{identifier}'")
        fixture(f"DELETE FROM films WHERE id='{identifier}'")
    cli('content:publish')

def main():
    fixture(password,'root');root=Client('admin');guest=Client();assert root.login('admin')[0]==200
    prefix='film-pages-'+secrets.token_hex(5);ids=[];checks=[]
    local=datetime.datetime.now(datetime.timezone(datetime.timedelta(hours=8)))
    monday=local.date()-datetime.timedelta(days=local.weekday())
    start=datetime.datetime.combine(monday,datetime.time(),local.tzinfo).astimezone(datetime.timezone.utc)
    def check(label,result):
        assert result,label
        checks.append(label)
    before=guest.call('GET','/api/v1/films')[1]['total']
    try:
        for number in range(22):
            identifier=str(uuid.uuid4());ids.append(identifier);status='draft' if number==21 else 'published'
            title=f'分頁驗收 {number+1:02} '+prefix+(' %_!' if number==0 else '')
            fixture(f"INSERT INTO films(id,slug,title,status,updated_at) VALUES('{identifier}','{prefix}-{number+1}','{title}','{status}','2026-01-01 00:00:00')")
            if status=='published':fixture(f"INSERT INTO film_screenings(id,film_id,starts_at) VALUES('{uuid.uuid4()}','{identifier}','{(start+datetime.timedelta(minutes=number)).strftime('%Y-%m-%d %H:%M:%S')}')")
        cli('content:publish')
        page=root.call('GET','/api/v1/admin/films?'+urllib.parse.urlencode({'q':prefix,'pageSize':5}))[1]
        second=root.call('GET','/api/v1/admin/films?'+urllib.parse.urlencode({'q':prefix,'pageSize':5,'page':2}))[1]
        check('admin filtered total and page size',page['total']==22 and len(page['items'])==5)
        check('admin stable pages do not duplicate',not {f['id'] for f in page['items']} & {f['id'] for f in second['items']})
        expected=fixture(f"SELECT id FROM films WHERE slug LIKE '{prefix}-%' ORDER BY updated_at DESC,id DESC LIMIT 10")
        check('stable secondary id ordering',[f['id'] for f in page['items']+second['items']]==[r['id'] for r in expected])
        for state,count in [('draft',1),('published',21),('archived',0)]:
            code,data,_=root.call('GET','/api/v1/admin/films?'+urllib.parse.urlencode({'q':prefix,'status':state}))
            check(state+' filter',code==200 and data['total']==count)
        code,data,_=root.call('GET','/api/v1/admin/films?'+urllib.parse.urlencode({'q':'%_!'}))
        check('LIKE metacharacters treated literally',code==200 and data['total']==1 and data['items'][0]['id']==ids[0])
        check('parameterized search does not interpret SQL',root.call('GET','/api/v1/admin/films?'+urllib.parse.urlencode({'q':"' OR 1=1 --"}))[1]['total']==0)
        for query in ['page=0','pageSize=101','status=unknown']:
            check('invalid admin query '+query,root.call('GET','/api/v1/admin/films?'+query)[0]==422)
        first=guest.call('GET','/api/v1/films?pageSize=20')[1];second=guest.call('GET','/api/v1/films?page=2&pageSize=20')[1]
        check('public total counts films not screenings',first['total']==before+21)
        check('public first page size',len(first['items'])==20)
        check('public pages share generation',first['generation']==second['generation'])
        check('public pages do not duplicate',not {f['id'] for f in first['items']} & {f['id'] for f in second['items']})
        combined=first['items']+second['items']
        check('all published fixtures reachable',set(ids[:21])<={f['id'] for f in combined})
        check('draft fixture not public',ids[21] not in {f['id'] for f in combined})
        check('last page can be empty',guest.call('GET','/api/v1/films?page=100')[1]['items']==[])
        if '--keep-for-browser' in sys.argv:
            (ROOT/'artifacts/phase4-pagination-fixtures.json').write_text(json.dumps({'ids':ids,'prefix':prefix}),encoding='utf-8')
            ids=[]
    finally:
        if ids:cleanup(ids)
    report={'filmPaginationChecks':len(checks),'checks':checks}
    (ROOT/'artifacts/phase4-pagination-results.json').write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf-8')
    print(json.dumps(report,ensure_ascii=False))

if __name__=='__main__':main()
