"""可重現的交付檢查；不會重設 root 帳號，也不會還原主資料庫。

inventory 只讀取資料。integration 使用事先明確準備、正在運行的 yinghua_test
測試資料庫，遇到第一個失敗的測試組就停止，並保留紀錄檔。
瀏覽器與外部服務的驗收不在這裡，需另外檢查。
"""
import argparse
import hashlib
import json
import os
from pathlib import Path
import subprocess
import sys
import time

ROOT = Path(__file__).resolve().parents[2]
ORIGINAL = '56733e5c566009b02fb8d23c8949eec0c61f80c6'


def git(*args):
    return subprocess.check_output(['git', *args], cwd=ROOT)


def inventory():
    original = git('ls-tree', '-r', '--name-only', '-z', ORIGINAL).split(b'\0')
    paths = [p.decode('utf8') for p in original if p and
             (p.startswith(b'website/') or p.endswith(b'.xlsx'))]
    assert len(paths) == 69, 'Original delivery count changed'
    for path in paths:
        assert (ROOT / path).read_bytes() == git('show', ORIGINAL + ':' + path), path
    contract = json.loads((ROOT / 'backend/resources/openapi.json').read_text(encoding='utf8'))
    methods = {'get', 'post', 'put', 'patch', 'delete', 'head', 'options'}
    operations = [(path, method) for path, value in contract['paths'].items()
                  for method in value if method in methods]
    pages = list((ROOT / 'frontend/pages').rglob('*.html'))
    migrations = list((ROOT / 'backend/database/migrations').glob('*.sql'))
    assert len(operations) == 115 and len(pages) == 48 and len(migrations) == 20
    fonts = json.loads((ROOT / 'docs/FONT-ASSETS.json').read_text(encoding='utf8'))
    for row in fonts['files']:
        data = (ROOT / row['path']).read_bytes()
        assert len(data) == row['bytes'] and hashlib.sha256(data).hexdigest() == row['sha256']
    ignored = subprocess.run(['git', 'check-ignore', '-q', '.env'], cwd=ROOT).returncode == 0
    assert ignored, '.env must remain ignored'
    assert not git('ls-files', '.env').strip(), '.env must not be tracked'
    return {'originalFiles': len(paths), 'httpOperations': len(operations),
            'fixedPages': len(pages), 'migrations': len(migrations),
            'fontFiles': len(fonts['files']), 'envIgnored': ignored}


def suites(dependency_path, vocabulary):
    py = [sys.executable, '-X', 'utf8']
    def test(name, *args):
        return py + ['backend/tests/' + name + '.py', *args]
    return [
        ('php-unit', ['docker', 'compose', 'exec', '-T', 'app-test', 'php', '/var/www/tools/vendor/bin/phpunit', '-c', 'phpunit.xml']),
        ('php-static-architecture', ['docker', 'compose', 'exec', '-T', 'app-test', 'php', '/var/www/tools/vendor/bin/phpstan', 'analyse', '--no-progress']),
        ('frontend', ['docker', 'compose', 'run', '--rm', '--no-deps', 'frontend', 'npm', 'test']),
        ('operations', py + ['-m', 'unittest', 'discover', '-s', 'scripts/operations/tests']),
        ('anonymous-boundaries', test('phase15_access')),
        ('p1-contracts', test('phase12_regression', '--dependency-path', dependency_path)),
        ('search', test('phase13_integration', '--dependency-path', dependency_path)),
        ('content-fallback', test('content_fallback_validation')),
        ('operations-settings', test('phase14_integration', '--keep-browser')),
        ('policy-consent', test('phase14_policy_integration')),
        ('restore-settings', test('phase14_cleanup', '--settings-only')),
        ('seo-index', test('phase12_integration', '--schema-vocabulary', vocabulary)),
        ('http-assets', test('phase14_assets')),
        ('html-integrity', test('frontend_integrity', '--base-url', 'http://localhost:8081')),
    ]


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('mode', choices=['inventory', 'integration'])
    parser.add_argument('--dependency-path')
    parser.add_argument('--schema-vocabulary')
    options = parser.parse_args()
    # 每次使用新的目錄，避免把失敗的執行結果和之前通過的紀錄搞混。
    target = ROOT / 'artifacts/phase15' / (time.strftime('%Y%m%d-%H%M%S') + '-' + str(os.getpid()))
    target.mkdir(parents=True, exist_ok=False)
    report = {'commit': git('rev-parse', 'HEAD').decode().strip(), 'mode': options.mode,
              'passed': False, 'inventory': {}, 'suites': [], 'browserVerified': False,
              'externalServicesVerified': False}
    try:
        report['inventory'] = inventory()
        if options.mode == 'integration':
            if not options.dependency_path or not options.schema_vocabulary:
                raise ValueError('Explicit dependency and official vocabulary paths required')
            state = json.loads((ROOT / 'artifacts/phase8-browser-fixture.json').read_text(encoding='utf8'))
            if state.get('cleaned') or not state.get('prefix', '').startswith('p8ui'):
                raise ValueError('Prepare an active owned yinghua_test fixture first')
            for name, command in suites(options.dependency_path, options.schema_vocabulary):
                started = time.monotonic()
                with (target / (name + '.log')).open('w', encoding='utf8') as log:
                    result = subprocess.run(command, cwd=ROOT, stdout=log, stderr=subprocess.STDOUT)
                data = (target / (name + '.log')).read_bytes()
                row = {'suite': name, 'exitCode': result.returncode,
                       'seconds': round(time.monotonic() - started, 2),
                       'logSha256': hashlib.sha256(data).hexdigest()}
                report['suites'].append(row)
                print(json.dumps(row), flush=True)
                if result.returncode:
                    raise RuntimeError('Failed suite: ' + name + '; inspect log, do not auto-retry')
        report['passed'] = True
    finally:
        (target / 'report.json').write_text(json.dumps(report, indent=2), encoding='utf8')
        print(str(target), flush=True)


if __name__ == '__main__':
    main()
