"""確認實際路由與 OpenAPI 文件一致，並檢查內容回應的欄位契約；只使用隔離環境。"""
import json
import subprocess
from api_integration import Client, ROOT, fixture, password


def main():
    spec = json.loads((ROOT / 'backend/resources/openapi.json').read_text(encoding='utf-8'))
    php = '''<?php
    require 'vendor/autoload.php';
    $kernel = (new Yinghua\\Composition(new Yinghua\\Config()))->kernel();
    $router = (new ReflectionProperty($kernel, 'router'))->getValue($kernel);
    $routes = (new ReflectionProperty($router, 'routes'))->getValue($router);
    // E-29：health 在 eager DB 組裝前分派，不列入一般認證 Router。
    echo json_encode([...array_map(fn($r) => [$r->method, $r->path], $routes), ['GET', Yinghua\\Composition::HEALTH_PATH]]);
    '''
    result = subprocess.run(['docker', 'compose', 'exec', '-T', 'app-test', 'php'],
                            cwd=ROOT, input=php, capture_output=True, text=True, encoding='utf-8', check=True)
    actual = {tuple(item) for item in json.loads(result.stdout)}
    documented = {(method.upper(), path) for path, value in spec['paths'].items()
                  for method in value if method in {'get', 'post', 'put', 'delete', 'patch'}}
    # 爬蟲用的檔案由 Nginx 提供，不經過 PHP Router，所以分開驗證，避免把這三個 GET 誤判成漏掉的路由。
    crawler = {('GET', '/robots.txt'), ('GET', '/sitemap.xml'), ('GET', '/sitemap-{part}.xml')}
    assert documented - crawler == actual, (actual - documented, documented - crawler - actual)
    assert crawler <= documented
    from attractions_integration import raw
    for path, expected in [('/robots.txt', 200), ('/sitemap.xml', 200), ('/sitemap-999.xml', 404)]:
        assert raw(Client(), path)[0] == expected, ('crawler route', path)
    fixture(password, 'root')
    root = Client('admin')
    assert root.login('admin')[0] == 200
    guest = Client()
    count = 1

    def shape(client, path, schema):
        nonlocal count
        status, data, _ = client.call('GET', path)
        assert status == 200, (path, status)
        definition = spec['components']['schemas'][schema]
        assert set(data) == set(definition['properties']), (schema, set(data))
        assert set(definition.get('required', [])) <= set(data)
        count += 1
        return data

    page = shape(root, '/api/v1/admin/attractions', 'AttractionList')
    assert page['items']
    item = page['items'][0]
    assert set(item) == set(spec['components']['schemas']['Attraction']['properties'])
    count += 1
    shape(root, '/api/v1/admin/attractions/' + item['id'], 'AttractionRecord')
    shape(root, '/api/v1/admin/media', 'MediaList')
    public = shape(guest, '/api/v1/attractions', 'PublicAttractionList')
    assert public['items']
    public_item = public['items'][0]
    assert set(public_item) == set(spec['components']['schemas']['PublicAttraction']['properties'])
    assert not {'status', 'deletedAt', 'googlePlaceId', 'legacyImage'} & set(public_item)
    count += 1
    shape(guest, '/api/v1/attractions/' + public_item['slug'], 'PublicAttractionRecord')
    shape(guest, '/api/v1/museum', 'PublicAttractionRecord')
    print(json.dumps({'contractChecks': count + 3, 'routeOperations': len(actual), 'crawlerOperations': len(crawler)}))


if __name__ == '__main__':
    main()
