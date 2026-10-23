"""[E-27] Cross-module regression with the browser's single CSRF refresh policy.

Only a normal same-origin request explicitly rejected as CSRF_INVALID is retried.
Missing/stale-token and origin/security probes keep their original rejection checks.
No retry for network failures, business errors or authorization failures.
"""
import argparse,contextlib,importlib,io,json,runpy,sys
from pathlib import Path

ROOT=Path(__file__).resolve().parents[2]
parser=argparse.ArgumentParser();parser.add_argument('--dependency-path');options=parser.parse_args()
if options.dependency_path:sys.path.insert(0,str(Path(options.dependency_path).resolve()))
import jsonschema
from api_integration import Client

schema=json.loads((ROOT/'backend/resources/openapi.json').read_text(encoding='utf8'))
validator=jsonschema.Draft202012Validator(schema)
original=Client.call;recoveries=[]
def browser_call(self,method,path,body=None,csrf=True,extra=None):
    response=original(self,method,path,body,csrf,extra)
    if csrf and not extra and method not in ('GET','HEAD') and response[0]==403 and response[1].get('code')=='CSRF_INVALID':
        validator.evolve(schema={'$ref':'#/components/schemas/Error'}).validate(response[1])
        previous_expiry=int(self.token.split('.')[0]) if self.token else None
        self.refresh()
        recoveries.append({'method':method,'path':path,'tokenExpiryChange':int(self.token.split('.')[0])-previous_expiry if previous_expiry else None})
        response=original(self,method,path,body,csrf,extra)
    return response

def main():
    Client.call=browser_call
    completed=[]
    try:
        sys.argv=['openapi_contracts.py','--include-films']
        runpy.run_path(str(ROOT/'backend/tests/openapi_contracts.py'),run_name='__main__')
        completed.append('openapi_contracts:21-suites')
        sys.argv=[sys.argv[0]]
        for name in ['news_public_integration','offers_public_integration','home_public_integration','public_content_security','public_demo_integration']:
            capture=io.StringIO()
            try:
                with contextlib.redirect_stdout(capture):importlib.import_module(name).main()
            finally:
                (ROOT/('artifacts/phase6-regression-'+name+'.log')).write_text(capture.getvalue(),encoding='utf8')
            completed.append(name);print(json.dumps({'suite':name,'completed':len(completed)}),flush=True)
    finally:
        (ROOT/'artifacts/phase6-cross-module-results.json').write_text(json.dumps({'completed':completed,'csrfRefreshes':recoveries,'allCompleted':len(completed)==6},indent=2),encoding='utf8')
        Client.call=original

if __name__=='__main__':main()
