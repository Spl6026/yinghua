"""[E-02] 真實 Nginx canonical URL／API CSRF 回歸；不需要登入帳密。"""
import argparse
import http.cookiejar
import json
import urllib.error
import urllib.parse
import urllib.request


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, request, response, code, message, headers, url):
        return None


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--port', type=int, default=8080)
    parser.add_argument('--origin', default='http://localhost:8080')
    args = parser.parse_args()
    origin = args.origin.rstrip('/')
    authority = urllib.parse.urlsplit(origin).netloc
    base = f'http://127.0.0.1:{args.port}'
    client = urllib.request.build_opener(
        urllib.request.ProxyHandler({}),
        urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()),
        NoRedirect(),
    )
    checks = []

    def request(method, path, host=None, origin_header=None, csrf=None, fetch_site=None):
        headers = {'Host': host or authority, 'Accept': 'application/json'}
        if origin_header is not None:
            headers['Origin'] = origin_header
        if csrf:
            headers['X-CSRF-Token'] = csrf
        if fetch_site:
            headers['Sec-Fetch-Site'] = fetch_site
        data = None
        if method == 'POST':
            headers['Content-Type'] = 'application/json'
            data = b'{}'
        try:
            result = client.open(urllib.request.Request(base + path, method=method, data=data, headers=headers), timeout=15)
        except urllib.error.HTTPError as error:
            result = error
        body = result.read()
        payload = json.loads(body) if result.headers.get_content_type() == 'application/json' else {}
        return result.status, result.headers, payload

    def check(label, condition):
        if not condition:
            raise AssertionError(label)
        checks.append(label)

    for path in ['/', '/admin/', '/admin/login.html?returnTo=%2Fadmin%2Fadmins.html']:
        code, headers, _ = request('GET', path, host=f'127.0.0.1:{args.port}')
        check('Alias redirects preserving path/query: ' + path, code == 302 and headers['Location'] == origin + path)
    code, _, _ = request('GET', '/admin/login.html')
    check('Canonical login page has no redirect loop', code == 200)
    code, headers, _ = request('GET', '/admin/login.html', host='untrusted.example')
    check('Redirect target comes from configuration, not request Host', code == 302 and headers['Location'] == origin + '/admin/login.html')
    for kind in ['auth', 'admin/auth']:
        path = '/api/v1/' + kind + '/'
        code, _, body = request('GET', path + 'csrf')
        check('API remains JSON: ' + kind, code == 200 and 'token' in body)
        token = body['token']
        code, _, body = request('POST', path + 'login', origin_header=origin, csrf=token)
        check('Canonical origin passes CSRF before field validation: ' + kind, code == 422 and body['code'] == 'VALIDATION_FAILED')
        alternate_origin = f'http://localhost:{args.port}' if origin == base else base
        for bad_origin in [alternate_origin, 'https://untrusted.example']:
            code, _, body = request('POST', path + 'login', origin_header=bad_origin, csrf=token)
            check('Wrong origin rejected: ' + kind + ' ' + bad_origin, code == 403 and body['code'] == 'CSRF_INVALID')
        code, _, body = request('POST', path + 'login', origin_header=origin)
        check('Missing CSRF rejected: ' + kind, code == 403 and body['code'] == 'CSRF_INVALID')
        code, _, body = request('POST', path + 'login', origin_header=origin, csrf=token, fetch_site='cross-site')
        check('Cross-site request rejected: ' + kind, code == 403 and body['code'] == 'CSRF_INVALID')
    print(f'PASS: {len(checks)} origin/redirect checks on port {args.port}; no credentials used.')


if __name__ == '__main__':
    main()
