"""[D API草案 C43:H43; E-29] Validate actual health HTTP responses, including failures."""
import argparse, json, sys
from pathlib import Path

parser = argparse.ArgumentParser()
parser.add_argument('--dependency-path', required=True)
options = parser.parse_args()
sys.path.insert(0, str(Path(options.dependency_path).resolve()))
import jsonschema
import health_integration as health

spec = json.loads((health.ROOT / 'backend/resources/openapi.json').read_text(encoding='utf8'))
validator = jsonschema.Draft202012Validator(spec)
original = health.Client.call
observed = []

def audited(self, method, path, body=None, csrf=True, extra=None):
    result = original(self, method, path, body, csrf, extra)
    if path == '/api/v1/health' and method in ('GET', 'POST'):
        operation = spec['paths']['/api/v1/health']['get']
        response = operation['responses'][str(result[0])]
        schema = response['content']['application/json']['schema']
        validator.evolve(schema=schema).validate(result[1])
        observed.append({'method': method, 'status': result[0]})
    return result

health.Client.call = audited
try:
    health.main()
finally:
    health.Client.call = original
    (health.ROOT / 'artifacts/phase8-health-contract.json').write_text(json.dumps({'httpResponses': observed}, indent=2), encoding='utf8')
print(json.dumps({'healthHttpContracts': len(observed), 'passed': True}))
