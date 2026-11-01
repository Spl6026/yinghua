"""[E-40] Named DB probe followed by real HTTP contracts; no root reset."""
import json
import subprocess
import sys
from api_integration import ROOT

state = json.loads((ROOT / 'artifacts/phase8-browser-fixture.json').read_text(encoding='utf8'))
if state.get('cleaned') or not state['prefix'].startswith('p8ui'):
    raise RuntimeError('Active owned fixture required')
result = subprocess.run(['docker', 'compose', 'exec', '-T', 'app-test', 'php', 'tests/content_fallback_probe.php'],
                        cwd=ROOT, input=json.dumps({key: state[key] for key in ['placeId', 'prefix']}),
                        text=True, encoding='utf8', capture_output=True)
if result.returncode:
    raise RuntimeError('DB probe failed: ' + result.stderr)
report = json.loads(result.stdout)
(ROOT / 'artifacts/phase15/fallback-db-probe.json').write_text(json.dumps(report, indent=2), encoding='utf8')
print(json.dumps(report), flush=True)
subprocess.run([sys.executable, '-X', 'utf8', 'backend/tests/content_fallback_integration.py'], cwd=ROOT, check=True)
