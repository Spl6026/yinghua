"""針對獨立審查發現的問題做回歸測試；只使用隔離的 HTTP 與 MariaDB。"""
import json
import secrets
import subprocess
from api_integration import Client, ROOT, fixture, password, admin_body


def main():
    fixture(password, 'root')
    root = Client('admin')
    assert root.login('admin')[0] == 200
    suffix = secrets.token_hex(5)
    checks, created = [], []

    def check(label, condition):
        assert condition, label
        checks.append(label)

    try:
        name = 'review-whitelist-' + suffix
        for key in ('0', '42', '-1', 'unexpected'):
            code, data, _ = root.call('POST', '/api/v1/admin/attractions',
                {'name': name, 'category': '景點', 'status': 'draft', key: 'must not disappear'})
            if data.get('attraction'):
                created.append(('attractions', data['attraction']))
            check('unknown JSON key rejected: ' + key, code == 422 and key in data.get('fieldErrors', {}))
        check('invalid JSON commands create no rows', fixture("SELECT COUNT(*) AS total FROM attractions WHERE name='" + name + "'")[0]['total'] == 0)
        account = '\U0001f600' * 50 + suffix
        value = dict(admin_body(account), email='review-unicode-' + suffix + '@example.test', displayName='Unicode帳號驗收')
        code, data, _ = root.call('POST', '/api/v1/admin/admins', value)
        check('sixty Unicode character account accepted', code == 201)
        created.append(('admins', data['admin']))
        check('account retained without UTF-16 truncation', data['admin']['account'] == account)
        check('sixty Unicode character account can log in', Client('admin').login(account)[0] == 200)
        check('sixty-one character account rejected at API', root.call('POST', '/api/v1/admin/admins', dict(value, account=account+'x'))[0] == 422)
        result = subprocess.run(['docker', 'compose', 'exec', '-T', 'app-test', 'php', 'tests/root_identity_constraints.php'],
            cwd=ROOT, capture_output=True, text=True, encoding='utf-8', check=True)
        check('sixteen root DB probes passed and rolled back', '16 root identity checks passed' in result.stdout)
    finally:
        for kind, item in created:
            code, data, _ = root.call('GET', '/api/v1/admin/' + kind + '/' + item['id'])
            if code == 200:
                current = data['attraction' if kind == 'attractions' else 'admin']
                assert root.call('DELETE', '/api/v1/admin/' + kind + '/' + item['id'], {'version': current['version']})[0] == (200 if kind == 'attractions' else 204)
    (ROOT/'artifacts/full-recheck-regressions.json').write_text(json.dumps(checks, indent=2), encoding='utf-8')
    print(json.dumps({'reviewRegressionChecks': len(checks), 'rootConstraintSubchecks': 16}))


if __name__ == '__main__':
    main()
