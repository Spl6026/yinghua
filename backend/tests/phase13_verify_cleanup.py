"""唯讀確認測試資料已清乾淨，並檢查公開投影與 migration 的完整性。"""
import json,hashlib
from api_integration import ROOT,fixture
from phase8_browser_fixture import literal
m=json.loads((ROOT/'artifacts/phase13/fixture.json').read_text(encoding='utf8'));assert m['cleaned']
prefix=literal(m['prefix']+'%')
counts={table:int(fixture('SELECT COUNT(*) AS n FROM '+table+' WHERE '+column+' LIKE '+prefix)[0]['n']) for table,column in [('news_posts','title'),('offers','title'),('merchants','name'),('attractions','name'),('search_documents','title')]}
assert not any(counts.values()),counts
indexes=fixture('SHOW INDEX FROM search_documents');assert {r['Key_name'] for r in indexes}=={'PRIMARY'}
generations=int(fixture('SELECT COUNT(DISTINCT generation) AS n FROM search_documents')[0]['n']);assert generations<=1
records=fixture('SELECT version,checksum FROM schema_migrations ORDER BY version')
assert all(hashlib.sha256((ROOT/'backend/database/migrations'/r['version']).read_bytes()).hexdigest()==r['checksum'] for r in records)
result={'ownedContentResiduals':counts,'indexNames':['PRIMARY'],'generations':generations,'migrationChecksumsValid':len(records)}
(ROOT/'artifacts/phase13/cleanup-verified.json').write_text(json.dumps(result,indent=2),encoding='utf8');print(json.dumps(result))
