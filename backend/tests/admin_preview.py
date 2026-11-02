"""以真實 HTTP 與 MariaDB 驗證管理員的唯讀模擬檢視；只使用 yinghua_test。"""
import concurrent.futures
import json
import secrets
from pathlib import Path

from api_integration import Client, admin_body, fixture, password

BASE = '/api/v1/admin/auth/preview'
checks = []


def check(name, condition):
    if not condition:
        raise AssertionError(name)
    checks.append(name)


def main():
    fixture(password, 'root')
    fixture('DELETE FROM auth_rate_limits')
    suffix = secrets.token_hex(4)
    root = Client('admin')
    check('root login', root.login('admin')[0] == 200)
    owner = root.call('GET', root.auth + 'me')[1]['admin']
    root_id = owner['id']
    control = Client('admin')
    check('independent root session login', control.login('admin')[0] == 200)
    guest = Client('admin')
    check('anonymous cannot list or start or stop', all(guest.call(method, BASE, {} if method != 'GET' else None)[0] == 401 for method in ['GET', 'POST', 'DELETE']))
    member = Client()
    check('member namespace cannot authenticate preview', member.call('GET', BASE)[0] == 401)
    ids = []
    for index in range(3):
        name = 'preview' + str(index) + suffix
        code, body, _ = control.call('POST', '/api/v1/admin/admins', admin_body(name))
        check('create preview target ' + str(index), code == 201)
        ids.append(body['admin']['id'])
    target_id, deleted_id, disabled_id = ids
    fixture("UPDATE admin_users SET status='disabled' WHERE id='" + disabled_id + "'")
    target = Client('admin')
    check('normal target login', target.login('preview0' + suffix)[0] == 200)
    check('first password restriction remains for actual target', target.call('GET', BASE)[0] == 403)
    fixture("UPDATE admin_users SET must_change_password=0 WHERE id='" + target_id + "'")
    check('ordinary admin cannot list or start or stop', all(target.call(method, BASE, {'adminId': deleted_id} if method == 'POST' else {} if method == 'DELETE' else None)[0] == 403 for method in ['GET', 'POST', 'DELETE']))
    candidates = root.call('GET', BASE)[1]['targets']
    check('minimal target fields', all(set(item) == {'id', 'account', 'displayName'} for item in candidates))
    check('root and disabled excluded', not any(item['id'] in [root_id, disabled_id] for item in candidates))
    for name, body in [('root', {'adminId': root_id}), ('disabled', {'adminId': disabled_id}), ('missing', {'adminId': '00000000-0000-0000-0000-000000000000'}), ('invalid ID', {'adminId': 'invalid'}), ('extra privileges', {'adminId': target_id, 'isRoot': True})]:
        check('reject target ' + name, root.call('POST', BASE, body)[0] == 422)
    check('start requires CSRF', root.call('POST', BASE, {'adminId': target_id}, csrf=False)[0] == 403)
    check('start rejects cross-origin', root.call('POST', BASE, {'adminId': target_id}, extra={'Origin': 'https://attacker.test'})[0] == 403)
    before = fixture("SELECT id,expires_at FROM sessions WHERE admin_id='" + root_id + "' AND revoked_at IS NULL ORDER BY created_at DESC,id")
    stale = root.clone()
    check('root enters preview', root.call('POST', BASE, {'adminId': target_id})[0] == 200)
    check('old token invalid after entry', stale.call('GET', stale.auth + 'me')[0] == 401)
    me = root.call('GET', root.auth + 'me')[1]
    check('effective identity is target and operator stays root', me['admin']['id'] == target_id and me['preview']['operator']['id'] == root_id and me['preview']['active'] and me['preview']['readOnly'])
    session = fixture("SELECT id,admin_id,preview_admin_id,expires_at FROM sessions WHERE preview_admin_id='" + target_id + "' AND revoked_at IS NULL")
    check('session retains owner and absolute expiry', len(session) == 1 and session[0]['admin_id'] == root_id and any(row['id'] == session[0]['id'] and row['expires_at'] == session[0]['expires_at'] for row in before))
    check('other root session unaffected', control.call('GET', control.auth + 'me')[1]['admin']['id'] == root_id)
    check('target permission restriction applies to API', root.call('GET', '/api/v1/admin/admins')[0] == 403)
    matrix = control.call('GET', '/api/v1/admin/admins/' + target_id + '/permissions')[1]
    rights = [{**right, 'allowed': True} for right in matrix['permissions']]
    check('real root can grant target rights', control.call('PUT', '/api/v1/admin/admins/' + target_id + '/permissions', {'version': matrix['version'], 'permissions': rights})[0] == 204)
    check('new permissions apply during preview', root.call('GET', '/api/v1/admin/admins')[0] == 200 and all(right['allowed'] for right in root.call('GET', root.auth + 'me')[1]['permissions']))
    check('all rights still cannot grant impersonation to ordinary admin', target.call('POST', BASE, {'adminId': deleted_id})[0] == 403)
    untouched = fixture("SELECT password_hash,version,permissions_version FROM admin_users WHERE id='" + target_id + "'")[0]
    paths = [
        ('create', 'POST', '/api/v1/admin/admins', admin_body('blocked' + suffix)),
        ('update', 'PUT', '/api/v1/admin/admins/' + target_id, {}),
        ('delete', 'DELETE', '/api/v1/admin/admins/' + target_id, {}),
        ('permissions', 'PUT', '/api/v1/admin/admins/' + target_id + '/permissions', {}),
        ('password', 'POST', root.auth + 'change-password', {}),
        ('reset password', 'POST', root.auth + 'reset-password', {}),
        ('request reset', 'POST', root.auth + 'forgot-password', {}),
        ('login', 'POST', root.auth + 'login', {}),
    ]
    for name, method, path, body in paths:
        code, result, _ = root.call(method, path, body)
        check('read-only blocks ' + name, code == 403 and result['code'] == 'PREVIEW_READ_ONLY')
    check('blocked writes leave target unchanged', fixture("SELECT password_hash,version,permissions_version FROM admin_users WHERE id='" + target_id + "'")[0] == untouched)
    check('preview switch is audited under root', fixture("SELECT actor_admin_id,resource_id FROM audit_logs WHERE action='preview-start' AND resource_id='" + target_id + "' ORDER BY id DESC LIMIT 1")[0] == {'actor_admin_id': root_id, 'resource_id': target_id})
    fixture("CREATE TRIGGER test_preview_audit_failure BEFORE INSERT ON audit_logs FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='test failure'")
    try:
        check('audit failure rejects switch', root.call('POST', BASE, {'adminId': deleted_id})[0] == 500)
    finally:
        fixture('DROP TRIGGER test_preview_audit_failure')
    check('failed switch rolls back identity and token', root.call('GET', root.auth + 'me')[1]['admin']['id'] == target_id)
    fixture("UPDATE admin_users SET status='disabled' WHERE id='" + target_id + "'")
    invalid = root.call('GET', root.auth + 'me')
    check('disabled target gives no rights and keeps exit metadata', invalid[0] == 200 and not invalid[1]['preview']['targetAvailable'] and invalid[1]['permissions'] == [])
    check('disabled target cannot read business API', root.call('GET', '/api/v1/admin/admins')[0] == 403)
    check('disabled target can still acquire CSRF to exit', root.call('GET', root.auth + 'csrf')[0] == 200)
    check('exit requires CSRF', root.call('DELETE', BASE, {}, csrf=False)[0] == 403)
    check('exit rejects cross-origin', root.call('DELETE', BASE, {}, extra={'Origin': 'https://attacker.test'})[0] == 403)
    stale = root.clone()
    check('exit works without target permissions', root.call('DELETE', BASE, {})[0] == 200)
    restored = root.call('GET', root.auth + 'me')[1]
    check('exit restores original root', restored['admin']['id'] == root_id and not restored['preview']['active'] and len(restored['permissions']) == 40 and all(right['allowed'] for right in restored['permissions']))
    check('old preview token invalid after exit', stale.call('GET', stale.auth + 'me')[0] == 401)
    check('fresh account can be previewed before first password change', root.call('POST', BASE, {'adminId': deleted_id})[0] == 200 and root.call('GET', root.auth + 'me')[0] == 200)
    check('fresh preview uses normal permissions without a first-login gate', root.call('GET', '/api/v1/admin/admins')[1]['code'] == 'FORBIDDEN')
    check('preview does not create actual target session', not fixture("SELECT id FROM sessions WHERE admin_id='" + deleted_id + "'"))
    version = control.call('GET', '/api/v1/admin/admins/' + deleted_id)[1]['admin']['version']
    check('delete current target', control.call('DELETE', '/api/v1/admin/admins/' + deleted_id, {'version': version})[0] == 204)
    check('deleted target cannot read and can exit', not root.call('GET', root.auth + 'me')[1]['preview']['targetAvailable'] and root.call('GET', '/api/v1/admin/admins')[0] == 403 and root.call('DELETE', BASE, {})[0] == 200)
    check('deleted target unavailable for new preview', root.call('POST', BASE, {'adminId': deleted_id})[0] == 422)
    fixture("UPDATE admin_users SET status='active' WHERE id='" + target_id + "'")
    raced = [root.clone(), root.clone()]
    with concurrent.futures.ThreadPoolExecutor(2) as pool:
        responses = list(pool.map(lambda client: client.call('POST', BASE, {'adminId': target_id}), raced))
    check('concurrent mode switches accept one token only', sorted(response[0] for response in responses) == [200, 401])
    winner = raced[next(index for index, response in enumerate(responses) if response[0] == 200)]
    check('logout during preview revokes root session', winner.call('POST', winner.auth + 'logout', {})[0] == 204 and winner.call('GET', winner.auth + 'me')[0] == 401)
    check('independent root starts another preview', control.call('POST', BASE, {'adminId': target_id})[0] == 200)
    fixture(password, 'root')
    check('root recovery revokes preview sessions', control.call('GET', control.auth + 'me')[0] == 401)
    output = Path(__file__).resolve().parents[2] / 'artifacts' / 'admin-preview-results.json'
    output.write_text(json.dumps({'passed': len(checks), 'checks': checks}, ensure_ascii=False, indent=2), encoding='utf-8')
    print(json.dumps({'passed': len(checks)}, ensure_ascii=False))


if __name__ == '__main__':
    main()
