"""[E-15] 暫停本專案 Mailpit 模擬 SMTP 故障，finally 保證重新啟動。"""
import json, secrets, subprocess, time
from api_integration import Client, fixture, ROOT

client=Client();client.refresh();address='mail-failure-'+secrets.token_hex(6)+'@example.test'
try:
    subprocess.run(['docker','compose','stop','mailpit'],cwd=ROOT,check=True,capture_output=True)
    code,body,_=client.call('POST',client.auth+'captcha/email',{'email':address})
    assert code==503 and body['code']=='MAIL_UNAVAILABLE'
    member_email=fixture('SELECT email FROM members ORDER BY created_at DESC LIMIT 1')[0]['email']
    code,body,_=client.call('POST',client.auth+'forgot-password',{'email':member_email})
    assert code==200 and 'token' not in body
    revoked=False
    for _ in range(50):
        latest=fixture("SELECT t.revoked_at FROM password_reset_tokens t JOIN members m ON m.id=t.member_id WHERE m.email='"+member_email+"' ORDER BY t.created_at DESC,t.id DESC LIMIT 1")
        if latest and latest[0]['revoked_at'] is not None:revoked=True;break
        time.sleep(.1)
    assert revoked
finally:
    subprocess.run(['docker','compose','start','mailpit'],cwd=ROOT,check=True,capture_output=True)
for _ in range(30):
    code,_,_=client.call('POST',client.auth+'captcha/email',{'email':address})
    if code==200:break
    assert code==503
    time.sleep(.2)
assert code==200
(ROOT/'artifacts/phase2-mail-failure-results.json').write_text(json.dumps({'checks':4,'captchaFailure503':True,'forgotGeneric200':True,'failedResetRevoked':True,'retryAfterMailRestored':True}),encoding='utf-8')
print('4 SMTP failure/recovery checks passed; local Mailpit restored.')
