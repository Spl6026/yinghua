import test from 'node:test';
import assert from 'node:assert/strict';

// api-client 只用到瀏覽器的 fetch 與計時器；這裡換成可控制的假物件後直接載入真正的模組。
globalThis.window = { setTimeout: (fn, ms) => setTimeout(fn, ms), clearTimeout: id => clearTimeout(id) };
const { api } = await import('../assets/js/core/api-client.js');

const reply = (status, body, type = 'application/json') =>
  new Response(typeof body === 'string' ? body : JSON.stringify(body), {
    status,
    headers: { 'Content-Type': type },
  });

test('閘道回的 HTML 錯誤頁轉成可讀的錯誤，保留真正的狀態碼', async () => {
  globalThis.fetch = async () => reply(502, '<html>Bad Gateway</html>', 'text/html');
  await assert.rejects(api('/api/v1/attractions'), error => {
    assert.equal(error.status, 502);
    assert.equal(error.code, 'SERVICE_UNAVAILABLE');
    assert.match(error.message, /服務暫時無法使用/);
    return true;
  });
});

test('上傳過大由狀態碼得到明確訊息，而不是連線錯誤', async () => {
  globalThis.fetch = async () => reply(413, '<html>413</html>', 'text/html');
  await assert.rejects(
    api('/api/v1/attractions'),
    error => error.status === 413 && /過大/.test(error.message)
  );
});

test('成功但不是 JSON 的回應不當成成功資料', async () => {
  globalThis.fetch = async () => reply(200, 'OK', 'text/plain');
  await assert.rejects(api('/api/v1/attractions'), error => error.status === 502);
});

test('JSON 錯誤沿用伺服器的訊息與欄位錯誤', async () => {
  globalThis.fetch = async () =>
    reply(422, { code: 'VALIDATION_FAILED', message: '欄位有誤', fieldErrors: { name: '必填' } });
  await assert.rejects(api('/api/v1/attractions'), error => {
    assert.equal(error.status, 422);
    assert.deepEqual(error.fieldErrors, { name: '必填' });
    return true;
  });
});

test('逾時與斷線分開說明', async () => {
  globalThis.fetch = (_path, { signal }) =>
    new Promise((_, reject) => signal.addEventListener('abort', () => reject(new Error('aborted'))));
  await assert.rejects(
    api('/api/v1/attractions', { timeout: 20 }),
    error => error.status === 0 && /逾時/.test(error.message)
  );
  globalThis.fetch = async () => {
    throw new TypeError('Failed to fetch');
  };
  await assert.rejects(
    api('/api/v1/attractions'),
    error => error.status === 0 && /檢查連線/.test(error.message)
  );
});

test('成功的 JSON 回應原樣回傳', async () => {
  globalThis.fetch = async () => reply(200, { items: [1, 2] });
  assert.deepEqual(await api('/api/v1/attractions'), { items: [1, 2] });
});
