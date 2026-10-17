"""[U-12] Excel／原型限制校正的真實 HTTP、MariaDB 與請求契約回歸。"""
import json
import secrets
import urllib.error
import urllib.request
from api_integration import Client, ROOT, BASE, ORIGIN, fixture, password, admin_body, challenge
from attractions_integration import body, test_png, raw


def main():
    fixture(password, 'root')
    root = Client('admin')
    assert root.login('admin')[0] == 200
    root_id = root.call('GET', root.auth + 'me')[1]['admin']['id']
    suffix = secrets.token_hex(5)
    checks, places, admins = [], [], []
    schemas = json.loads((ROOT / 'backend/resources/openapi.json').read_text(encoding='utf-8'))['components']['schemas']

    def check(name, condition):
        assert condition, name
        checks.append(name)

    def upload_without_optional_fields():
        root.refresh()
        boundary = 'spl-' + secrets.token_hex(12)
        data = b''
        for key, value in {'action': 'create', 'alt': '本案測試圖片'}.items():
            data += f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode()
        data += f'--{boundary}\r\nContent-Disposition: form-data; name="image"; filename="test.png"\r\nContent-Type: image/png\r\n\r\n'.encode() + test_png() + f'\r\n--{boundary}--\r\n'.encode()
        request = urllib.request.Request(BASE + '/api/v1/admin/media', data=data, method='POST', headers={
            'Origin': ORIGIN, 'X-CSRF-Token': root.token, 'Content-Type': 'multipart/form-data; boundary=' + boundary})
        try:
            response = root.http.open(request, timeout=20)
        except urllib.error.HTTPError as error:
            response = error
        return response.status, json.loads(response.read())

    try:
        minimal = {'name': '規格回歸 ' + suffix, 'category': '景點', 'status': 'published'}
        check('minimal create satisfies documented required keys', set(schemas['AttractionCreate']['required']) <= set(minimal))
        check('guest cannot publish minimal attraction', Client('admin').call('POST', '/api/v1/admin/attractions', minimal)[0] == 401)
        status, result, _ = root.call('POST', '/api/v1/admin/attractions', minimal)
        check('publish without address district image or openingHours', status == 201)
        item = result['attraction']; places.append(item['id'])
        check('slug generated without manual input', bool(item['slug']) and item['url'].endswith(item['slug'] + '.html'))
        check('optional fields remain empty without Google fallback', all(item[key] == '' for key in ('summary', 'description', 'address', 'district')))
        check('new image-free content does not invent original-delivery credits', item['imageSource'] == item['imageLicense'] == '')
        check('public API includes image-free attraction', Client().call('GET', '/api/v1/attractions/' + item['slug'])[0] == 200)
        status, html, _ = raw(Client(), item['url'])
        check('public page uses existing image placeholder', status == 200 and b'/assets/image-placeholder.svg' in html)
        audit = fixture("SELECT created_by,updated_by FROM attractions WHERE id='" + item['id'] + "'")[0]
        check('creation actor saved from authenticated identity', audit == {'created_by': root_id, 'updated_by': root_id})
        edit = dict(minimal, version=item['version'], slug='', address='', district='')
        status, result, _ = root.call('PUT', '/api/v1/admin/attractions/' + item['id'], edit)
        check('edit blank slug preserves original URL', status == 200 and result['attraction']['slug'] == item['slug'])
        item = result['attraction']
        check('stale version still rejected', root.call('PUT', '/api/v1/admin/attractions/' + item['id'], edit)[0] == 409)
        check('untrusted manual map URL rejected', root.call('PUT', '/api/v1/admin/attractions/' + item['id'], dict(edit, version=item['version'], mapUrl='https://example.com/maps'))[0] == 422)
        check('coordinate range remains enforced', root.call('PUT', '/api/v1/admin/attractions/' + item['id'], dict(edit, version=item['version'], latitude=91, longitude=120))[0] == 422)
        check('actor cannot be forged in JSON', root.call('POST', '/api/v1/admin/attractions', dict(minimal, created_by=root_id))[0] == 422)
        check('unknown district still rejected', root.call('POST', '/api/v1/admin/attractions', dict(minimal, district='其他行政區'))[0] == 422)
        check('foreign-key actor identity enforced', fixture("UPDATE attractions SET updated_by='ffffffff-ffff-4fff-8fff-ffffffffffff' WHERE id='" + item['id'] + "'", expect=False) != 0)
        check('district CHECK rejects direct invalid SQL', fixture("UPDATE attractions SET district='未知區' WHERE id='" + item['id'] + "'", expect=False) != 0)
        status, media = upload_without_optional_fields()
        check('upload omitting source and license accepted', status == 201)
        check('omitted media metadata saved as empty strings', media['media']['source'] == media['media']['license'] == '')
        check('media request contract only requires image action alt', set(schemas['MediaUpload']['required']) == {'image', 'action', 'alt'})

        for account in ('字' + suffix, '1' + suffix):
            value = admin_body(account)
            value['email'] = 'spec-' + secrets.token_hex(5) + '@example.test'
            status, data, _ = root.call('POST', '/api/v1/admin/admins', value)
            check('Unicode or numeric-prefix ordinary account accepted', status == 201)
            admin = data['admin']; admins.append(admin['id'])
        target_id = admins[0]
        current = root.call('GET', '/api/v1/admin/admins/' + target_id)[1]['admin']
        edit_admin = {key: current[key] for key in ('account', 'displayName', 'email', 'roleId', 'status', 'version')}
        renamed = '新' + suffix
        status, data, _ = root.call('PUT', '/api/v1/admin/admins/' + target_id, dict(edit_admin, account=renamed))
        check('ordinary account can be renamed', status == 200 and data['admin']['account'] == renamed)
        check('old login no longer works', Client('admin').login(edit_admin['account'])[0] == 401)
        target = Client('admin')
        check('new Unicode login works', target.login(renamed)[0] == 200)
        check('new administrator can use granted business rights immediately', target.call('GET', '/api/v1/admin/attractions')[0] == 200)
        version = data['admin']['version']
        check('duplicate account still rejected', root.call('PUT', '/api/v1/admin/admins/' + target_id, dict(edit_admin, account=admins_account(root, admins[1]), version=version))[0] == 409)
        check('reserved admin cannot be assigned to ordinary user', root.call('PUT', '/api/v1/admin/admins/' + target_id, dict(edit_admin, account='admin', version=version))[0] == 403)
        check('root update remains rejected', root.call('PUT', '/api/v1/admin/admins/' + root_id, dict(edit_admin, account='admin', version=1))[0] == 403)
        check('root rename blocked at database layer', fixture("UPDATE admin_users SET account='changed-root' WHERE id='" + root_id + "'", expect=False) != 0)
        check('root identity remains intact', root.call('GET', root.auth + 'me')[1]['admin']['account'] == 'admin')
        check('Unicode account unique index available', any(row.get('key') == 'account' for row in fixture("EXPLAIN SELECT id FROM admin_users WHERE account='" + renamed + "'")))
        check('all migrations completed', all(row['applied_at'] is not None for row in fixture('SELECT applied_at FROM schema_migrations')))
        # [U-13] Exercise the exact security-policy changes explicitly approved by the user.
        member = Client()
        email = 'spec-eight-' + suffix + '@example.test'
        token, answer = challenge(member, email)
        registration = dict(email=email, displayName='八字密碼驗證', password='Demo123!', passwordConfirmation='Demo123!',
            challengeToken=token, answer=answer, acceptTerms=True, termsVersion=member.refresh()['termsVersion'])
        check('eight-character member password with matching confirmation accepted', member.call('POST', member.auth + 'register', registration)[0] == 201)
        check('confirmation mismatch still rejected', member.call('POST', member.auth + 'change-password',
            dict(currentPassword='Demo123!', password='Changed8!', passwordConfirmation='different'))[0] == 422)
        check('empty confirmation rejects password update', member.call('POST', member.auth + 'change-password',
            dict(currentPassword='Demo123!', password='Changed8!', passwordConfirmation=''))[0] == 422)
        check('matching confirmation permits password update', member.call('POST', member.auth + 'change-password',
            dict(currentPassword='Demo123!', password='Changed8!', passwordConfirmation='Changed8!'))[0] == 204)
        check('seven-character password remains rejected', root.call('POST', '/api/v1/admin/admins',
            dict(admin_body('short' + suffix), password='1234567'))[0] == 422)
        for action in ('disable', 'delete', 'demote'):
            account = admin_body('self-' + action + '-' + suffix)
            account['password'] = 'Demo123!'
            status, data, _ = root.call('POST', '/api/v1/admin/admins', account)
            check('eight-character administrator password accepted: ' + action, status == 201)
            current = data['admin']; own_id = current['id']; admins.append(own_id)
            matrix = root.call('GET', '/api/v1/admin/admins/' + own_id + '/permissions')[1]
            for permission in matrix['permissions']:
                permission['allowed'] = (permission['module'], permission['action']) in {
                    ('admins', 'view'), ('admins', 'edit'), ('admins', 'delete'), ('permissions', 'view'), ('permissions', 'edit')}
            assert root.call('PUT', '/api/v1/admin/admins/' + own_id + '/permissions',
                {'version': matrix['version'], 'permissions': matrix['permissions']})[0] == 204
            own = Client('admin'); assert own.login(account['account'], 'Demo123!')[0] == 200
            if action == 'disable':
                values = {key: current[key] for key in ('account', 'displayName', 'email', 'roleId', 'status', 'version')}
                check('authorized ordinary admin can disable self', own.call('PUT', '/api/v1/admin/admins/' + own_id, dict(values, status='disabled'))[0] == 200)
                check('self-disable revokes existing session', own.call('GET', own.auth + 'me')[0] == 401)
            elif action == 'delete':
                check('authorized ordinary admin can soft-delete self', own.call('DELETE', '/api/v1/admin/admins/' + own_id, {'version': current['version']})[0] == 204)
                admins.remove(own_id)
                check('self-delete revokes existing session', own.call('GET', own.auth + 'me')[0] == 401)
            else:
                matrix = own.call('GET', '/api/v1/admin/admins/' + own_id + '/permissions')[1]
                for permission in matrix['permissions']:
                    permission['allowed'] = False
                check('authorized ordinary admin can surrender own permissions', own.call('PUT', '/api/v1/admin/admins/' + own_id + '/permissions',
                    {'version': matrix['version'], 'permissions': matrix['permissions']})[0] == 204)
                check('surrendered rights stop later writes', own.call('DELETE', '/api/v1/admin/admins/' + own_id, {})[0] == 403)
            check('fixed root retains last management rights after self-' + action,
                root.call('GET', root.auth + 'me')[1]['admin']['isRoot'] and root.call('GET', '/api/v1/admin/admins')[0] == 200)
    finally:
        for identifier in places:
            row = root.call('GET', '/api/v1/admin/attractions/' + identifier)[1]['attraction']
            assert root.call('DELETE', '/api/v1/admin/attractions/' + identifier, {'version': row['version']})[0] == 200
        for identifier in admins:
            assert root.call('DELETE', '/api/v1/admin/admins/' + identifier, {})[0] == 204
        root.call('POST', root.auth + 'logout', {})
    (ROOT / 'artifacts/specification-alignment-results.json').write_text(json.dumps(checks, ensure_ascii=False, indent=2), encoding='utf-8')
    print(json.dumps({'checks': len(checks), 'results': checks}, ensure_ascii=False))


def admins_account(root, identifier):
    return root.call('GET', '/api/v1/admin/admins/' + identifier)[1]['admin']['account']


if __name__ == '__main__':
    main()
