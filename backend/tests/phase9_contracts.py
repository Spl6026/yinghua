"""以獨立的 OpenAPI 驗證最新消息與優惠實際的請求與回應。"""
from pathlib import Path
import argparse,json,re,sys
ROOT=Path(__file__).resolve().parents[2]
p=argparse.ArgumentParser();p.add_argument('--dependency-path');args=p.parse_args()
if args.dependency_path:sys.path.insert(0,str(Path(args.dependency_path).resolve()))
import jsonschema
from api_integration import Client
import phase9_integration
spec=json.loads((ROOT/'backend/resources/openapi.json').read_text(encoding='utf8'))
validator=jsonschema.Draft202012Validator(spec);original=Client.call;observed=set();errors=[];responses=0
def wrapped(self,method,path,body=None,csrf=True,extra=None):
    global responses
    code,data,headers=original(self,method,path,body,csrf,extra)
    clean=path.split('?')[0]
    route=clean if clean in spec['paths'] and method.lower() in spec['paths'][clean] else next((key for key in spec['paths'] if re.fullmatch(re.sub(r'\{[^}]+\}',r'[^/]+',key),clean) and method.lower() in spec['paths'][key]),None)
    assert route is not None,('Undocumented route',method,path.split('?')[0])
    op=spec['paths'][route][method.lower()];definition=op['responses'].get(str(code))
    assert definition is not None,('Undocumented status',method,route,code)
    if '$ref' in definition:
        definition=spec['components']['responses'][definition['$ref'].split('/')[-1]]
    schema=definition.get('content',{}).get('application/json',{}).get('schema')
    if schema:
        for e in validator.evolve(schema=schema).iter_errors(data):errors.append({'route':route,'status':code,'path':list(e.path),'validator':e.validator})
    if 200<=code<300 and body is not None and op.get('requestBody'):
        schema=op['requestBody']['content']['application/json']['schema']
        for e in validator.evolve(schema=schema).iter_errors(body):errors.append({'route':route,'request':True,'path':list(e.path),'validator':e.validator})
    observed.add((method,route));responses+=1;return code,data,headers
try:
    Client.call=wrapped;phase9_integration.main()
finally:Client.call=original
output={'responses':responses,'operations':len(observed),'errors':errors}
target=ROOT/'artifacts/phase9-contracts.json';target.write_text(json.dumps(output,ensure_ascii=False,indent=2),encoding='utf8')
print(json.dumps(output,ensure_ascii=False));assert not errors
