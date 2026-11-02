"""所有受保護的操作都必須拒絕匿名存取。

不重設 root 憑證、不授予角色權限；只登入登出測試自有的帳號。
這是補充先前的本人、CSRF 與角色權限測試，而不是取代它們。
"""
import json
import re
from api_integration import ROOT, Client


def main():
    state = json.loads((ROOT / 'artifacts/phase8-browser-fixture.json').read_text(encoding='utf8'))
    if state.get('cleaned') or not state['prefix'].startswith('p8ui'):
        raise RuntimeError('Active owned isolated fixture required')
    spec = json.loads((ROOT / 'backend/resources/openapi.json').read_text(encoding='utf8'))
    guest = Client()
    results = []
    for path, operations in spec['paths'].items():
        protected = path.startswith('/api/v1/admin/') and '/auth/' not in path or path.startswith('/api/v1/me/')
        if not protected:
            continue
        concrete = re.sub(r'\{[^}]+\}', '00000000-0000-4000-8000-000000000000', path)
        for method in operations:
            if method not in {'get', 'post', 'put', 'delete', 'patch'}:
                continue
            status, body, _ = guest.call(method.upper(), concrete, {} if method in {'post', 'put', 'patch'} else None)
            assert status in {401, 403}, (method, path, status)
            # 媒體存取檢查是給 Nginx auth_request 使用的，403 回應刻意只帶最少的欄位。
            required = ['requestId'] if path == '/api/v1/admin/media/access' else ['code', 'message', 'fieldErrors', 'requestId']
            assert all(key in body for key in required), (method, path, status, list(body))
            results.append({'method': method, 'path': path, 'status': status})
    for kind, identifier, endpoint in [
        ('admin', state['prefix'], '/api/v1/admin/auth/me'),
        ('member', state['prefix'] + '@example.test', '/api/v1/auth/me'),
    ]:
        client = Client(kind)
        assert client.login(identifier, state['password'])[0] == 200
        assert client.call('GET', endpoint)[0] == 200
        assert client.call('POST', client.auth + 'logout', {})[0] == 204
        assert client.call('GET', endpoint)[0] == 401
    target = ROOT / 'artifacts/phase15/access.json'
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text(json.dumps({'protectedOperations': len(results), 'loginLogoutFlows': 2,
                                  'passed': True, 'responses': results}, indent=2), encoding='utf8')
    print(json.dumps({'protectedOperations': len(results), 'loginLogoutFlows': 2, 'passed': True}))


if __name__ == '__main__':
    main()
