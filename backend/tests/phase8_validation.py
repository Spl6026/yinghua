"""依固定順序執行剩下的驗證測試組；遇到第一個失敗就停止，不重跑到通過為止。"""
import argparse,json,subprocess,sys,time
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2]
parser=argparse.ArgumentParser();parser.add_argument('--dependency-path',required=True);parser.add_argument('--start-at');options=parser.parse_args()
names=['phase7_member_regression','seo_settings_integration','seo_publication_integration','policy_pages_integration','seo_head_inventory']
if options.start_at:
    if options.start_at not in names:raise RuntimeError('Unknown fixed suite')
    names=names[names.index(options.start_at):]
results=[]
try:
    for name in names:
        start=time.monotonic();result=subprocess.run([sys.executable,str(ROOT/'backend/tests'/str(name+'.py')),'--dependency-path',options.dependency_path],cwd=ROOT,capture_output=True,text=True,encoding='utf8')
        (ROOT/('artifacts/phase8-'+name+'.log')).write_text(result.stdout+result.stderr,encoding='utf8')
        row={'suite':name,'exitCode':result.returncode,'seconds':round(time.monotonic()-start,2)};results.append(row);print(json.dumps(row),flush=True)
        if result.returncode:raise RuntimeError('Fixed suite failed: '+name+'; inspect its isolated evidence before resuming')
finally:(ROOT/'artifacts/phase8-validation-results.json').write_text(json.dumps(results,indent=2),encoding='utf8')
