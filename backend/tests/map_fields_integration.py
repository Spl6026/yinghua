"""[U-10／E-21] 官網／行政區保存與參考資料不落DB；不消耗 Google 額度。"""
import json
import secrets
from api_integration import Client, ROOT, fixture, password
from attractions_integration import body, upload, test_png, raw


def main():
    fixture(password, 'root')
    root = Client('admin');assert root.login('admin')[0] == 200
    checks = []
    identifier = None

    def check(name, result):
        assert result, name
        checks.append(name)

    try:
        media = upload(root, test_png())[1]['media']
        value = body(media['id'], slug='map-fields-'+secrets.token_hex(4), websiteUrl='http://example.test/original', district='東區', status='published')
        code, result, _ = root.call('POST', '/api/v1/admin/attractions', dict(value, district=''))
        check('unknown district stays blank and can be saved', code == 201 and result['attraction']['district'] == '')
        unknown = result['attraction']
        assert root.call('DELETE', '/api/v1/admin/attractions/' + unknown['id'], {'version': unknown['version']})[0] == 200
        value['slug'] += '-known'
        code, result, _ = root.call('POST', '/api/v1/admin/attractions', value);assert code == 201
        item = result['attraction'];identifier = item['id'];path = '/api/v1/admin/attractions/'+identifier
        check('HTTP website and selected district saved', item['websiteUrl'] == value['websiteUrl'] and item['district'] == '東區')
        loaded = root.call('GET', path)[1]['attraction']
        check('reload preserves original website protocol', loaded['websiteUrl'] == value['websiteUrl'])
        html = raw(Client(), '/places/'+item['slug']+'.html')[1].decode()
        check('public website remains HTTP safe external link', 'href="http://example.test/original"' in html and 'rel="noopener noreferrer"' in html)
        for number, url in enumerate(['javascript:alert(1)','ftp://example.test','https://user:secret@example.test'], 1):
            check('unsafe website rejected '+str(number), root.call('PUT', path, dict(value, version=item['version'], websiteUrl=url))[0] == 422)
        check('HTTP map link remains forbidden', root.call('PUT', path, dict(value, version=item['version'], mapUrl='http://example.test'))[0] == 422)
        check('Google reference cannot be submitted as a persistence field', root.call('PUT', path, dict(value, version=item['version'], editorialSummary='Google參考原文'))[0] == 422)
        after = root.call('GET', path)[1]['attraction']
        check('failed updates preserve website version and content', after['version'] == item['version'] and after['description'] == value['description'] and after['websiteUrl'] == value['websiteUrl'])
    finally:
        if identifier:
            item = root.call('GET', '/api/v1/admin/attractions/'+identifier)[1]['attraction']
            assert root.call('DELETE', '/api/v1/admin/attractions/'+identifier, {'version':item['version']})[0] == 200
    (ROOT/'artifacts/map-fields-results.json').write_text(json.dumps(checks, ensure_ascii=False, indent=2), encoding='utf-8')
    print(json.dumps({'checks':len(checks), 'results':checks}, ensure_ascii=False))


if __name__ == '__main__':
    main()
