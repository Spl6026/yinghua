"""[E-15] Isolated active member; SMTP failure revokes its reset, Mailpit always recovers."""
import json, secrets, subprocess, time, uuid
from api_integration import Client, fixture, ROOT

def compose(*args):
    return subprocess.run(['docker','compose',*args],cwd=ROOT,check=True,capture_output=True)

def main():
    client=Client();client.refresh()
    member=str(uuid.uuid4());address='mail-failure-'+secrets.token_hex(6)+'@example.test'
    # 不挑其他套件留下的最後會員；此帳號不登入，不建立可使用的測試密碼。
    fixture("INSERT INTO members(id,email,display_name,password_hash,email_verified_at) VALUES('"+member+"','"+address+"','SMTP隔離驗收','unusable-test-fixture',UTC_TIMESTAMP())")
    try:
        try:
            compose('stop','mailpit')
            code,body,_=client.call('POST',client.auth+'captcha/email',{'email':address})
            assert code==503 and body['code']=='MAIL_UNAVAILABLE'
            code,body,_=client.call('POST',client.auth+'forgot-password',{'email':address})
            assert code==200 and 'token' not in body
            rows=fixture("SELECT revoked_at FROM password_reset_tokens WHERE member_id='"+member+"'")
            assert len(rows)==1 and rows[0]['revoked_at'] is not None
        finally:
            compose('start','mailpit')
        # 等服務健康，僅送一次恢復後的業務請求；不將寄信錯誤重試到成功。
        deadline=time.monotonic()+30
        while time.monotonic()<deadline:
            states=[json.loads(line) for line in compose('ps','--format','json','mailpit').stdout.decode().splitlines() if line.strip()]
            if len(states)==1 and states[0].get('Health')=='healthy':break
            time.sleep(.5)
        else:raise AssertionError('Mailpit health did not recover in the fixed window')
        code,_,_=client.call('POST',client.auth+'captcha/email',{'email':address})
        assert code==200
        report={'checks':4,'captchaFailure503':True,'forgotGeneric200':True,'failedResetRevoked':True,'singleRequestAfterMailRestored':True,'ownedActiveMember':True}
    finally:
        fixture("UPDATE password_reset_tokens SET revoked_at=COALESCE(revoked_at,UTC_TIMESTAMP()) WHERE member_id='"+member+"'")
        fixture("UPDATE members SET status='disabled',deleted_at=UTC_TIMESTAMP() WHERE id='"+member+"'")
    report['fixtureDisabled']=True
    (ROOT/'artifacts/phase2-mail-failure-results.json').write_text(json.dumps(report,indent=2),encoding='utf8')
    print(json.dumps(report))

if __name__=='__main__':main()
