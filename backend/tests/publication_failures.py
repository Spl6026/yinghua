"""資料已提交、但寫入發布紀錄失敗時，不能弄丟已儲存的資料識別。"""
import json
import secrets
from api_integration import Client, fixture, password
from attractions_integration import cli


def main():
    fixture(password, 'root')
    root = Client('admin')
    assert root.login('admin')[0] == 200
    prefix = 'publication-failure-' + secrets.token_hex(5)
    checks = []
    trigger = 'test_publication_record_failure'

    def check(name, condition):
        assert condition, name
        checks.append(name)

    for operation in ['INSERT', 'UPDATE']:
        fixture(f"CREATE TRIGGER {trigger} BEFORE {operation} ON content_publications "
                "FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='isolated publication record failure'")
        try:
            for module, field, key in [('films', 'title', 'film'), ('attractions', 'name', 'attraction')]:
                label = prefix + '-' + operation.lower() + '-' + module
                body = {field: label, 'status': 'published'}
                if module == 'attractions':
                    body['category'] = '景點'
                path = '/api/v1/admin/' + module
                code, result, _ = root.call('POST', path, body)
                check(module + ' create returns saved identity after ' + operation,
                      code == 202 and result[key]['version'] == 1 and result['publication']['status'] == 'failed')
                item = result[key]
                identifier = item['id']
                path += '/' + identifier
                if operation == 'INSERT':
                    # 發布失敗時網站照常提供上一版，新建立的內容不會提早公開。
                    check(module + ' unpublished generation stays private',
                          Client().call('GET', '/api/v1/' + module + '/' + item['slug'])[0] == 404)
                body['version'] = item['version']
                code, updated, _ = root.call('PUT', path, body)
                check(module + ' retry updates same resource after ' + operation,
                      code == 202 and updated[key]['id'] == identifier and updated[key]['version'] == 2)
                count = fixture(f"SELECT COUNT(*) AS n FROM {module} WHERE {field}='{label}' AND deleted_at IS NULL")
                check(module + ' no duplicate row after ' + operation, int(count[0]['n']) == 1)
                code, deleted, _ = root.call('DELETE', path, {'version': 2})
                check(module + ' delete remains saved after ' + operation,
                      code == 202 and deleted[key] is None and root.call('GET', path)[0] == 404)
        finally:
            fixture('DROP TRIGGER ' + trigger)
            cli('content:publish')
    print(json.dumps({'publicationFailureChecks': len(checks), 'checks': checks}))


if __name__ == '__main__':
    main()
