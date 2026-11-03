"""瀏覽器冒煙測試：在隔離的 localhost:8081／yinghua_test 建立自有管理員與會員，執行 Playwright 腳本，結束後一律清理。

用法：python3 backend/tests/browser_smoke.py
需要 Docker 開發環境（含 test profile）、Node 與 Playwright（frontend devDependencies），以及 Playwright 的 Chromium。
"""
import json,os,secrets,subprocess,sys,uuid
from api_integration import ROOT,fixture
from attractions_integration import cli
from phase8_browser_fixture import literal,php,publish

def main():
    prefix='smoke'+secrets.token_hex(4);password='Smoke-only-'+secrets.token_urlsafe(24)
    admin=str(uuid.uuid4());member=str(uuid.uuid4())
    # 示範內容不覆寫既有資料；確保後台編輯表單與行程都有可操作的公開內容。
    for command in ['content:seed-demo','film:seed-demo','content:seed-public-demo']:cli(command)
    actor=fixture('SELECT id FROM admin_users WHERE is_root=1')[0]['id']
    hashed=php('require "vendor/autoload.php";echo (new Yinghua\\Services\\PasswordHasher())->hash(stream_get_contents(STDIN));',password.encode()).decode()
    try:
        fixture("INSERT INTO admin_users(id,account,email,display_name,password_hash,role_id,status,must_change_password) VALUES('"+admin+"',"+literal(prefix)+','+literal(prefix+'@example.test')+","+literal('冒煙測試管理員')+','+literal(hashed)+",'viewer','active',0)")
        fixture("INSERT INTO admin_role_permissions(admin_id,permission_id,allowed,updated_by) SELECT '"+admin+"',id,1,'"+actor+"' FROM permissions")
        fixture("INSERT INTO members(id,email,display_name,password_hash,email_verified_at) VALUES('"+member+"',"+literal(prefix+'@example.test')+','+literal('冒煙測試會員')+','+literal(hashed)+',UTC_TIMESTAMP())')
        publish()
        env=dict(os.environ,SMOKE_BASE_URL='http://localhost:8081',SMOKE_ADMIN=prefix,SMOKE_MEMBER=prefix+'@example.test',SMOKE_PASSWORD=password,SMOKE_ARTIFACTS=str(ROOT/'artifacts/browser-smoke'))
        result=subprocess.run(['node','frontend/tests/browser/smoke.mjs'],cwd=ROOT,env=env)
        if result.returncode:raise SystemExit(result.returncode)
    finally:
        # 只清理本次建立的帳號與其行程；一般稽核紀錄保留。
        fixture("DELETE FROM favorites WHERE member_id='"+member+"'")
        fixture("DELETE FROM itinerary_items WHERE itinerary_id IN (SELECT id FROM itineraries WHERE member_id='"+member+"')")
        fixture("DELETE FROM itineraries WHERE member_id='"+member+"'")
        fixture("UPDATE sessions SET revoked_at=UTC_TIMESTAMP() WHERE admin_id='"+admin+"' OR member_id='"+member+"'")
        fixture("UPDATE admin_users SET status='disabled',deleted_at=UTC_TIMESTAMP() WHERE id='"+admin+"' AND account="+literal(prefix))
        fixture("UPDATE members SET status='disabled',deleted_at=UTC_TIMESTAMP() WHERE id='"+member+"' AND email="+literal(prefix+'@example.test'))

if __name__=='__main__':main()
