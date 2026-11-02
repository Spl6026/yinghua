"""驗證已發布的條款政策、過期的同意紀錄、待發布狀態，以及同意紀錄確實寫入資料庫。"""
import json,uuid
from api_integration import ROOT,Client,fixture,challenge,register_body
from phase8_browser_fixture import literal,publish
m=json.loads((ROOT/'artifacts/phase8-browser-fixture.json').read_text(encoding='utf8'));assert not m['cleaned']
rows=fixture("SELECT version,updated_by FROM site_settings WHERE `key`='general'");assert len(rows)==1 and rows[0]['updated_by']==m['adminId']
version=int(rows[0]['version']);client=Client();metadata=client.refresh();token=metadata['termsVersion'];checks=[];registered=None
def check(name,value):assert value,name;checks.append(name)
body={'email':m['prefix']+'-consent-'+uuid.uuid4().hex[:6]+'@example.test','displayName':'隔離同意驗收','password':m['password'],'passwordConfirmation':m['password'],'acceptTerms':True,'termsVersion':'stale','challengeToken':'a'*64,'answer':'invalid'}
try:
    code,error,_=client.call('POST','/api/v1/auth/register',body);check('stale version rejected before captcha',code==422 and 'termsVersion' in error['fieldErrors'])
    fixture("UPDATE site_settings SET version=version+1 WHERE `key`='general' AND updated_by="+literal(m['adminId']))
    check('login metadata remains available while pending',client.refresh()['termsVersion']==token)
    body['termsVersion']=token;code,error,_=client.call('POST','/api/v1/auth/register',body);check('pending policy cannot be accepted',code==503 and error['code']=='PUBLICATION_PENDING')
finally:
    fixture("UPDATE site_settings SET version="+str(version)+" WHERE `key`='general' AND updated_by="+literal(m['adminId']))
try:
    captcha,answer=challenge(client,body['email']);valid=register_body(client,body['email'],captcha,answer);valid['password']=valid['passwordConfirmation']=m['password']
    code,data,_=client.call('POST','/api/v1/auth/register',valid);check('current published consent registration succeeds',code==201)
    registered=fixture('SELECT id FROM members WHERE email='+literal(body['email']))[0]['id']
    consents=fixture('SELECT document_key,document_version FROM member_consents WHERE member_id='+literal(registered))
    check('both documents retain exact published digest',len(consents)==2 and {row['document_key'] for row in consents}=={'terms','privacy'} and all(row['document_version']==token for row in consents))
finally:
    if registered:
        fixture('UPDATE sessions SET revoked_at=UTC_TIMESTAMP() WHERE member_id='+literal(registered))
        fixture('UPDATE members SET status=\'disabled\',deleted_at=UTC_TIMESTAMP() WHERE id='+literal(registered))
result={'checks':len(checks),'passed':True};(ROOT/'artifacts/phase14/policies.json').write_text(json.dumps(result),encoding='utf8');print(json.dumps(result))
