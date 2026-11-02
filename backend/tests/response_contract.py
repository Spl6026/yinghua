"""以真實 HTTP 驗證回應欄位契約；重構 DTO 時不能破壞原生 JS 使用的格式。"""
import json
import re
import secrets

from api_integration import Client, ROOT, admin_body, challenge, fixture, password, register_body


SUMMARY = {'id', 'kind', 'account', 'email', 'displayName', 'status', 'isRoot', 'roleId',
           'mustChangePassword', 'version', 'permissionsVersion'}
checks = []


def check(name, condition):
    if not condition:
        raise AssertionError(name)
    checks.append(name)


def shape(name, response, status, fields):
    code, data, headers = response
    check(name, code == status and set(data) == fields | {'requestId'}
          and re.fullmatch(r'[a-f0-9]{32}', data.get('requestId', '')) is not None
          and headers.get('X-Request-Id') == data['requestId'])
    return data


def main():
    fixture(password, 'root')
    fixture('DELETE FROM auth_rate_limits')
    suffix = 'contract' + secrets.token_hex(5)
    member = Client()
    shape('csrf fields', member.call('GET', member.auth + 'csrf'), 200, {'token', 'expiresIn', 'termsVersion'})
    png = shape('image challenge fields', member.call('GET', member.auth + 'captcha'), 200,
                {'challengeToken', 'expiresAt', 'imageDataUrl'})
    check('image challenge stays PNG', png['imageDataUrl'].startswith('data:image/png;base64,'))
    email = suffix + '@example.test'
    token, answer = challenge(member, email)
    registered = shape('member registration envelope', member.call('POST', member.auth + 'register', register_body(member, email, token, answer)),
                       201, {'member', 'csrfToken'})
    check('safe member summary', set(registered['member']) == SUMMARY and registered['member']['kind'] == 'member')
    shape('member me envelope', member.call('GET', member.auth + 'me'), 200, {'member'})

    root = Client('admin')
    logged_in = shape('admin login envelope', root.login('admin'), 200, {'admin', 'permissions', 'csrfToken'})
    check('safe admin summary', set(logged_in['admin']) == SUMMARY and logged_in['admin']['kind'] == 'admin')
    me = shape('admin me envelope', root.call('GET', root.auth + 'me'), 200, {'admin', 'permissions', 'preview'})
    check('preview metadata fields', set(me['preview']) == {'active', 'readOnly', 'targetAvailable', 'targetId', 'operator'}
          and set(me['preview']['operator']) == {'id', 'account', 'displayName'})
    body = admin_body(suffix)
    created = shape('admin create envelope', root.call('POST', '/api/v1/admin/admins', body), 201, {'admin'})
    admin_id = created['admin']['id']
    path = '/api/v1/admin/admins/' + admin_id
    check('create summary allowlist', set(created['admin']) == SUMMARY)
    page = shape('admin pagination fields', root.call('GET', '/api/v1/admin/admins?q=' + suffix), 200,
                 {'items', 'total', 'page', 'pageSize'})
    check('pagination summary and count', page['total'] == 1 and len(page['items']) == 1 and set(page['items'][0]) == SUMMARY)
    current = shape('admin detail envelope', root.call('GET', path), 200, {'admin'})['admin']
    roles = shape('role envelope', root.call('GET', '/api/v1/admin/roles'), 200, {'roles'})['roles']
    check('role template fields', bool(roles) and all(set(role) == {'id', 'name', 'permissions'} and role['id'] != 'root' for role in roles))
    catalog = shape('permission catalog fields', root.call('GET', '/api/v1/admin/permissions/catalog'), 200, {'permissions', 'targets'})
    check('catalog target allowlist', all(set(target) == {'id', 'account', 'displayName', 'isRoot'} for target in catalog['targets']))
    matrix = shape('permission matrix fields', root.call('GET', path + '/permissions'), 200, {'adminId', 'version', 'isRoot', 'permissions'})
    check('matrix values remain typed', matrix['adminId'] == admin_id and type(matrix['version']) is int
          and len(matrix['permissions']) == 40 and all(set(right) == {'module', 'action', 'allowed'} and type(right['allowed']) is bool for right in matrix['permissions']))

    duplicate = root.call('POST', '/api/v1/admin/admins', body)
    check('duplicate account maps to field conflict', duplicate[0] == 409 and duplicate[1]['fieldErrors'] == {'account': '此資料已使用。'})
    body['account'] += 'other'
    duplicate = root.call('POST', '/api/v1/admin/admins', body)
    check('duplicate email maps to field conflict', duplicate[0] == 409 and duplicate[1]['fieldErrors'] == {'email': '此資料已使用。'})
    check('database details stay private', all(word not in json.dumps(duplicate[1]) for word in ['SQLSTATE', 'PDOException', 'admin_users', 'AccountConflict']))

    edit = {key: current[key] for key in ['account', 'displayName', 'email', 'roleId', 'status', 'version']}
    edit['displayName'] = '契約驗證'
    shape('admin update envelope', root.call('PUT', path, edit), 200, {'admin'})
    targets = shape('preview target envelope', root.call('GET', root.auth + 'preview'), 200, {'targets'})
    check('preview target allowlist', all(set(target) == {'id', 'account', 'displayName'} for target in targets['targets']))
    fixture("UPDATE admin_users SET must_change_password=0 WHERE id='" + admin_id + "'")
    shape('preview switch envelope', root.call('POST', root.auth + 'preview', {'adminId': admin_id}), 200, {'csrfToken'})
    preview = shape('preview identity envelope', root.call('GET', root.auth + 'me'), 200, {'admin', 'permissions', 'preview'})
    check('owner remains separate from target', preview['admin']['id'] == admin_id
          and preview['preview']['operator']['id'] == logged_in['admin']['id'] and preview['preview']['readOnly'] is True)
    shape('preview exit envelope', root.call('DELETE', root.auth + 'preview', {}), 200, {'csrfToken'})
    check('204 delete has no JSON', root.call('DELETE', path, {})[:2] == (204, {}))
    root.call('POST', root.auth + 'logout', {})
    member.call('POST', member.auth + 'logout', {})
    (ROOT / 'artifacts/layering-response-results.json').write_text(json.dumps({'checks': len(checks), 'passed': checks}, ensure_ascii=False, indent=2), encoding='utf-8')
    print(f'{len(checks)} response contract checks passed against isolated HTTP/MariaDB.')


if __name__ == '__main__':
    main()
