"""以獨立的 JSON schema 檢查推薦路線所有實際的 HTTP 回應與成功的請求。"""
import sys,argparse,json,re
from pathlib import Path
p=argparse.ArgumentParser();p.add_argument('--dependency-path');args=p.parse_args()
if args.dependency_path:sys.path.insert(0,str(Path(args.dependency_path).resolve()))
import jsonschema
from api_integration import ROOT,Client
import phase10_integration
spec=json.loads((ROOT/'backend/resources/openapi.json').read_text(encoding='utf8'));validator=jsonschema.Draft202012Validator(spec)
original=Client.call;responses=0;observed=set();errors=[]
def wrapped(self,method,path,body=None,csrf=True,extra=None):
    global responses
    code,data,headers=original(self,method,path,body,csrf,extra);clean=path.split('?')[0]
    route=clean if clean in spec['paths'] else next((key for key in spec['paths'] if re.fullmatch(re.sub(r'\{[^}]+\}',r'[^/]+',key),clean) and method.lower() in spec['paths'][key]),None)
    assert route is not None,(method,clean)
    operation=spec['paths'][route][method.lower()];response=operation['responses'].get(str(code));assert response is not None,(route,code)
    if '$ref' in response:response=spec['components']['responses'][response['$ref'].split('/')[-1]]
    schemas=[response.get('content',{}).get('application/json',{}).get('schema')]
    if 200<=code<300 and body is not None and operation.get('requestBody'):schemas.append(operation['requestBody']['content']['application/json']['schema']);values=[data,body]
    else:values=[data]
    for schema,value in zip(schemas,values):
        if schema:
            for error in validator.evolve(schema=schema).iter_errors(value):errors.append({'operation':method+' '+route,'status':code,'path':list(error.path),'validator':error.validator})
    observed.add((method,route));responses+=1;return code,data,headers
try:
    Client.call=wrapped;phase10_integration.main()
finally:Client.call=original
output={'responses':responses,'operations':len(observed),'errors':errors};(ROOT/'artifacts/phase10/contracts.json').write_text(json.dumps(output,ensure_ascii=False,indent=2),encoding='utf8')
print(json.dumps(output,ensure_ascii=False));assert not errors
