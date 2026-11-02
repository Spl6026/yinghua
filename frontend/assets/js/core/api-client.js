// Cookie、CSRF、錯誤與逾時處理都集中在這裡；狀態不存在 localStorage。
import { recordSessionResponse, sessionSnapshot, sessionEpoch } from './session-events.js';
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
const prefix = kind => (kind === 'admin' ? '/api/v1/admin/auth/' : '/api/v1/auth/');

// 伺服器沒有回 JSON 時（例如代理或閘道自己產生的錯誤頁），依狀態碼給一般人看得懂的訊息。
function fallbackPayload(status) {
  if (status === 413) return { code: 'PAYLOAD_TOO_LARGE', message: '上傳內容過大，請縮小檔案後再試。' };
  if (status === 429) return { code: 'RATE_LIMITED', message: '操作過於頻繁，請稍後再試。' };
  if (status >= 500) return { code: 'SERVICE_UNAVAILABLE', message: '服務暫時無法使用，請稍後再試。' };
  return { code: 'HTTP_ERROR', message: '操作失敗，請稍後再試。' };
}

// 一般請求 15 秒逾時；上傳圖片在行動網路上可能較慢，由呼叫端傳入較長的時間。
async function request(path, { timeout = 15000, ...options } = {}) {
  const controller = new AbortController();
  const timer = window.setTimeout(() => controller.abort(), timeout);
  let response;
  try {
    response = await fetch(path, {
      ...options,
      signal: controller.signal,
      credentials: 'same-origin',
      cache: 'no-store',
    });
  } catch {
    const message = controller.signal.aborted
      ? '伺服器回應逾時，無法確認操作結果，請重新載入頁面確認。'
      : '無法確認操作結果，請檢查連線並重新載入頁面。';
    throw new ApiError(0, { code: 'NETWORK_ERROR', message });
  } finally {
    window.clearTimeout(timer);
  }
  const result = (payload, ok = response.ok, status = response.status) => ({ response, payload, ok, status });
  if (response.status === 204 || options.method === 'HEAD') return result({});
  // 成功的 API 一定回 JSON；不是 JSON 就當成伺服器錯誤，不讓呼叫端拿到不完整的資料。
  if (!(response.headers.get('Content-Type') || '').includes('application/json')) {
    const status = response.ok ? 502 : response.status;
    return result(fallbackPayload(status), false, status);
  }
  try {
    return result(await response.json());
  } catch {
    const status = response.ok ? 502 : response.status;
    return result(fallbackPayload(status), false, status);
  }
}

export async function csrf(kind = 'member', refresh = false) {
  if (refresh) tokens.delete(kind);
  if (!refresh && tokens.has(kind)) return tokens.get(kind);
  if (!csrfLoads.has(kind)) {
    csrfLoads.set(
      kind,
      request(prefix(kind) + 'csrf')
        .then(({ response, payload, ok, status }) => {
          if (!ok) throw new ApiError(status, payload, response.headers.get('Retry-After'));
          tokens.set(kind, payload.token);
          configurations.set(kind, payload);
          return payload.token;
        })
        .finally(() => csrfLoads.delete(kind))
    );
  }
  return csrfLoads.get(kind);
}

export function clearCsrf(kind = 'member') {
  tokens.delete(kind);
}
export async function authConfiguration(kind = 'member') {
  await csrf(kind);
  return configurations.get(kind);
}

export async function api(path, { method = 'GET', body, kind = 'member', retry = true, timeout } = {}) {
  if (!path.startsWith('/api/v1/') || path.includes('\\'))
    throw new Error('API path must be a same-origin route.');
  if (!['GET', 'HEAD'].includes(method) && !path.includes('/auth/') && sessionSnapshot(kind)?.changed)
    throw new ApiError(409, {
      code: 'SESSION_CHANGED',
      message: '登入身分已變更，請先保留內容並重新開啟本頁。',
    });
  const headers = { Accept: 'application/json' };
  if (method !== 'GET' && method !== 'HEAD') {
    headers['X-CSRF-Token'] = await csrf(kind);
    if (!(body instanceof FormData)) headers['Content-Type'] = 'application/json';
  }
  const epoch = sessionEpoch(kind);
  const { response, payload, ok, status } = await request(path, {
    method,
    headers,
    body: body === undefined ? undefined : body instanceof FormData ? body : JSON.stringify(body),
    timeout: timeout ?? (body instanceof FormData ? 120000 : 15000),
  });
  recordSessionResponse(path, response, payload, kind, epoch);
  if (!ok) {
    if (retry && payload.code === 'CSRF_INVALID') {
      await csrf(kind, true);
      return api(path, { method, body, kind, retry: false, timeout });
    }
    throw new ApiError(status, payload, response.headers.get('Retry-After'));
  }
  if (payload.csrfToken) tokens.set(kind, payload.csrfToken);
  if (response.status === 204 && /\/auth\/(logout|change-password|reset-password)$/.test(path))
    clearCsrf(kind);
  return payload;
}
