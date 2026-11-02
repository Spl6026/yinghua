"""建立只存在 yinghua_test 的會員行程測試資料，包含另一位獨立的會員。"""
import sys,json,uuid
from api_integration import ROOT,fixture
from phase8_browser_fixture import main as base_fixture,literal,php

def prepare():
    base_fixture(False)
    m=json.loads((ROOT/'artifacts/phase8-browser-fixture.json').read_text(encoding='utf8'))
    other=str(uuid.uuid4());email=m['prefix']+'-second@example.test'
    hashed=php('require "vendor/autoload.php";echo (new Yinghua\\Services\\PasswordHasher())->hash(stream_get_contents(STDIN));',m['password'].encode()).decode()
    fixture("INSERT INTO members(id,email,display_name,password_hash,email_verified_at) VALUES('"+other+"',"+literal(email)+",'第二位隔離會員',"+literal(hashed)+',UTC_TIMESTAMP())')
    target=ROOT/'artifacts/phase11';target.mkdir(exist_ok=True)
    (target/'fixture.json').write_text(json.dumps({'memberB':other,'emailB':email,'prefix':m['prefix'],'cleaned':False}),encoding='utf8')
    print('Phase11 second isolated member created.')

def cleanup():
    m=json.loads((ROOT/'artifacts/phase8-browser-fixture.json').read_text(encoding='utf8'));x=json.loads((ROOT/'artifacts/phase11/fixture.json').read_text(encoding='utf8'))
    assert m['prefix']==x['prefix'] and not m['cleaned']
    for member in [m['memberId'],x['memberB']]:
        fixture("DELETE i FROM itinerary_items i JOIN itineraries t ON t.id=i.itinerary_id WHERE t.member_id='"+member+"'")
        fixture("DELETE FROM itineraries WHERE member_id='"+member+"'")
        fixture("DELETE FROM favorites WHERE member_id='"+member+"'")
    for row in fixture("SELECT id FROM routes WHERE title LIKE "+literal(m['prefix']+'%')):
        fixture("DELETE FROM route_attractions WHERE route_id='"+row['id']+"'");fixture("DELETE FROM routes WHERE id='"+row['id']+"'")
    # 選用的第二個瀏覽器測試站點以測試自有的完整 slug 辨識，不依顯示名稱。
    fixture('DELETE FROM attractions WHERE slug='+literal(m['prefix']+'-p11-browser'))
    fixture("UPDATE sessions SET revoked_at=UTC_TIMESTAMP() WHERE member_id='"+x['memberB']+"'")
    fixture("UPDATE members SET status='disabled',deleted_at=UTC_TIMESTAMP() WHERE id='"+x['memberB']+"' AND email="+literal(x['emailB']))
    base_fixture(True);x['cleaned']=True;(ROOT/'artifacts/phase11/fixture.json').write_text(json.dumps(x),encoding='utf8')
    print('Phase11 owned member/route data cleaned; accounts disabled; audit retained.')

if __name__=='__main__':cleanup() if '--cleanup' in sys.argv else prepare()
