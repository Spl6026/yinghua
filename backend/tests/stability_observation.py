"""固定跑 100 輪，觀察 CSRF 與發布是否穩定；不自動重試，也不輸出機密。"""
import json, time, urllib.request, urllib.error
from api_integration import Client, ROOT

def main():
    client = Client()
    observations = []
    previous = None
    for index in range(100):
        started = time.monotonic()
        csrf = client.refresh()
        expiry = int(csrf['token'].split('.')[0])
        status, body, headers = client.call('POST', client.auth+'register', {})
        observations.append(dict(round=index+1, status=status, code=body.get('code'),
            requestId=body.get('requestId'), expiryChange=None if previous is None else expiry-previous,
            elapsedMs=round((time.monotonic()-started)*1000), retries=0))
        previous = expiry
    result = dict(rounds=100, expectedStatus=422, observations=observations,
                  passed=all(row['status']==422 for row in observations))
    target = ROOT/'artifacts/phase8-stability-observation.json'
    target.write_text(json.dumps(result, indent=2), encoding='utf8')
    print(json.dumps(dict(rounds=100, passed=result['passed'],
        unexpected=[row for row in observations if row['status']!=422],
        backwardExpiryChanges=sum(row['expiryChange'] is not None and row['expiryChange']<0 for row in observations))))
    if not result['passed']: raise SystemExit(1)

if __name__=='__main__': main()
