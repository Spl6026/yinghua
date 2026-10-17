"""[U-11／E-22] 選填文案的真實 HTTP／MariaDB 保存、失敗與越權驗證。"""
import json
import secrets
import sys
from api_integration import Client, ROOT, fixture, password, admin_body
from attractions_integration import body, upload, test_png, raw


def main():
    fixture(password, 'root')
    root, guest = Client('admin'), Client()
    assert root.login('admin')[0] == 200
    slug = 'optional-' + secrets.token_hex(5)
    identifier, retained = None, False
    checks = []

    def check(name, result):
        assert result, name
        checks.append(name)

    def save(value, version):
        code, result, _ = root.call('PUT', '/api/v1/admin/attractions/' + identifier, dict(value, version=version))
        assert code == 200, (code, result.get('message'))
        return result['attraction']

    value = body('', slug=slug)
    del value['summary'], value['description']
    try:
        check('anonymous cannot create blank content', Client('admin').call('POST', '/api/v1/admin/attractions', value)[0] == 401)
        code, result, _ = root.call('POST', '/api/v1/admin/attractions', value)
        assert code == 201, (code, result.get('message'))
        item = result['attraction']; identifier = item['id']; path = '/api/v1/admin/attractions/' + identifier
        check('omitted content creates empty strings', item['summary'] == item['description'] == '')
        stored = fixture("SELECT summary,description FROM attractions WHERE id='" + identifier + "'")[0]
        check('MariaDB stores empty strings without a migration', stored == {'summary': '', 'description': ''})
        item = save(dict(value, summary='自填摘要', description='自填介紹'), item['version'])
        check('content can be filled later', root.call('GET', path)[1]['attraction']['description'] == '自填介紹')
        item = save(dict(value, summary=' \n ', description='\t '), item['version'])
        check('clearing content survives reload', root.call('GET', path)[1]['attraction']['summary'] == item['description'] == '')
        for summary, description in [('', '只有介紹'), ('只有摘要', '')]:
            item = save(dict(value, summary=summary, description=description), item['version'])
            check('fields are independently optional: ' + str(bool(summary)), item['summary'] == summary and item['description'] == description)
        item = save(value, item['version'])
        check('PUT omission clears content as documented', item['summary'] == item['description'] == '')
        for key, invalid in [('summary', None), ('description', []), ('summary', '字' * 501), ('description', '字' * 20001)]:
            code, result, _ = root.call('PUT', path, dict(value, version=item['version'], **{key: invalid}))
            check('invalid type or length rejected: ' + key + '/' + type(invalid).__name__, code == 422 and key in result.get('fieldErrors', {}))
        check('failed writes preserve version', root.call('GET', path)[1]['attraction']['version'] == item['version'])
        check('stale version still rejected', root.call('PUT', path, dict(value, version=item['version'] - 1))[0] == 409)
        viewer_value = admin_body('optionalview' + secrets.token_hex(4))
        viewer_id = root.call('POST', '/api/v1/admin/admins', viewer_value)[1]['admin']['id']
        fixture("UPDATE admin_users SET must_change_password=0 WHERE id='" + viewer_id + "'")
        viewer = Client('admin'); assert viewer.login(viewer_value['account'])[0] == 200
        check('viewer cannot clear content', viewer.call('PUT', path, dict(value, version=item['version']))[0] == 403)
        root.call('POST', root.auth + 'preview', {'adminId': viewer_id})
        try:
            check('preview cannot clear content', root.call('PUT', path, dict(value, version=item['version']))[1].get('code') == 'PREVIEW_READ_ONLY')
        finally:
            root.call('DELETE', root.auth + 'preview', {})
        code, data = upload(root, test_png())
        assert code == 201
        item = save(dict(value, mediaId=data['media']['id'], status='published'), item['version'])
        public = guest.call('GET', '/api/v1/attractions/' + slug)
        check('blank content publishes and returns as strings', public[0] == 200 and public[1]['attraction']['summary'] == public[1]['attraction']['description'] == '')
        code, html, _ = raw(guest, '/places/' + slug + '.html')
        check('static detail remains available', code == 200 and item['name'] in html.decode())
        item = save(dict(value, mediaId=data['media']['id']), item['version'])
        check('draft is no longer public', guest.call('GET', '/api/v1/attractions/' + slug)[0] == 404)
        if '--keep-for-browser' in sys.argv:
            (ROOT / 'artifacts/optional-content-browser.json').write_text(json.dumps({'rootPassword': password, 'id': identifier, 'slug': slug}), encoding='utf-8')
            retained = True
    finally:
        if identifier and not retained:
            current = root.call('GET', '/api/v1/admin/attractions/' + identifier)[1]['attraction']
            assert root.call('DELETE', '/api/v1/admin/attractions/' + identifier, {'version': current['version']})[0] == 200
    (ROOT / 'artifacts/optional-content-results.json').write_text(json.dumps(checks, ensure_ascii=False, indent=2), encoding='utf-8')
    print(json.dumps({'checks': len(checks), 'results': checks}, ensure_ascii=False))


if __name__ == '__main__':
    main()
