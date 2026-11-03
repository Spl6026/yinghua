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
    assert len(operations) == 115 and len(pages) == 48 and len(migrations) == 21
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


def suites(dependency_path, vocabulary, browser=False, full=False):
    py = [sys.executable, '-X', 'utf8']
    def test(name, *args):
        return py + ['backend/tests/' + name + '.py', *args]
    def php_test(name):
        return ['docker', 'compose', 'exec', '-T', 'app-test', 'php', 'tests/' + name + '.php']
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
        # 以下需要一般設定已還原（policy-consent 會暫時改動條款版本）。
        ('policy-pages', test('policy_pages_integration')),
        ('attractions', test('attractions_integration')),
        ('member-persistence', php_test('member_resources_persistence')),
        ('publication-alias-order', php_test('publication_alias_order')),
        ('publication-clock', php_test('public_content_clock')),
        ('seo-index', test('phase12_integration', '--schema-vocabulary', vocabulary)),
        ('http-assets', test('phase14_assets')),
        ('html-integrity', test('frontend_integrity', '--base-url', 'http://localhost:8081')),
    ] + ([
        # 需要 Node、Playwright 與 Chromium；以 --browser 開啟，結果寫入 artifacts/browser-smoke。
        ('browser-smoke', test('browser_smoke')),
    ] if browser else []) + (full_regression(test, php_test, dependency_path, vocabulary) if full else [])


# 以 --full 另外執行的完整回歸：上面固定套件沒涵蓋的所有測試腳本都在這裡，各有固定位置，不再散落在只能手動執行的小型執行器裡。
# 其中幾支會重建 app-test／web-test 容器或資料庫（*_restart、*_persistence），耗時較長。
def full_regression(test, php_test, dependency_path, vocabulary):
    output = str(ROOT / 'artifacts/full-regression')
    (ROOT / 'artifacts/full-regression').mkdir(parents=True, exist_ok=True)
    return [
        # 以 OpenAPI 驗證回應的契約測試（含 api_integration、景點、電影等 21 組）與公開消息／優惠／首頁。
        ('contracts-and-content', test('phase6_regression', '--dependency-path', dependency_path)),
        ('member-regression', test('phase7_member_regression', '--dependency-path', dependency_path)),
        ('member-restart', test('member_resources_restart')),
        ('phase9-content-admin', test('phase9_integration')),
        ('phase10-routes', test('phase10_integration')),
        ('phase11-itineraries', test('phase11_integration')),
        ('publication-diagnostics', test('publication_diagnostics')),
        ('public-content-seed', test('public_content_seed_fresh')),
        ('public-content-restart', test('public_content_restart', '--dependency-path', dependency_path)),
        ('content-persistence', test('content_persistence')),
        ('seo-settings', test('seo_settings_integration', '--dependency-path', dependency_path)),
        ('seo-publication', test('seo_publication_integration', '--dependency-path', dependency_path)),
        ('seo-settings-restart', test('seo_settings_restart')),
        ('seo-head-inventory', test('seo_head_inventory')),
        ('published-inventory', test('phase12_published_inventory', '--schema-vocabulary', vocabulary,
                                     '--output', output + '/published-inventory.json')),
        ('session-deadlines', test('session_deadlines_integration', '--output', output + '/session-deadlines.json')),
        ('health', test('health_integration')),
        ('health-contract', test('phase8_health_contract', '--dependency-path', dependency_path)),
        ('mail-failure', test('mail_failure')),
        ('schema-film', test('film_schema_upgrade')),
        ('schema-member', test('member_resources_schema')),
        ('schema-public-content', test('public_content_schema_upgrade')),
        ('schema-seo', test('seo_settings_schema')),
        ('db-accounts', php_test('account_persistence')),
        ('db-root-identity', php_test('root_identity_constraints')),
        ('db-attractions', php_test('attraction_constraints')),
        ('db-films', php_test('film_persistence')),
        ('db-public-content', php_test('public_content_persistence')),
        ('db-seo-settings', php_test('seo_settings_persistence')),
        ('queries-member', php_test('member_resources_queries')),
        ('queries-itineraries', php_test('phase11_queries')),
        ('queries-public-content', php_test('public_content_queries')),
    ]


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('mode', choices=['inventory', 'integration'])
    parser.add_argument('--dependency-path')
    parser.add_argument('--schema-vocabulary')
    parser.add_argument('--browser', action='store_true', help='另外執行瀏覽器冒煙測試')
    parser.add_argument('--full', action='store_true', help='另外執行完整回歸（所有其餘測試腳本，耗時較長）')
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
            # 各套件把結果與暫存的 fixture 寫進 artifacts 底下的子資料夾；artifacts 是可清理的本機目錄，先確保資料夾存在。
            for folder in ('phase10', 'phase11', 'phase12', 'phase13', 'phase14', 'phase15'):
                (ROOT / 'artifacts' / folder).mkdir(parents=True, exist_ok=True)
            for name, command in suites(options.dependency_path, options.schema_vocabulary, options.browser, options.full):
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
        # 冒煙測試只代表頁面可正常開啟與基本操作，不等於完整的人工 Browser 驗收，所以另立欄位。
        report['browserSmoke'] = options.mode == 'integration' and options.browser
        report['fullRegression'] = options.mode == 'integration' and options.full
        report['passed'] = True
    finally:
        (target / 'report.json').write_text(json.dumps(report, indent=2), encoding='utf8')
        print(str(target), flush=True)


if __name__ == '__main__':
    main()
