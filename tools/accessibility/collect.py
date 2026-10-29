"""[E-30] Export actual Orca speech and isolation metadata, never raw key-event logs."""
import argparse
import json
import re
import subprocess
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
TOOL = Path(__file__).resolve().parent
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--directory', type=Path, required=True)
parser.add_argument('--label', required=True)
options = parser.parse_args()
if not re.fullmatch(r'[a-z0-9-]+', options.label):
    parser.error('Use a short lowercase evidence label')
directory = options.directory.resolve()
if not directory.is_relative_to(ROOT / 'artifacts'):
    parser.error('Evidence must stay in ignored project artifacts')
directory.mkdir(parents=True, exist_ok=True)
target = directory / (options.label + '.json')
if target.exists():
    parser.error('Preserve existing evidence; choose a new label')
compose = ['docker', 'compose', '--project-directory', str(TOOL), '-f', str(TOOL / 'compose.yaml')]
def output(args):
    return subprocess.check_output(args, cwd=ROOT, text=True, encoding='utf8')
raw = output(compose + ['exec', '-T', 'desktop', 'cat', '/state/orca-debug.log'])
fixture = ROOT / 'artifacts/phase8-browser-fixture.json'
if fixture.exists():
    password = json.loads(fixture.read_text(encoding='utf8')).get('password')
    if password:
        raw = raw.replace(password, '[test password redacted]')
speech = []
for event in re.split(r'(?=^\d{2}:\d{2}:\d{2}\.\d+ - )', raw, flags=re.M):
    if ' - SPEECH OUTPUT: ' not in event:
        continue
    at, text = event.split(' - SPEECH OUTPUT: ', 1)
    text = text.split(" {'established'", 1)[0].strip().strip("'")
    speech.append({'containerTime': at, 'text': text})
container_id = output(compose + ['ps', '-q', 'desktop']).strip()
container = json.loads(output(['docker', 'inspect', container_id]))[0]
config = container['HostConfig']
versions = output(compose + ['exec', '-T', 'desktop', 'dpkg-query', '-W',
    'orca', 'firefox-esr', 'at-spi2-core', 'novnc', 'websockify', 'xvfb'])
isolation = {
    'user': container['Config']['User'], 'imageId': container['Image'],
    'readOnlyRoot': config['ReadonlyRootfs'], 'privileged': config['Privileged'],
    'capDrop': config['CapDrop'], 'cpuQuotaNano': config['NanoCpus'],
    'memoryBytes': config['Memory'], 'pidsLimit': config['PidsLimit'],
    'devices': config['Devices'], 'networkMode': config['NetworkMode'],
    'ports': config['PortBindings'],
    'mountTargets': [m['Destination'] for m in container['Mounts']],
    'securityOptions': config['SecurityOpt'],
    'health': container['State'].get('Health', {}).get('Status'),
}
assert isolation['user'] == '1000:1000' and isolation['readOnlyRoot']
assert not isolation['privileged'] and not isolation['devices']
assert 'ALL' in isolation['capDrop'] and isolation['networkMode'] != 'host'
assert isolation['ports']['6080/tcp'][0]['HostIp'] == '127.0.0.1'
assert set(isolation['mountTargets']) <= {'/state', '/run/secrets/vnc_password', '/tmp', '/run', '/home/spl'}
assert 'no-new-privileges:true' in isolation['securityOptions']
assert isolation['cpuQuotaNano'] == 750000000 and isolation['memoryBytes'] == 1073741824
assert isolation['pidsLimit'] == 512 and isolation['health'] == 'healthy'
record = {'createdAt': datetime.now(timezone.utc).isoformat(),
    'label': options.label, 'versions': versions, 'isolation': isolation,
    'speech': speech, 'scope': 'Orca/Firefox only; not a full AA certificate or Windows NVDA result'}
target.write_text(json.dumps(record, ensure_ascii=False, indent=2), encoding='utf8')
print(json.dumps({'label': options.label, 'speechEvents': len(speech),
    'evidence': str(target.relative_to(ROOT))}, ensure_ascii=True))
