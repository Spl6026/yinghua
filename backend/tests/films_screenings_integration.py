"""驗證場次開始時間、整批場次的版本控制，以及真實的交易與並行行為。"""
import concurrent.futures, datetime, json, secrets, urllib.parse
from api_integration import Client, ROOT, fixture, password
from attractions_integration import raw, cli

def main():
    fixture(password,'root');fixture('DELETE FROM auth_rate_limits')
    root=Client('admin');guest=Client();assert root.login('admin')[0]==200
    prefix='screenings-'+secrets.token_hex(5);created=[];checks=[]
    def check(label,result):
        assert result,label
        checks.append(label)
    def create(label,rows,status='published',**extra):
        code,data,_=root.call('POST','/api/v1/admin/films',dict(title=label+' '+prefix,slug=prefix+'-'+label,screenings=rows,status=status,**extra))
        assert code==201,(code,data);created.append(data['film']['id']);return data['film']
    def save(film,**changes):
        code,data,_=root.call('PUT','/api/v1/admin/films/'+film['id'],dict(title=film['title'],version=film['version'],**changes))
        assert code==200,(code,data);return data['film']
    def get(film):return root.call('GET','/api/v1/admin/films/'+film['id'])[1]['film']
    def rows(film):return [{k:row[k] for k in ['id','startsAt','ticketUrl']} for row in film['screenings']]
    def sqlstate(film):return fixture(f"SELECT version,title,slug FROM films WHERE id='{film['id']}'")+fixture(f"SELECT id,starts_at,created_at,ticket_url FROM film_screenings WHERE film_id='{film['id']}' ORDER BY id")
    local=datetime.datetime.now(datetime.timezone(datetime.timedelta(hours=8)))
    monday=local.date()-datetime.timedelta(days=local.weekday())
    stamp=lambda hour,minute=0,days=0:f'{monday+datetime.timedelta(days=days)}T{hour:02}:{minute:02}:00+08:00'
    a14={'startsAt':stamp(14)};a1430={'startsAt':stamp(14,30),'ticketUrl':'https://tickets.example.test/special'}
    try:
        a=create('first',[a14,a1430,{'startsAt':stamp(14,days=1)}],ticketUrl='https://tickets.example.test/default')
        check('three real screenings persisted',len(a['screenings'])==3)
        check('distinct starts allowed without duration',a['screenings'][0]['startsAt']!=a['screenings'][1]['startsAt'])
        check('ticket fallback and override',a['screenings'][0]['effectiveTicketUrl'].endswith('/default') and a['screenings'][1]['effectiveTicketUrl'].endswith('/special'))
        code,data,_=guest.call('GET','/api/v1/films?week=current&pageSize=100')
        check('weekly API includes three starts',code==200 and len(next(f for f in data['items'] if f['id']==a['id'])['screenings'])==3)
        check('cinema and home use saved film',a['title'].encode() in raw(guest,'/cinema.html')[1] and a['title'].encode() in raw(guest,'/index.html')[1])
        for label,values,expect in [
            ('same movie same start',[a14,a14],409),
            ('same instant different offsets',[a14,{'startsAt':stamp(6).replace('+08:00','+00:00')}],409),
            ('invalid leap day',[{'startsAt':'2026-02-29T14:00:00+08:00'}],422),
            ('non-minute start',[{'startsAt':stamp(18).replace(':00+08',':01+08')}],422),
            ('end deferred',[dict(a14,endsAt=stamp(16))],422),
            ('venue deferred',[dict(a14,venue='場地')],422),
        ]:
            code,result,_=root.call('POST','/api/v1/admin/films',dict(title='invalid '+prefix,screenings=values))
            check(label,code==expect)
            check(label+' error points to row',any(key.startswith('screenings.') for key in result.get('fieldErrors',{})))
        check('cross-film draft start conflicts',root.call('POST','/api/v1/admin/films',dict(title='conflict '+prefix,screenings=[a14]))[0]==409)
        b=create('second',[{'startsAt':stamp(14,15)}])
        check('14:00 and 14:15 different films allowed',len(b['screenings'])==1)
        before=sqlstate(b)
        check('cannot take other film screening id',root.call('PUT','/api/v1/admin/films/'+b['id'],dict(title='hijack',slug=prefix+'-hijack',version=b['version'],screenings=[dict(rows(a)[0],startsAt=stamp(20))]))[0]==422)
        check('ownership error rolls back film and children',sqlstate(b)==before and not fixture(f"SELECT path FROM film_aliases WHERE path='/films/{prefix}-hijack.html'"))
        repeated=[rows(a)[0],dict(rows(a)[0],startsAt=stamp(21))]
        check('duplicate child id rejected',root.call('PUT','/api/v1/admin/films/'+a['id'],dict(title=a['title'],version=a['version'],screenings=repeated))[0]==422)
        c=create('archived',[a14],status='archived')
        check('archived start may duplicate',len(c['screenings'])==1)
        check('restore omitted screenings rechecks starts',root.call('PUT','/api/v1/admin/films/'+c['id'],dict(title=c['title'],version=c['version'],status='published'))[0]==409)
        c=save(c,status='published',screenings=[])
        check('zero screenings may restore',not c['screenings'])
        old_created={r['id']:r['created_at'] for r in fixture(f"SELECT id,created_at FROM film_screenings WHERE film_id='{a['id']}'")}
        swapped=rows(a);swapped[0]['startsAt'],swapped[1]['startsAt']=swapped[1]['startsAt'],swapped[0]['startsAt']
        a=save(a,screenings=swapped)
        check('existing ids survive reordered starts',set(r['id'] for r in a['screenings'])==set(old_created))
        check('retained screening created_at preserved',{r['id']:r['created_at'] for r in fixture(f"SELECT id,created_at FROM film_screenings WHERE film_id='{a['id']}'")}==old_created)
        a=save(a,screenings=rows(a)[1:])
        b=save(b,screenings=rows(b)+[a14])
        check('removed start released for another film',len(b['screenings'])==2)
        a=save(a,status='archived')
        reserve=create('reserve',[{'startsAt':stamp(14,30)}],status='draft')
        check('unpublish releases occupied starts',len(reserve['screenings'])==1)
        check('draft reserves starts',root.call('POST','/api/v1/admin/films',dict(title='conflict',screenings=[{'startsAt':stamp(14,30)}]))[0]==409)
        check('restore rejects another draft reservation',root.call('PUT','/api/v1/admin/films/'+a['id'],dict(title=a['title'],version=a['version'],status='published'))[0]==409)
        reserve=save(reserve,status='archived');a=save(a,status='published')
        check('restoring after release succeeds',a['status']=='published')
        with concurrent.futures.ThreadPoolExecutor(2) as pool:
            results=list(pool.map(lambda client:client.call('POST','/api/v1/admin/films',dict(title='race '+prefix,screenings=[{'startsAt':stamp(19)}])),[root.clone(),root.clone()]))
        for code,result,_ in results:
            if code==201:created.append(result['film']['id'])
        check('cross-film concurrent same start only one winner',sorted(r[0] for r in results)==[201,409])
        before=sqlstate(a)
        for trigger,table in [('phase4_audit_failure','audit_logs'),('phase4_screening_failure','film_screenings')]:
            fixture(f"CREATE TRIGGER {trigger} BEFORE INSERT ON {table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='phase4 isolated failure'")
            try:
                code,result,_=root.call('PUT','/api/v1/admin/films/'+a['id'],dict(title='rollback',slug=prefix+'-rollback',version=a['version'],screenings=[{'startsAt':stamp(22)}]))
                check(table+' failure returns safe 500',code==500 and 'SQLSTATE' not in json.dumps(result))
            finally:fixture('DROP TRIGGER '+trigger)
            check(table+' failure rolls back whole aggregate',sqlstate(a)==before and not fixture(f"SELECT path FROM film_aliases WHERE path='/films/{prefix}-rollback.html'"))
        many=[{'startsAt':(datetime.datetime(2091,1,2,0,tzinfo=local.tzinfo)+datetime.timedelta(minutes=i)).isoformat()} for i in range(101)]
        large=create('many',many,status='draft')
        check('no invented 100-screening business cap',len(large['screenings'])==101)
        scheduled=create('scheduled',[{'startsAt':stamp(23)}],publishAt='2099-01-01T00:00:00+08:00')
        check('scheduled film not early',guest.call('GET','/api/v1/films/'+scheduled['slug'])[0]==404)
        fixture(f"UPDATE films SET publish_at='2026-01-01 00:00:00' WHERE id='{scheduled['id']}'")
        # 排程時間到、發布程序還沒重新產生前，照常提供上一版，新電影尚未出現。
        check('schedule change keeps serving last version',guest.call('GET','/api/v1/films')[0]==200)
        check('scheduled film waits for next tick',guest.call('GET','/api/v1/films/'+scheduled['slug'])[0]==404)
        cli('content:tick')
        check('schedule tick publishes film',raw(guest,scheduled['url'])[0]==200)
        check('attractions preserved through screening changes',guest.call('GET','/api/v1/attractions')[0]==200)
    finally:
        for trigger in ['phase4_audit_failure','phase4_screening_failure']:fixture('DROP TRIGGER IF EXISTS '+trigger)
        for identifier in created:
            code,data,_=root.call('GET','/api/v1/admin/films/'+identifier)
            if code==200:root.call('DELETE','/api/v1/admin/films/'+identifier,{'version':data['film']['version']})
        cli('content:publish')
    report={'filmScreeningChecks':len(checks),'checks':checks}
    (ROOT/'artifacts/phase4-screenings-results.json').write_text(json.dumps(report,ensure_ascii=False,indent=2),encoding='utf-8')
    print(json.dumps(report,ensure_ascii=False))

if __name__=='__main__':main()
