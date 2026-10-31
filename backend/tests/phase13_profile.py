"""[E-35] Read-only exact search WHERE/ORDER profiles on the actual MariaDB, not a load test."""
import json
from api_integration import ROOT,fixture
from phase8_browser_fixture import literal
rows=fixture('SELECT generation FROM search_documents LIMIT 1')
assert rows,'Publish content before profiling'
generation=rows[0]['generation'];q='咖啡'
where='generation='+literal(generation)+' AND LOCATE(LOWER('+literal(q)+'),LOWER(search_text))>0'
profiles={}
for kind in ['all','news']:
    condition=where if kind=='all' else where+' AND resource_type='+literal(kind)
    sql='SELECT resource_id AS id,resource_type AS type,title,summary,slug,url,thumbnail,thumbnail_alt AS thumbnailAlt FROM search_documents WHERE '+condition+' ORDER BY type_order,CASE WHEN title='+literal(q)+' THEN 0 ELSE 1 END,resource_id LIMIT 20 OFFSET 0'
    profiles[kind]={'explain':fixture('EXPLAIN '+sql),'analyze':fixture('ANALYZE FORMAT=JSON '+sql),'countExplain':fixture('EXPLAIN SELECT COUNT(*) AS total FROM search_documents WHERE '+condition)}
result={'environment':fixture('SELECT @@version AS version,@@performance_schema AS performance_schema'),'projectionRows':fixture('SELECT COUNT(*) AS total FROM search_documents'),'profiles':profiles}
(ROOT/'artifacts/phase13/query-profile.json').write_text(json.dumps(result,ensure_ascii=False,indent=2),encoding='utf8')
print(json.dumps({'environment':result['environment'],'projectionRows':result['projectionRows'],'explain':{k:v['explain'] for k,v in profiles.items()}},ensure_ascii=False))
