// [E-02／E-13] Cookie、CSRF、錯誤與逾時處理只有一份；狀態不存 localStorage。
export class ApiError extends Error {
  constructor(status, payload, retryAfter) {
    super(payload.message || '操作失敗，請稍後再試。');
    this.status = status;
    this.code = payload.code;
    this.fieldErrors = payload.fieldErrors || {};
    this.requestId = payload.requestId;
    this.retryAfter = Number(retryAfter) || 0;
  }
}
const tokens = new Map();
const csrfLoads = new Map();
const configurations = new Map();
const prefix = kind => kind === 'admin' ? '/api/v1/admin/auth/' : '/api/v1/auth/';

async function request(path, options = {}) {
  const controller = new AbortController();
  const timeout = window.setTimeout(() => controller.abort(), 15000);
  try {
    const response = await fetch(path, { ...options, signal: controller.signal, credentials: 'same-origin', cache: 'no-store' });
    const payload = response.status === 204 ? {} : await response.json();
    return { response, payload };
  } catch {
    throw new ApiError(0, { code: 'NETWORK_ERROR', message: '無法確認操作結果，請檢查連線並重新載入頁面。' });
  } finally { window.clearTimeout(timeout); }
}

export async function csrf(kind = 'member', refresh = false) {
  if (refresh) tokens.delete(kind);
  if (!refresh && tokens.has(kind)) return tokens.get(kind);
  if (!csrfLoads.has(kind)) {
    csrfLoads.set(kind, request(prefix(kind) + 'csrf')
      .then(({ response, payload }) => {
        if (!response.ok) throw new ApiError(response.status, payload, response.headers.get('Retry-After'));
        tokens.set(kind, payload.token);
        configurations.set(kind, payload);
        return payload.token;
      }).finally(() => csrfLoads.delete(kind)));
  }
  return csrfLoads.get(kind);
}

export function clearCsrf(kind = 'member') { tokens.delete(kind); }
export async function authConfiguration(kind = 'member') {
  await csrf(kind);
  return configurations.get(kind);
}

export async function api(path, { method = 'GET', body, kind = 'member', retry = true } = {}) {
  if (!path.startsWith('/api/v1/') || path.includes('\\')) throw new Error('API path must be a same-origin route.');
  const headers = { Accept: 'application/json' };
  if (method !== 'GET' && method !== 'HEAD') {
    headers['X-CSRF-Token'] = await csrf(kind);
    if (!(body instanceof FormData)) headers['Content-Type'] = 'application/json';
  }
  const { response, payload } = await request(path, { method, headers, body: body === undefined ? undefined : body instanceof FormData ? body : JSON.stringify(body) });
  if (!response.ok) {
    if (retry && payload.code === 'CSRF_INVALID') {
      await csrf(kind, true);
      return api(path, { method, body, kind, retry: false });
    }
    throw new ApiError(response.status, payload, response.headers.get('Retry-After'));
  }
  if (payload.csrfToken) tokens.set(kind, payload.csrfToken);
  if (response.status === 204 && /\/auth\/(logout|change-password|reset-password)$/.test(path)) clearCsrf(kind);
  return payload;
}
