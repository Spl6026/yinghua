"""依獨立的 OpenAPI 契約驗證會員行程實際的 HTTP 請求與回應。"""
import argparse,json,re,sys
from pathlib import Path
parser=argparse.ArgumentParser();parser.add_argument('--dependency-path');args=parser.parse_args()
if args.dependency_path:sys.path.insert(0,str(Path(args.dependency_path).resolve()))
import jsonschema
from api_integration import ROOT,Client
import phase11_integration
spec=json.loads((ROOT/'backend/resources/openapi.json').read_text(encoding='utf8'))
validator=jsonschema.Draft202012Validator(spec);original=Client.call;observed=set();errors=[];responses=0
def wrapped(self,method,path,body=None,csrf=True,extra=None):
    global responses
    code,data,headers=original(self,method,path,body,csrf,extra);clean=path.split('?')[0]
    route=clean if clean in spec['paths'] else next((key for key in spec['paths'] if re.fullmatch(re.sub(r'\{[^}]+\}',r'[^/]+',key),clean) and method.lower() in spec['paths'][key]),None)
    assert route is not None,(method,clean)
    operation=spec['paths'][route][method.lower()];response=operation['responses'].get(str(code));assert response is not None,(route,code)
    if '$ref' in response:response=spec['components']['responses'][response['$ref'].split('/')[-1]]
    pairs=[(response.get('content',{}).get('application/json',{}).get('schema'),data)]
    if 200<=code<300 and body is not None and operation.get('requestBody'):pairs.append((operation['requestBody']['content']['application/json']['schema'],body))
    for schema,value in pairs:
        if schema:
            for error in validator.evolve(schema=schema).iter_errors(value):errors.append({'operation':method+' '+route,'status':code,'path':list(error.path),'validator':error.validator})
    # Client 會把空的 body 當成 {}，但實際的 HTTP 回應長度必須是 0 或不帶 Content-Length。
    if code==204:assert data=={} and headers.get('Content-Length','0')=='0'
    responses+=1;observed.add((method,route));return code,data,headers
try:
    Client.call=wrapped;phase11_integration.main()
finally:Client.call=original
output={'responses':responses,'operations':len(observed),'errors':errors}
(ROOT/'artifacts/phase11/contracts.json').write_text(json.dumps(output,ensure_ascii=False,indent=2),encoding='utf8')
print(json.dumps(output,ensure_ascii=False));assert not errors
