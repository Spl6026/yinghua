"""以測試自有的 yinghua_test 資料，驗證內容備援的 HTTP、MariaDB、發布與回應契約。"""
import copy
import json
import socket
import sys
import urllib.request
from api_integration import ROOT, Client, fixture
from attractions_integration import body
from phase8_browser_fixture import literal, publish

sys.path.insert(0, str(ROOT / '.cache/validation/python'))
import jsonschema

state = json.loads((ROOT / 'artifacts/phase8-browser-fixture.json').read_text(encoding='utf8'))
assert not state['cleaned']
admin, guest = Client('admin'), Client()
assert admin.login(state['prefix'], state['password'])[0] == 200
spec = json.loads((ROOT / 'backend/resources/openapi.json').read_text(encoding='utf8'))
validator = jsonschema.Draft202012Validator(spec)
checks, contracts = [], 0
path = '/api/v1/admin/attractions/' + state['placeId']
public = '/api/v1/attractions/' + state['prefix'] + '-place'


def check(name, value):
    assert value, name
    checks.append(name)


def call(client, method, url, value=None, expected=200, route=None):
    global contracts
    code, data, headers = client.call(method, url, value)
    check(method + ' ' + url + ' status ' + str(expected), code == expected)
    operation = spec['paths'][route or url.split('?')[0]][method.lower()]
    schema = operation['responses'][str(code)]
    if '$ref' in schema:
        schema = spec['components']['responses'][schema['$ref'].split('/')[-1]]
    schema = schema['content']['application/json']['schema']
    check('response contract', not list(validator.evolve(schema=schema).iter_errors(data)))
    contracts += 1
    return data, headers


def save(summary, description):
    current = admin.call('GET', path)[1]['attraction']
    value = body('', slug=state['prefix'] + '-place', name=state['prefix'] + ' 景點驗收',
                 status='published', summary=summary, description=description, version=current['version'])
    result, _ = call(admin, 'PUT', path, value, route='/api/v1/admin/attractions/{id}')
    check('editor values stay authored', result['attraction']['summary'] == summary and result['attraction']['description'] == description)
    stored = fixture('SELECT summary,description FROM attractions WHERE id=' + literal(state['placeId']))[0]
    check('no derived text persisted', stored == {'summary': summary, 'description': description})


publish()
try:
    save('', '第一段網站介紹\n\n' + '竹' * 130)
    value, _ = call(guest, 'GET', public, route='/api/v1/attractions/{slug}')
    item = value['attraction']
    check('excerpt 120 Unicode chars', len(item['summary']) == 120 and item['summary'].endswith('…'))
    check('source and authored values explicit', item['contentSource']['summary'] == 'description-excerpt' and item['authoredContent']['summary'] == '')
    preview = {'summary': '', 'description': item['description'], 'linked': False}
    shown, _ = call(admin, 'POST', '/api/v1/admin/attractions/content-preview', preview)
    check('preview matches published rule', shown['summary'] == item['summary'])
    check('preview rejects missing CSRF', admin.call('POST', '/api/v1/admin/attractions/content-preview', preview, csrf=False)[0] == 403)
    check('preview rejects foreign Origin', admin.call('POST', '/api/v1/admin/attractions/content-preview', preview, extra={'Origin': 'https://attacker.test'})[0] == 403)
    save('', '新的介紹')
    check('description edits update excerpt', call(guest, 'GET', public, route='/api/v1/attractions/{slug}')[0]['attraction']['summary'] == '新的介紹')
    save('自填摘要', '新的介紹')
    check('authored summary wins', call(guest, 'GET', public, route='/api/v1/attractions/{slug}')[0]['attraction']['summary'] == '自填摘要')
    save('', '')
    summary, headers = call(guest, 'GET', public + '/google-summary', route='/api/v1/attractions/{slug}/google-summary')
    check('unlinked safe empty state', summary['status'] == 'unavailable' and summary['text'] == '')
    check('never cache external text', headers.get('Cache-Control') == 'no-store')
    call(guest, 'GET', public + '/google-summary?placeId=ChIarbitrary', expected=422, route='/api/v1/attractions/{slug}/google-summary')
    call(guest, 'GET', '/api/v1/attractions/not-found-owned/google-summary', expected=404, route='/api/v1/attractions/{slug}/google-summary')
    bad = copy.deepcopy(preview);bad['description'] = 123
    call(admin, 'POST', '/api/v1/admin/attractions/content-preview', bad, expected=422)
    fixture("UPDATE admin_role_permissions a JOIN permissions p ON a.permission_id=p.id SET a.allowed=(p.action_key='view') WHERE a.admin_id=" + literal(state['adminId']) + " AND p.module_key='attractions'")
    call(admin, 'POST', '/api/v1/admin/attractions/content-preview', preview, expected=403)
    fixture('UPDATE admin_role_permissions SET allowed=1 WHERE admin_id=' + literal(state['adminId']))
    fixture("UPDATE attractions SET status='draft',version=version+1 WHERE id=" + literal(state['placeId']));publish()
    call(guest, 'GET', public + '/google-summary', expected=404, route='/api/v1/attractions/{slug}/google-summary')
finally:
    fixture('UPDATE admin_role_permissions SET allowed=1 WHERE admin_id=' + literal(state['adminId']))
    fixture("UPDATE attractions SET status='published',version=version+1 WHERE id=" + literal(state['placeId']));publish()

connect = socket.create_connection
socket.create_connection = lambda address, *a, **kw: connect(('127.0.0.1', address[1]) if address[0] == 'localhost' else address, *a, **kw)
http = urllib.request.build_opener(urllib.request.ProxyHandler({}))
with http.open('http://localhost:8081/places/' + state['prefix'] + '-place.html', timeout=15) as response:
    html = response.read().decode()
check('empty detail remains usable', '尚未提供景點介紹' in html and response.status == 200)
check('no synthetic external text in HTML', 'Synthetic original text' not in html)
report = {'passed': True, 'checks': len(checks), 'contracts': contracts, 'externalGoogleCalls': 0}
(ROOT / 'artifacts/phase15/content-fallback-integration.json').write_text(json.dumps(report, indent=2), encoding='utf8')
print(json.dumps(report))
