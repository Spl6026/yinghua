"""只還原測試自己改過的設定，再視需要清理指定的會員行程與基礎測試資料。"""
import json,sys
from api_integration import ROOT,fixture
from phase8_browser_fixture import literal,publish
m=json.loads((ROOT/'artifacts/phase8-browser-fixture.json').read_text(encoding='utf8'))
path=ROOT/'artifacts/phase14/settings-fixture.json';state=json.loads(path.read_text(encoding='utf8'))
assert state['prefix']==m['prefix']
if not state['cleaned']:
    for key in ['general','seo']:
        current=fixture('SELECT updated_by FROM site_settings WHERE `key`='+literal(key))
        old=[row for row in state['settings'] if row['key']==key]
        assert not current or current[0]['updated_by']==m['adminId'] or (old and current[0]['updated_by']==old[0]['updated_by'])
        fixture('DELETE FROM site_settings WHERE `key`='+literal(key))
        for row in old:
            fixture('INSERT INTO site_settings('+','.join('`'+k+'`' for k in row)+') VALUES('+','.join('NULL' if v is None else literal(v) for v in row.values())+')')
    state['cleaned']=True;path.write_text(json.dumps(state),encoding='utf8');publish()
if '--settings-only' not in sys.argv:
    from phase11_fixture import cleanup
    cleanup()
print('Phase14 owned settings restored.')
