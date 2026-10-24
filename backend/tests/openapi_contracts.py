"""[E-02/E-15] Validate real isolated HTTP responses against OpenAPI using python-jsonschema.

Requires tests/requirements.txt. Uses localhost:8081/yinghua_test and local Mailpit;
origin_redirect only probes public 8080 redirects without credentials. Never calls Google.
Captured artifacts contain schema paths and counts, never request/password/token payloads.
"""
from pathlib import Path
import sys, json, re, subprocess, importlib, contextlib, io
import argparse
from importlib.metadata import version
parser = argparse.ArgumentParser(description='Isolated integration suites plus independent OpenAPI schema checks')
parser.add_argument('--dependency-path', help='Optional pip --target directory for test dependencies')
parser.add_argument('--include-films', action='store_true', help='Include phase-four film/public/screening suites')
parser.add_argument('--start-at', help='Run the remaining fixed suites after a diagnosed harness failure; previous evidence stays separate')
options = parser.parse_args()
if options.dependency_path:
    sys.path.insert(0, str(Path(options.dependency_path).resolve()))
sys.argv = [sys.argv[0]]
ROOT = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(ROOT / 'backend/tests'))
import jsonschema
from api_integration import Client
spec = json.loads((ROOT / 'backend/resources/openapi.json').read_text(encoding='utf-8'))
root_validator = jsonschema.Draft202012Validator(spec)
schemas = {}
errors = []
observed = set()
counts = {'responses': 0, 'successfulRequests': 0}

def dereference(value):
    while '$ref' in value:
        ref = value['$ref']
        assert ref.startswith('#/')
        value = spec
        for key in ref[2:].split('/'):
            value = value[key.replace('~1', '/').replace('~0', '~')]
    return value

def validate(schema, value, kind, route, status):
    key = json.dumps(schema, sort_keys=True)
    if key not in schemas:
        schemas[key] = root_validator.evolve(schema=schema)
    for error in schemas[key].iter_errors(value):
        errors.append({'kind': kind, 'route': route, 'status': status, 'path': list(error.path), 'validator': error.validator, 'schemaPath': list(error.schema_path)})
original = Client.call

def audited(self, method, path, body=None, csrf=True, extra=None):
    result = original(self, method, path, body, csrf, extra)
    status, payload, headers = result
    url = path.split('?')[0]
    for route, item in sorted(spec['paths'].items(), key=lambda entry: ('{' in entry[0], entry[0])):
        if not re.fullmatch(re.sub('\\{[^}]+\\}', '[^/]+', route), url):
            continue
        operation = item.get(method.lower())
        if operation is None:
            break
        observed.add(method + ' ' + route)
        if 200 <= status < 300 and body is not None and isinstance(body, dict):
            schema = operation.get('requestBody', {}).get('content', {}).get('application/json', {}).get('schema')
            if schema:
                validate(schema, body, 'request', route, status)
                counts['successfulRequests'] += 1
        response = operation.get('responses', {}).get(str(status), operation.get('responses', {}).get('default'))
        if response and status != 204 and (method != 'HEAD'):
            definition = dereference(response)
            schema = definition.get('content', {}).get('application/json', {}).get('schema')
            if schema:
                validate(schema, payload, 'response', route, status)
                counts['responses'] += 1
        break
    return result
Client.call = audited
if options.include_films:
    # 上傳測試使用 multipart helper，並非 Client.call；也驗證真正 HTTP 回傳的 schema。
    import attractions_integration as media_http
    original_upload = media_http.upload
    def audited_upload(*args, **kwargs):
        status, payload = original_upload(*args, **kwargs)
        route = '/api/v1/admin/media'
        observed.add('POST ' + route)
        response = spec['paths'][route]['post']['responses'].get(str(status))
        if response:
            schema = dereference(response).get('content', {}).get('application/json', {}).get('schema')
            if schema:
                validate(schema, payload, 'response', route, status)
                counts['responses'] += 1
        return status, payload
    media_http.upload = audited_upload
names = ['api_integration', 'admin_preview', 'attractions_integration', 'content_contract', 'optional_content_integration', 'attraction_corrections', 'opening_hours_integration', 'map_fields_integration', 'google_link_integration', 'specification_alignment', 'response_contract', 'origin_redirect', 'review_regressions', 'owner_requirements']
if options.include_films:
    names += ['films_public_integration', 'films_admin_integration', 'films_screenings_integration',
              'films_seed_integration', 'films_content_persistence', 'films_pagination_integration', 'publication_failures']
completed = []
if options.start_at:
    if options.start_at not in names:raise ValueError('Unknown fixed suite')
    names=names[names.index(options.start_at):]
for name in names:
    module = importlib.import_module(name)
    capture = io.StringIO()
    with contextlib.redirect_stdout(capture):
        module.main()
    (ROOT / ('artifacts/full-recheck-final-' + name + '.log')).write_text(capture.getvalue(), encoding='utf-8')
    completed.append(name)
    (ROOT / 'artifacts/full-recheck-contract-progress.json').write_text(json.dumps({'completed': completed, 'counts': counts, 'errors': errors, 'observed': sorted(observed)}, indent=2), encoding='utf-8')
    print(json.dumps({'suite': name, 'completed': len(completed), 'contractErrors': len(errors)}), flush=True)
php = "<?php require 'vendor/autoload.php'; $mapper=new Yinghua\\Adapters\\GooglePlaceMapper(new Yinghua\\Adapters\\GoogleOpeningHours()); $v=$mapper->convert(['id'=>'review-place','displayName'=>['text'=>'Review'],'formattedAddress'=>'Example','location'=>['latitude'=>24.8,'longitude'=>120.97]],'https://maps.google.com/maps?q=review'); echo json_encode($v?->toArray());"
r = subprocess.run(['docker', 'compose', 'exec', '-T', 'app-test', 'php'], input=php, capture_output=True, text=True, encoding='utf-8', check=True)
validate(spec['components']['schemas']['MapCandidate'], json.loads(r.stdout), 'mapper', 'MapCandidate', 200)
result = {'toolVersion': version('jsonschema'), 'completed': completed, 'counts': counts, 'errors': errors, 'observed': sorted(observed), 'observedOperationCount': len(observed), 'liveGoogleCalls': 0, 'errorCount': len(errors)}
(ROOT / 'artifacts/full-recheck-contract-validation.json').write_text(json.dumps(result, indent=2), encoding='utf-8')
print(json.dumps({k: v for k, v in result.items() if k not in ['observed', 'completed', 'errors']}, ensure_ascii=True))
assert not errors, 'Contract discrepancies found; see sanitized artifact'
