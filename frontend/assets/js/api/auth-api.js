import { api } from '../core/api-client.js';

// 頁面載入時頁首、收藏按鈕與登入期限監看會同時確認身分；同一時間只送一次請求、共用結果，
// 回應後就清掉，下一次確認仍會重新詢問伺服器，不會拿到過期的身分。
const pendingIdentity = new Map();

export function authApi(kind = 'member') {
  const base = kind === 'admin' ? '/api/v1/admin/auth/' : '/api/v1/auth/';
  return {
    me: () => {
      if (!pendingIdentity.has(kind))
        pendingIdentity.set(
          kind,
          api(base + 'me', { kind }).finally(() => pendingIdentity.delete(kind))
        );
      return pendingIdentity.get(kind);
    },
    login: body => api(base + 'login', { method: 'POST', body, kind }),
    logout: () => api(base + 'logout', { method: 'POST', body: {}, kind }),
    forgot: body => api(base + 'forgot-password', { method: 'POST', body, kind }),
    reset: body => api(base + 'reset-password', { method: 'POST', body, kind }),
    change: body => api(base + 'change-password', { method: 'POST', body, kind }),
    register: body => api('/api/v1/auth/register', { method: 'POST', body }),
    captcha: () => api('/api/v1/auth/captcha'),
    emailCaptcha: email => api('/api/v1/auth/captcha/email', { method: 'POST', body: { email } }),
  };
}
