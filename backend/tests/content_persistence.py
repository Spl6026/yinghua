"""重啟隔離的 app／web 之後，確認真實內容、HTML 與媒體都還在；不刪除 volume。"""
import json
import subprocess
import time
from api_integration import Client, ROOT, fixture, password
from attractions_integration import body, raw, test_png, upload, write


def main():
    fixture(password, 'root')
    root, guest = Client('admin'), Client()
    assert root.login('admin')[0] == 200
    code, result = upload(root, test_png())
    assert code == 201
    media = result['media']
    item, _ = write(root, body(media['id'], status='published'), expect=201)
    path = '/places/' + item['slug'] + '.html'
    image_before = raw(guest, media['url'])[1]
    html_before = raw(guest, path)[1]
    subprocess.run(['docker', 'compose', 'restart', 'app-test', 'web-test'], cwd=ROOT,
                   check=True, capture_output=True)
    for _ in range(40):
        try:
            if guest.call('GET', '/api/v1/attractions/' + item['slug'])[0] == 200:
                break
        except (OSError, ValueError):
            pass
        time.sleep(0.5)
    else:
        raise AssertionError('Restarted test environment did not become ready.')
    assert raw(guest, path)[:2] == (200, html_before)
    assert raw(guest, media['url'])[:2] == (200, image_before)
    assert root.call('GET', '/api/v1/admin/attractions/' + item['id'])[1]['attraction']['version'] == 1
    assert root.call('DELETE', '/api/v1/admin/attractions/' + item['id'], {'version': 1})[0] == 200
    assert raw(guest, path)[0] == 404
    assert raw(guest, media['url'])[0] == 404
    print(json.dumps({'persistenceChecks': 6, 'servicesRestarted': ['app-test', 'web-test']}))


if __name__ == '__main__':
    main()
