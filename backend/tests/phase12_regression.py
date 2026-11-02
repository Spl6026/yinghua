"""重用測試自有的資料，依序重跑最新消息／優惠、推薦路線與會員行程的契約測試；絕不重設 root 的憑證。"""
import argparse,json,subprocess,sys
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
parser=argparse.ArgumentParser();parser.add_argument('--dependency-path',required=True);args=parser.parse_args()
target=ROOT/'artifacts/phase12';target.mkdir(exist_ok=True)
manifest=json.loads((ROOT/'artifacts/phase8-browser-fixture.json').read_text(encoding='utf8'));assert not manifest['cleaned']
reports=[]
for phase in [9,10,11]:
    report_path=ROOT/'artifacts/phase9-contracts.json' if phase==9 else ROOT/('artifacts/phase'+str(phase)+'/contracts.json')
    (ROOT/('artifacts/phase'+str(phase))).mkdir(exist_ok=True)
    command=[sys.executable,'-X','utf8','backend/tests/phase'+str(phase)+'_contracts.py','--dependency-path',args.dependency_path]
    result=subprocess.run(command,cwd=ROOT,capture_output=True,text=True,encoding='utf8')
    (target/('p1-phase'+str(phase)+'.txt')).write_text(result.stdout+result.stderr,encoding='utf8')
    assert result.returncode==0,('P1 regression failed',phase,result.returncode)
    report=json.loads(report_path.read_text(encoding='utf8'))
    assert not report['errors'];reports.append({'phase':phase,**report})
    (target/('p1-phase'+str(phase)+'-contracts.json')).write_text(json.dumps(report,indent=2),encoding='utf8')
    print(json.dumps({'phase':phase,'responses':report['responses'],'operations':report['operations'],'errors':0}),flush=True)
(target/'p1-regression.json').write_text(json.dumps(reports,indent=2),encoding='utf8')
