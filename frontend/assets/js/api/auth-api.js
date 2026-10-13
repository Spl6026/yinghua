import { api } from '../core/api-client.js';

export function authApi(kind = 'member') {
  const base = kind === 'admin' ? '/api/v1/admin/auth/' : '/api/v1/auth/';
  return {
    me: () => api(base + 'me', { kind }),
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
