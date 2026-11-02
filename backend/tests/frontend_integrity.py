"""檢查建置後的 HTML 與資產，並可選擇檢查唯讀的 HTTP 路由；不寫入任何業務資料。

執行前請先建置前端。四個舊的詳情端點需要帶識別碼，
沒有識別碼時必須回 404，而不是捏造一筆資料。
這裡只檢查檔案與路由是否完整，不檢查畫面呈現。
"""
import argparse
import json
import re
import urllib.error
import urllib.request
from collections import Counter
from html.parser import HTMLParser
from pathlib import Path
from urllib.parse import unquote, urlsplit

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument('--base-url', help='Optional local HTTP origin, e.g. http://localhost:8080')
parser.add_argument('--output', type=Path, help='Optional ignored artifact JSON')
args = parser.parse_args()
root = Path(__file__).resolve().parents[2]
dist = root / 'frontend/dist'
issues, pages, assets, http = [], [], set(), []


class Page(HTMLParser):
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.ids, self.refs, self.labels, self.mains = [], [], [], 0

    def handle_starttag(self, tag, attrs):
        attributes = dict(attrs)
        if attributes.get('id'):
            self.ids.append(attributes['id'])
        if tag == 'main':
            self.mains += 1
        if tag == 'label' and attributes.get('for'):
            self.labels.append(attributes['for'])
        if tag in ('script', 'img', 'link', 'source'):
            value = attributes.get('src') or (attributes.get('href') if tag == 'link' else None)
            if value:
                self.refs.append(value)


for source in sorted((root / 'frontend/pages').rglob('*.html')):
    relative = source.relative_to(root / 'frontend/pages')
    page = Page()
    if not (dist / relative).is_file():
        issues.append([relative.as_posix(), 'missing built page'])
        continue
    content = (dist / relative).read_text(encoding='utf8')
    page.feed(content)
    pages.append('/' + relative.as_posix())
    if page.mains != 1:
        issues.append([relative.as_posix(), 'main count', page.mains])
    issues.extend([relative.as_posix(), 'duplicate id', key] for key, count in Counter(page.ids).items() if count > 1)
    issues.extend([relative.as_posix(), 'missing label target', key] for key in page.labels if key not in page.ids)
    for reference in page.refs:
        url = urlsplit(reference)
        if url.scheme or url.netloc or reference.startswith('#'):
            continue
        path = dist / unquote(url.path.lstrip('/')) if reference.startswith('/') else dist / relative.parent / unquote(url.path)
        if not path.is_file():
            issues.append([relative.as_posix(), 'missing asset', reference])
        else:
            assets.add('/' + path.relative_to(dist).as_posix())
    if re.search(r'\{\{[A-Z_]+\}\}', content):
        issues.append([relative.as_posix(), 'unresolved template'])

for source in (dist / 'assets/js').rglob('*.js'):
    for reference in re.findall(r'(?:from\s*|import\s*)[\'"]([^\'"]+)[\'"]', source.read_text(encoding='utf8')):
        if reference.startswith('.') and not (source.parent / reference).is_file():
            issues.append([source.relative_to(dist).as_posix(), 'missing module', reference])
if not pages:
    issues.append(['build', 'no pages found'])
if args.base_url:
    origin = urlsplit(args.base_url)
    if origin.scheme != 'http' or origin.hostname not in ('localhost', '127.0.0.1') or origin.path not in ('', '/'):
        parser.error('--base-url must be a local HTTP origin')
    opener = urllib.request.build_opener(urllib.request.ProxyHandler({}))
    # 站內的找不到頁面本身就以 404 回應，其他固定頁都應該是 200。
    not_found_pages = {'/404.html'}
    for path in sorted(set(pages) | assets):
        expected = 404 if path in not_found_pages else 200
        try:
            with opener.open(args.base_url.rstrip('/') + path, timeout=15) as response:
                status = response.status
        except urllib.error.HTTPError as error:
            status = error.code
        except Exception as error:
            issues.append([path, 'HTTP', str(error)])
            continue
        http.append({'path': path, 'status': status, 'expected': expected})
        if status != expected:
            issues.append([path, 'HTTP', status, 'expected', expected])
report = {'fixedPages': len(pages), 'assets': len(assets), 'httpChecks': len(http), 'issues': issues, 'http': http}
if args.output:
    args.output.parent.mkdir(parents=True, exist_ok=True)
    args.output.write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding='utf8')
print(json.dumps({key: value for key, value in report.items() if key != 'http'}, ensure_ascii=False))
raise SystemExit(bool(issues))
