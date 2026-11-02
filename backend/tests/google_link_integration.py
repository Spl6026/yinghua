"""以真實 MariaDB 與 HTTP 驗證 Google 地點關聯的確認流程，包含繞過前端、越權與版本衝突。"""
import json
import secrets
import sys
from api_integration import Client, ROOT, fixture, password, admin_body
from attractions_integration import body


def main():
    fixture(password, 'root')
    root = Client('admin');assert root.login('admin')[0] == 200
    identifier = None
    retained = False
    checks = []

    def check(name, result):
        assert result, name
        checks.append(name)

    def put(value, version, expected=200):
        code, result, _ = root.call('PUT', '/api/v1/admin/attractions/'+identifier, dict(value, version=version))
        assert code == expected, (code, result.get('message'))
        return result.get('attraction')

    value = body('', slug='google-link-'+secrets.token_hex(4), googlePlaceId='ChIJ_test_original')
    try:
        code, result, _ = root.call('POST', '/api/v1/admin/attractions', value)
        check('new link requires explicit confirmation', code == 422 and 'googlePlaceId' in result.get('fieldErrors', {}))
        code, result, _ = root.call('POST', '/api/v1/admin/attractions', dict(value, googleLinkConfirmed=True));assert code == 201
        item = result['attraction'];identifier = item['id'];path = '/api/v1/admin/attractions/'+identifier
        check('confirmed link stored', item['googlePlaceId'] == value['googlePlaceId'])
        item = put(value, item['version']);check('unchanged linked place remains editable by older clients', item['googlePlaceId'] == value['googlePlaceId'])
        for key, changed in [('name','人工修改名稱'),('address','人工修改地址'),('latitude',24.7),('googlePlaceId','ChIJ_test_replaced')]:
            attempted = dict(value, **{key:changed})
            put(attempted, item['version'], 422)
            current = root.call('GET', path)[1]['attraction']
            check('unconfirmed '+key+' preserves version', current['version'] == item['version'])
        put(dict(value, googleLinkConfirmed='true'), item['version'], 422)
        check('confirmation string is rejected', root.call('GET', path)[1]['attraction']['version'] == item['version'])
        value['name'] = '人工核對後的地點'
        item = put(dict(value, googleLinkConfirmed=True), item['version']);check('explicit reconfirmation saves changed content', item['name'] == value['name'])
        put(dict(value, googleLinkConfirmed=True), item['version']-1, 409);check('stale confirmation cannot bypass version checks', True)
        viewer_body = admin_body('linkview'+secrets.token_hex(4));viewer_id = root.call('POST', '/api/v1/admin/admins', viewer_body)[1]['admin']['id']
        fixture("UPDATE admin_users SET must_change_password=0 WHERE id='"+viewer_id+"'")
        viewer = Client('admin');assert viewer.login(viewer_body['account'])[0] == 200
        check('viewer cannot confirm a link', viewer.call('PUT', path, dict(value, version=item['version'], googleLinkConfirmed=True))[0] == 403)
        root.call('POST', root.auth+'preview', {'adminId':viewer_id})
        check('preview cannot detach a link', root.call('PUT', path, dict(value, version=item['version'], googlePlaceId=''))[1].get('code') == 'PREVIEW_READ_ONLY')
        root.call('DELETE', root.auth+'preview', {})
        item = put(dict(value, googlePlaceId=''), item['version']);check('explicit detach clears persisted ID', item['googlePlaceId'] is None)
        item = put(dict(value, googleLinkConfirmed=True), item['version'])
        check('relink survives fresh read', root.call('GET', path)[1]['attraction']['googlePlaceId'] == value['googlePlaceId'])
        check('public API does not expose private Google association', 'googlePlaceId' not in Client().call('GET', '/api/v1/attractions')[1]['items'][0])
        if '--keep-for-browser' in sys.argv:
            (ROOT/'artifacts/google-link-browser.json').write_text(json.dumps({'rootPassword':password,'id':identifier,'slug':item['slug']}, ensure_ascii=False), encoding='utf-8');retained = True
    finally:
        if identifier and not retained:
            current = root.call('GET', '/api/v1/admin/attractions/'+identifier)[1]['attraction']
            assert root.call('DELETE', '/api/v1/admin/attractions/'+identifier, {'version':current['version']})[0] == 200
    (ROOT/'artifacts/google-link-results.json').write_text(json.dumps(checks, ensure_ascii=False, indent=2), encoding='utf-8')
    print(json.dumps({'checks':len(checks),'results':checks}, ensure_ascii=False))


if __name__ == '__main__':
    main()
