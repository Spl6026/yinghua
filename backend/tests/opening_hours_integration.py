"""以真實 MariaDB、HTTP 與伺服器端渲染驗證營業時間週表的保存與越權拒絕；只使用隔離環境。"""
import copy
import json
import secrets
import sys
from api_integration import Client, ROOT, fixture, password, admin_body
from attractions_integration import body, upload, test_png, raw


def main():
    fixture(password, 'root')
    root, guest = Client('admin'), Client()
    assert root.login('admin')[0] == 200
    checks = []
    created = None
    keep = '--keep-for-browser' in sys.argv
    retained = False

    def check(name, condition):
        assert condition, name
        checks.append(name)

    week = {'timezone': 'Asia/Taipei', 'note': '假日依公告 <安全>', 'days': [{'day': day, 'status': 'unknown', 'periods': []} for day in range(7)]}
    week['days'][1] = {'day': 1, 'status': 'periods', 'periods': [{'opens': '09:00', 'closes': '12:00', 'nextDay': False}, {'opens': '14:00', 'closes': '18:00', 'nextDay': False}]}
    week['days'][2]['status'] = 'closed'
    week['days'][5] = {'day': 5, 'status': 'periods', 'periods': [{'opens': '22:00', 'closes': '02:00', 'nextDay': True}]}
    week['days'][0]['status'] = 'all_day'
    prefix = 'hours-' + secrets.token_hex(5)
    try:
        media = upload(root, test_png())[1]['media']
        value = body(media['id'], slug=prefix, status='published', openingHours=week)
        code, result, _ = root.call('POST', '/api/v1/admin/attractions', value)
        assert code == 201, (code, result)
        item = result['attraction'];created = item['id']
        path = '/api/v1/admin/attractions/' + created
        check('manual create stores structured schedule', item['openingHours'] == week)
        check('server generates compact summary', item['hours'] == '每週時段不同，詳見介紹')
        loaded = root.call('GET', path)[1]['attraction']
        check('admin reload retains lunch break cross-day and states', loaded['openingHours'] == week)
        public = guest.call('GET', '/api/v1/attractions/' + prefix)[1]['attraction']
        check('public API uses same schedule', public['openingHours'] == week)
        code, content, _ = raw(guest, '/places/' + prefix + '.html');html = content.decode()
        check('SSR renders weekly schedule without JS', code == 200 and '週一：09:00–12:00、14:00–18:00' in html and '週五：22:00–次日 02:00' in html and '週二：公休' in html)
        check('SSR encodes user note safely', '假日依公告 &lt;安全&gt;' in html and '假日依公告 <安全>' not in html)
        invalid = [None, dict(week, timezone='UTC'), dict(week, note='字' * 501)]
        duplicate = copy.deepcopy(week);duplicate['days'][6]['day'] = 0;invalid.append(duplicate)
        overlap = copy.deepcopy(week);overlap['days'][1]['periods'][1]['opens'] = '11:00';invalid.append(overlap)
        wrap = copy.deepcopy(week);wrap['days'][6] = {'day': 6, 'status': 'periods', 'periods': [{'opens': '22:00', 'closes': '02:00', 'nextDay': True}]};invalid.append(wrap)
        bad_time = copy.deepcopy(week);bad_time['days'][1]['periods'][0]['opens'] = '25:00';invalid.append(bad_time)
        for number, schedule in enumerate(invalid, 1):
            response = root.call('PUT', path, dict(value, version=item['version'], openingHours=schedule))
            check('invalid schedule rejected ' + str(number), response[0] == 422 and 'openingHours' in response[1].get('fieldErrors', {}))
        after = root.call('GET', path)[1]['attraction']
        check('invalid writes preserve version and original schedule', after['version'] == item['version'] and after['openingHours'] == week)
        legacy = dict(value, version=item['version'], hours='不應覆蓋的新摘要');legacy.pop('openingHours')
        code, result, _ = root.call('PUT', path, legacy);assert code == 200
        item = result['attraction']
        check('older client preserves structured schedule and summary', item['openingHours'] == week and item['hours'] == '每週時段不同，詳見介紹')
        check('stale structured write rejected', root.call('PUT', path, dict(value, version=1))[0] == 409)
        viewer_body = admin_body('hoursview' + secrets.token_hex(4))
        viewer_id = root.call('POST', '/api/v1/admin/admins', viewer_body)[1]['admin']['id']
        fixture("UPDATE admin_users SET must_change_password=0 WHERE id='" + viewer_id + "'")
        viewer = Client('admin');assert viewer.login(viewer_body['account'])[0] == 200
        check('viewer cannot change weekly hours', viewer.call('PUT', path, dict(value, version=item['version']))[0] == 403)
        root.call('POST', root.auth + 'preview', {'adminId': viewer_id})
        check('preview cannot change weekly hours', root.call('PUT', path, dict(value, version=item['version']))[1].get('code') == 'PREVIEW_READ_ONLY')
        root.call('DELETE', root.auth + 'preview', {})
        stored = fixture("SELECT JSON_VALID(opening_hours) AS valid FROM attractions WHERE id='" + created + "'")[0]
        check('real MariaDB stores valid JSON', int(stored['valid']) == 1)
        unknown = {'timezone': 'Asia/Taipei', 'note': '依店家公告', 'days': [{'day': day, 'status': 'unknown', 'periods': []} for day in range(7)]}
        code, result, _ = root.call('PUT', path, dict(value, version=item['version'], openingHours=unknown));assert code == 200
        check('unknown hours stay distinct from closed', result['attraction']['openingHours'] == unknown and result['attraction']['hours'] == '依店家公告')
        current = result['attraction']
        code, result, _ = root.call('PUT', path, dict(value, version=current['version']));assert code == 200
        check('structured edit and public republish retain changes', guest.call('GET', '/api/v1/attractions/' + prefix)[1]['attraction']['openingHours'] == week)
        if keep:
            (ROOT / 'artifacts/opening-hours-browser.json').write_text(json.dumps({'rootPassword': password, 'id': created, 'slug': prefix}, ensure_ascii=False), encoding='utf-8')
            retained = True
    finally:
        if created and not retained:
            current = root.call('GET', '/api/v1/admin/attractions/' + created)[1]['attraction']
            assert root.call('DELETE', '/api/v1/admin/attractions/' + created, {'version': current['version']})[0] == 200
    (ROOT / 'artifacts/opening-hours-results.json').write_text(json.dumps(checks, ensure_ascii=False, indent=2), encoding='utf-8')
    print(json.dumps({'checks': len(checks), 'results': checks}, ensure_ascii=False))


if __name__ == '__main__':
    main()
