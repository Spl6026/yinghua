"""回歸測試：圖片說明的保存、共用資產互不影響，以及與原型一致的排序與標籤。"""
import html
import json
import secrets
from api_integration import Client, ROOT, fixture, password, admin_body
from attractions_integration import upload, test_png, body, raw, cli


def main():
    fixture(password, 'root')
    root, guest = Client('admin'), Client()
    assert root.login('admin')[0] == 200
    prefix = 'correct-' + secrets.token_hex(5)
    created, checks = [], []

    def check(name, condition):
        assert condition, name
        checks.append(name)

    def save(value, target=None, expected=200):
        code, result, _ = root.call('PUT' if target else 'POST', '/api/v1/admin/attractions' + ('/' + target if target else ''), value)
        assert code == expected, (code, result.get('message'))
        if not target:
            created.append(result['attraction']['id'])
        return result['attraction']

    try:
        code, uploaded = upload(root, test_png())
        assert code == 201
        media = uploaded['media']
        value = body(media['id'], slug=prefix, imageAlt='此景點的說明', imageSource='個別來源', imageLicense='個別授權', recommendationLabel='城市記憶')
        first = save(value, expected=201)
        check('metadata stored on create', first['imageAlt'] == value['imageAlt'] and first['imageSource'] == value['imageSource'] and first['imageLicense'] == value['imageLicense'])
        second = save(body(media['id'], slug=prefix+'-shared'), expected=201)
        check('shared asset keeps original metadata', second['imageAlt'] == media['alt'])
        value.update(version=first['version'], status='published', imageAlt='修改後 <安全> 說明', imageSource='修正來源', imageLicense='修正授權')
        first = save(value, first['id'])
        loaded = root.call('GET', '/api/v1/admin/attractions/'+first['id'])[1]['attraction']
        check('metadata edit survives reload without uploading', all(loaded[key] == value[key] for key in ['imageAlt', 'imageSource', 'imageLicense']))
        public = guest.call('GET', '/api/v1/attractions/'+prefix)[1]['attraction']
        check('public metadata matches saved content', public['imageAlt'] == value['imageAlt'] and public['recommendationLabel'] == '城市記憶')
        code, content, _ = raw(guest, '/places/'+prefix+'.html')
        check('static HTML uses saved alt safely', code == 200 and html.escape(value['imageAlt'], quote=True) in content.decode())
        check('other attraction unaffected', root.call('GET', '/api/v1/admin/attractions/'+second['id'])[1]['attraction']['imageAlt'] == media['alt'])
        stored = fixture("SELECT alt,source,license FROM media_assets WHERE id='"+media['id']+"'")[0]
        check('original asset provenance unchanged', stored == {'alt': media['alt'], 'source': media['source'], 'license': media['license']})
        check('stale metadata write rejected', root.call('PUT', '/api/v1/admin/attractions/'+first['id'], dict(value, imageAlt='過期改寫'))[0] == 409)
        before = loaded['version']
        bad = dict(value, version=before, imageAlt='x'*256)
        check('long alt rejected', root.call('PUT', '/api/v1/admin/attractions/'+first['id'], bad)[0] == 422)
        bad['imageAlt'] = ['invalid']
        check('non-string alt rejected', root.call('PUT', '/api/v1/admin/attractions/'+first['id'], bad)[0] == 422)
        check('failed writes preserve version', root.call('GET', '/api/v1/admin/attractions/'+first['id'])[1]['attraction']['version'] == before)
        first = save(dict(body(media['id'], slug=prefix), version=before, status='published'), first['id'])
        check('older client omitting metadata preserves it', first['imageAlt'] == value['imageAlt'] and first['recommendationLabel'] == '城市記憶')
        first = save(dict(body(media['id'], slug=prefix), version=first['version'], status='published', imageAlt='', imageSource='', imageLicense=''), first['id'])
        check('clearing override restores original asset metadata', first['imageAlt'] == media['alt'] and first['imageSource'] == media['source'] and first['imageLicense'] == media['license'])
        fresh = upload(root, test_png(30,20), extra={'alt':'替換圖片說明','source':'替換來源','license':'替換授權'})[1]['media']
        first = save(dict(body(fresh['id'], slug=prefix), version=first['version'], status='published', imageAlt=fresh['alt'], imageSource=fresh['source'], imageLicense=fresh['license']), first['id'])
        check('replacement image and metadata synchronized', first['image'] == fresh['url'] and guest.call('GET','/api/v1/attractions/'+prefix)[1]['attraction']['imageAlt'] == fresh['alt'])
        viewer_body = admin_body('metaview'+secrets.token_hex(4))
        viewer_id = root.call('POST','/api/v1/admin/admins',viewer_body)[1]['admin']['id']
        fixture("UPDATE admin_users SET must_change_password=0 WHERE id='"+viewer_id+"'")
        viewer = Client('admin'); assert viewer.login(viewer_body['account'])[0] == 200
        check('viewer cannot edit image metadata', viewer.call('PUT','/api/v1/admin/attractions/'+first['id'],dict(value,version=first['version']))[0] == 403)
        root.call('POST', root.auth+'preview', {'adminId':viewer_id})
        check('preview cannot edit image metadata', root.call('PUT','/api/v1/admin/attractions/'+first['id'],dict(value,version=first['version']))[1].get('code') == 'PREVIEW_READ_ONLY')
        root.call('DELETE', root.auth+'preview', {})
        demo_path = '/api/v1/admin/attractions/03000000-0000-4000-8000-000000000002'
        demo = root.call('GET', demo_path)[1]['attraction']
        metadata_before = fixture("SELECT image_alt,image_source,image_license FROM attractions WHERE id='03000000-0000-4000-8000-000000000002'")[0]
        demo_value = {key:demo[key] for key in body('').keys()}
        demo_value['mediaId'] = demo_value['mediaId'] or ''
        demo_value['googlePlaceId'] = demo_value['googlePlaceId'] or ''
        custom = dict(demo_value,version=demo['version'],recommendationLabel='人工保留文案',imageAlt='人工保留圖說')
        try:
            changed = root.call('PUT',demo_path,custom)
            assert changed[0] == 200
            cli('content:seed-demo')
            check('regular seed does not overwrite edited metadata', root.call('GET',demo_path)[1]['attraction']['imageAlt'] == '人工保留圖說')
            check('backfill preview preserves custom labels', json.loads(cli('content:backfill-demo-labels'))['changes'] == [])
        finally:
            latest = root.call('GET',demo_path)[1]['attraction']
            restore = dict(demo_value,version=latest['version'],recommendationLabel=demo['recommendationLabel'],imageAlt=metadata_before['image_alt'],imageSource=metadata_before['image_source'],imageLicense=metadata_before['image_license'])
            assert root.call('PUT',demo_path,restore)[0] == 200
        # 只比較受控 seed，測試環境可能另有其他已發布測試資料。
        public = guest.call('GET','/api/v1/attractions?pageSize=100')[1]['items']
        demos = [row for row in public if row.get('legacyId') is not None]
        check('original demo order retained', [row['legacyId'] for row in demos] == [1,2,3,4,5,6])
        check('original text labels retained', [row['recommendationLabel'] for row in demos] == ['城市記憶','巷弄探索','在地好店','散步提案','風城咖啡','本月優惠'])
        check('demo labels reach static HTML', all(label.encode() in raw(guest,'/explore.html')[1] for label in ['城市記憶','在地好店']))
    finally:
        root.call('DELETE',root.auth+'preview',{})
        for identifier in created:
            result = root.call('GET','/api/v1/admin/attractions/'+identifier)
            if result[0] == 200:
                root.call('DELETE','/api/v1/admin/attractions/'+identifier,{'version':result[1]['attraction']['version']})
    (ROOT/'artifacts/phase3-correction-results.json').write_text(json.dumps(checks,ensure_ascii=False,indent=2),encoding='utf-8')
    (ROOT/'artifacts/phase3-correction-browser-credentials.json').write_text(json.dumps({'rootPassword':password}),encoding='utf-8')
    print(json.dumps({'correctionChecks':len(checks)},ensure_ascii=False))


if __name__ == '__main__':
    main()
