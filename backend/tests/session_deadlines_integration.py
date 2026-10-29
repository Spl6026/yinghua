"""[E-29] Real HTTP/MariaDB deadline checks; requires the named disposable UI fixture.

Create with phase8_browser_fixture.py, run this file, do UI checks, then --cleanup.
Only the fixture's two accounts/sessions/one permission are changed. No global TTL
or fixed admin credentials are changed; authentication events remain auditable.
"""
import argparse,hashlib,json,re
from pathlib import Path
from api_integration import ROOT,Client,fixture

parser=argparse.ArgumentParser(description=__doc__)
parser.add_argument('--output',type=Path,required=True)
args=parser.parse_args()
data=json.loads((ROOT/'artifacts/phase8-browser-fixture.json').read_text(encoding='utf8'))
assert not data['cleaned'] and re.fullmatch(r'p8ui[a-f0-9]{8}',data['prefix'])
for key in ['adminId','memberId','placeId']:assert re.fullmatch(r'[a-f0-9-]{36}',data[key])
checks=[]
def check(label,condition):
    assert condition,label
    checks.append(label)
def owner(kind):return ('admin_id',data['adminId']) if kind=='admin' else ('member_id',data['memberId'])
def sessions(kind):
    column,id=owner(kind)
    return column+"='"+id+"' AND revoked_at IS NULL"
def me(client):return client.call('GET',client.auth+'me')
def number(headers,key):return int(headers['X-Session-'+key])
def active(client):
    token=next(cookie.value for cookie in client.cookies if cookie.name=='spl_'+client.kind+'_session')
    return sessions(client.kind)+" AND token_hash='"+hashlib.sha256(token.encode()).hexdigest()+"'"

try:
    for kind in ['admin','member']:
        client=Client(kind);identifier=data['prefix'] if kind=='admin' else data['prefix']+'@example.test'
        status,_,headers=me(client)
        check(kind+' anonymous rejects without timing',status==401 and headers.get('X-Session-Expires-At') is None)
        status,_,_=client.login(identifier,data['password']);check(kind+' fixture login',status==200)
        status,_,headers=me(client)
        absolute=number(headers,'Expires-At');idle=number(headers,'Idle-Expires-At');now=number(headers,'Server-Time')
        check(kind+' bounded server timing',status==200 and now<idle<=absolute and absolute-now>=10*(idle-now))
        check(kind+' correct owner/kind no-store',headers['X-Session-Kind']==kind and headers['X-Session-Owner']==owner(kind)[1] and 'no-store' in headers['Cache-Control'])
        original=fixture('SELECT expires_at FROM sessions WHERE '+active(client))[0]['expires_at']
        for n in range(11):
            fixture('UPDATE sessions SET idle_expires_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 SECOND) WHERE '+active(client))
            status,_,headers=me(client)
            check(kind+' valid extension '+str(n+1),status==200 and number(headers,'Expires-At')==absolute and number(headers,'Idle-Expires-At')-number(headers,'Server-Time')>30)
        check(kind+' absolute deadline unchanged',fixture('SELECT expires_at FROM sessions WHERE '+active(client))[0]['expires_at']==original)
        fixture('UPDATE sessions SET expires_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 45 SECOND),idle_expires_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 MINUTE) WHERE '+active(client))
        status,_,headers=me(client)
        check(kind+' idle cannot exceed absolute cap',status==200 and number(headers,'Idle-Expires-At')==number(headers,'Expires-At'))
        old=client.clone();old_id=fixture('SELECT id FROM sessions WHERE '+active(client))[0]['id']
        fixture('UPDATE sessions SET idle_expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE '+active(client))
        status,_,headers=me(client)
        check(kind+' expired idle cannot extend',status==401 and headers.get('X-Session-Expires-At') is None)
        target='/api/v1/admin/attractions/'+data['placeId'] if kind=='admin' else '/api/v1/me/favorites/'+data['placeId']
        check(kind+' expired writes rejected',client.call('PUT',target,{})[0]==401)
        check(kind+' password still required',client.login(identifier,'incorrect-only-for-this-test')[0]==401)
        check(kind+' explicit reauthentication succeeds',client.login(identifier,data['password'])[0]==200)
        fixture("UPDATE sessions SET expires_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 12 HOUR),idle_expires_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 30 MINUTE) WHERE id='"+old_id+"'")
        check(kind+' rotated old token stays revoked',me(old)[0]==401)
        fixture('UPDATE sessions SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE '+active(client))
        check(kind+' absolute expiry cannot extend',me(client)[0]==401)
        check(kind+' fresh login after absolute expiry',client.login(identifier,data['password'])[0]==200)
        if kind=='admin':
            check('csrf remains enforced',client.call('PUT',target,{},extra={'X-CSRF-Token':'invalid'})[1].get('code')=='CSRF_INVALID')
            fixture("UPDATE admin_role_permissions p JOIN permissions r ON r.id=p.permission_id SET p.allowed=0 WHERE p.admin_id='"+data['adminId']+"' AND r.module_key='attractions' AND r.action_key='edit'")
            status,payload,_=client.call('PUT',target,{})
            check('deadline metadata does not grant permissions',status==403 and payload.get('code')=='FORBIDDEN')
        else:
            fixture("UPDATE members SET status='disabled' WHERE id='"+data['memberId']+"'")
            check('disabled owner remains rejected',me(client)[0]==401)
finally:
    fixture("UPDATE members SET status='active' WHERE id='"+data['memberId']+"'")
    fixture("UPDATE admin_role_permissions p JOIN permissions r ON r.id=p.permission_id SET p.allowed=1 WHERE p.admin_id='"+data['adminId']+"' AND r.module_key='attractions' AND r.action_key='edit'")
    for kind in ['admin','member']:fixture('UPDATE sessions SET revoked_at=UTC_TIMESTAMP() WHERE '+sessions(kind))

args.output.parent.mkdir(parents=True,exist_ok=True)
args.output.write_text(json.dumps({'scope':'yinghua_test owned fixture only','passed':len(checks),'checks':checks,'globalTtlChanged':False},indent=2),encoding='utf8')
print(json.dumps({'passed':len(checks),'globalTtlChanged':False}))
