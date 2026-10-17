"""[S E20/G6/L5/L6/L20/L26/L27, E-24] Excel gaps; isolated HTTP/MariaDB only.

Existing suites cover authentication, RBAC, publication, media and map safety.
This suite checks sample privilege boundaries and optional phone round trips.
"""
import json
import secrets
import subprocess
from api_integration import Client, ROOT, fixture, password, admin_body
from attractions_integration import body, raw


def main():
    fixture(password, 'root')
    root, guest = Client('admin'), Client()
    assert root.login('admin')[0] == 200
    suffix = secrets.token_hex(6)
    checks, created_admins = [], []
    item_id = None
    museum_before = None
    museum_changed = False

    def check(label, condition):
        assert condition, label
        checks.append(label)

    def write(value, target=None):
        code, result, _ = root.call('PUT' if target else 'POST',
                                  '/api/v1/admin/attractions' + ('/' + target if target else ''), value)
        assert code == (200 if target else 201), (code, result.get('code'))
        return result['attraction']

    spec = json.loads((ROOT/'backend/resources/openapi.json').read_text(encoding='utf8'))
    writable = set(spec['components']['schemas']['AttractionCreate']['properties'])

    def command(item, **changes):
        value = {key: value for key, value in item.items() if key in writable}
        if value.get('openingHours') is None:
            value.pop('openingHours', None)
        for key in ['mediaId', 'googlePlaceId']:
            value[key] = value.get(key) or ''
        value.update(version=item['version'], **changes)
        return value

    try:
        route = '/api/v1/admin/attractions/samples'
        check('E20 sample endpoint requires authentication', guest.call('GET', route)[0] == 401)
        baseline = fixture('SELECT COUNT(*) AS total,SUM(version) AS versions FROM attractions')[0]
        code, result, _ = root.call('GET', route)
        check('E20 existing DB sample content is available', code == 200 and 0 < len(result['items']) <= 6)
        samples = result['items']
        check('E20 samples exclude identity publication and Google linkage commands',
              all(not {'slug','status','version','mediaId','image','googlePlaceId','isMuseum'} & set(x) for x in samples))
        check('E20 reading samples does not create or update attractions', baseline == fixture('SELECT COUNT(*) AS total,SUM(version) AS versions FROM attractions')[0])
        for key, allowed in [('create', True), ('view', False)]:
            value = dict(admin_body('owner-' + key + suffix), roleId='viewer')
            code, result, _ = root.call('POST', '/api/v1/admin/admins', value)
            assert code == 201
            admin_id = result['admin']['id']
            created_admins.append(admin_id)
            path = '/api/v1/admin/admins/' + admin_id + '/permissions'
            matrix = root.call('GET', path)[1]
            rights = [dict(right, allowed=right['module'] == 'attractions' and right['action'] == key) for right in matrix['permissions']]
            assert root.call('PUT', path, {'version': matrix['version'], 'permissions': rights})[0] == 204
            client = Client('admin')
            assert client.login(value['account'])[0] == 200
            check('E20 sample privilege: ' + key, client.call('GET', route)[0] == (200 if allowed else 403))
            if allowed:
                check('E20 create-only samples do not grant general admin list access', client.call('GET', '/api/v1/admin/attractions')[0] == 403)
        value = body('', slug='owner-phone-' + suffix, name='業主規格電話驗收', phone='+886 (3) 123-4567 分機 25', status='published')
        item = write(value)
        item_id = item['id']
        check('G6 valid optional phone retained by DB and admin API', item['phone'] == value['phone'] and item['phoneUrl'] == 'tel:+88631234567;ext=25')
        public = guest.call('GET', '/api/v1/attractions/' + item['slug'])
        check('G6 public phone matches saved data', public[0] == 200 and public[1]['attraction']['phone'] == item['phone'])
        html = raw(guest, '/places/' + item['slug'] + '.html')[1].decode('utf8')
        check('G6 server-produced HTML contains safe dial link', 'href="tel:+88631234567;ext=25"' in html and item['phone'] in html)
        check('E20 unrelated newly published content is not a legacy sample', item_id not in [x['id'] for x in root.call('GET', route)[1]['items']])
        for invalid in ['not-a-phone', 'javascript:123', '<script>123</script>', ')(03)123(', '1' * 81, 123, None]:
            code, result, _ = root.call('PUT', '/api/v1/admin/attractions/' + item_id, command(item, phone=invalid))
            check('G6 invalid phone rejected: ' + str(len(checks)), code == 422 and 'phone' in result.get('fieldErrors', {}))
        unchanged = root.call('GET', '/api/v1/admin/attractions/' + item_id)[1]['attraction']
        check('G6 failed validation changes neither phone nor version', unchanged['phone'] == item['phone'] and unchanged['version'] == item['version'])

        # The real museum uses the same content model. Temporarily switch only isolated fixtures.
        museum_public = guest.call('GET', '/api/v1/museum')[1]['attraction']
        museum_before = root.call('GET', '/api/v1/admin/attractions/' + museum_public['id'])[1]['attraction']
        write(command(museum_before, isMuseum=False), museum_before['id'])
        museum_changed = True
        item = write(command(item, isMuseum=True), item_id)
        museum = guest.call('GET', '/api/v1/museum')
        check('G6/L6 museum API returns edited contact data', museum[0] == 200 and museum[1]['attraction']['phone'] == value['phone'])
        museum_html = raw(guest, '/museum.html')[1].decode('utf8')
        check('G6/L6 museum HTML publishes phone and last update', 'href="tel:+88631234567;ext=25"' in museum_html and '更新日期' in museum_html)
        home = raw(guest, '/index.html')[1].decode('utf8')
        check('L5 homepage managed content synchronized while original historical timeline retained', item['description'] in home and all(year in home for year in ['1933','1937','1998']))
        item = write(command(item, phone=''), item_id)
        museum_html = raw(guest, '/museum.html')[1].decode('utf8')
        check('G6 optional phone can be cleared without stale contact link', item['phone'] == '' and item['phoneUrl'] == '' and 'tel:+88631234567;ext=25' not in museum_html)
        value = command(item)
        del value['phone']
        item = write(value, item_id)
        check('G6 omitted optional phone accepted', item['phone'] == '')

        # Real repository probe, bounded to the isolated DB and always rolled back.
        php = r'''<?php
        require 'vendor/autoload.php';
        $config=new Yinghua\Config();
        if($config->string('APP_ENV')!=='test'||$config->string('DB_DATABASE')!=='yinghua_test')throw new RuntimeException('Isolated DB required');
        $db=Yinghua\Repositories\Database::connect($config);$repo=new Yinghua\Repositories\AttractionRepository($db);
        $db->pdo->beginTransaction();try {
          $id=$db->row('SELECT id FROM attractions WHERE legacy_id=2')['id'];
          foreach(["status='draft'","status='published',publish_at=DATE_ADD(UTC_TIMESTAMP(),INTERVAL 1 DAY)","status='published',publish_at=UTC_TIMESTAMP(),deleted_at=UTC_TIMESTAMP()"] as $change){
            $db->execute('UPDATE attractions SET '.$change.' WHERE id=:id',['id'=>$id]);
            foreach($repo->samples() as $sample)if($sample->id===$id)throw new RuntimeException('Unavailable sample exposed');
          }
        }finally{$db->pdo->rollBack();}echo '3 sample repository conditions passed';'''
        probe = subprocess.run(['docker','compose','exec','-T','app-test','php'],input=php,text=True,encoding='utf8',capture_output=True,check=True)
        check('E20 draft future and deleted samples excluded with rollback', '3 sample repository conditions passed' in probe.stdout)
    finally:
        if item_id:
            code, result, _ = root.call('GET', '/api/v1/admin/attractions/' + item_id)
            if code == 200:
                assert root.call('DELETE', '/api/v1/admin/attractions/' + item_id, {'version':result['attraction']['version']})[0] == 200
        if museum_changed:
            current = root.call('GET', '/api/v1/admin/attractions/' + museum_before['id'])[1]['attraction']
            write(command(current, isMuseum=True), museum_before['id'])
        for admin_id in created_admins:
            current = root.call('GET', '/api/v1/admin/admins/' + admin_id)[1]['admin']
            assert root.call('DELETE', '/api/v1/admin/admins/' + admin_id, {'version':current['version']})[0] == 204
    (ROOT/'artifacts/owner-requirements-checks.json').write_text(json.dumps(checks,ensure_ascii=False,indent=2),encoding='utf8')
    print(json.dumps({'ownerRequirementChecks':len(checks),'liveGoogleCalls':0,'sampleRepositoryConditions':3}))


if __name__ == '__main__':
    main()
