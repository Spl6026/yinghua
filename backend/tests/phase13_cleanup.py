"""只清理指定的 yinghua_test 測試資料，不動共用或示範內容。"""
import json,sys
from api_integration import ROOT,fixture
from phase8_browser_fixture import literal,publish
from phase11_fixture import cleanup
p=ROOT/'artifacts/phase13/fixture.json';m=json.loads(p.read_text(encoding='utf8'));assert m['prefix'].startswith('p8ui') and not m['cleaned']
for topic,identifier in m['created']:
    table='offers' if topic=='offers' else 'news_posts';fk='offer_id' if topic=='offers' else 'news_post_id';aliases='offer_aliases' if topic=='offers' else 'news_aliases'
    fixture('DELETE FROM '+aliases+' WHERE '+fk+'='+literal(identifier));fixture('DELETE FROM '+table+' WHERE id='+literal(identifier)+' AND title LIKE '+literal(m['prefix']+'%'))
ids=','.join(literal(i) for i in m['newsIds']);fixture('DELETE FROM news_aliases WHERE news_post_id IN ('+ids+')');fixture('DELETE FROM news_posts WHERE id IN ('+ids+') AND title LIKE '+literal(m['prefix']+'%'))
if m.get('merchantId'):fixture('DELETE FROM merchants WHERE id='+literal(m['merchantId'])+' AND name LIKE '+literal(m['prefix']+'%'))
fixture('DELETE FROM attractions WHERE id='+literal(m['foodId'])+' AND name LIKE '+literal(m['prefix']+'%'))
if '--content-only' not in sys.argv:cleanup()
publish();m['cleaned']=True;p.write_text(json.dumps(m),encoding='utf8');print('Owned search fixture cleaned and index republished.')
