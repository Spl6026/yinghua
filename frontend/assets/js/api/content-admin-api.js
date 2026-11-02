import { api } from '../core/api-client.js';

// 只接受兩個明確的主題，避免用任意名稱組出管理端點。
export function contentAdminApi(module) {
  if (!['news', 'offers'].includes(module)) throw new Error('不支援的內容模組。');
  const base = '/api/v1/admin/' + module;
  return {
    list: query => api(base + '?' + new URLSearchParams(query), { kind: 'admin' }),
    get: id => api(base + '/' + encodeURIComponent(id), { kind: 'admin' }),
    save: (id, body) =>
      api(id ? base + '/' + encodeURIComponent(id) : base, {
        method: id ? 'PUT' : 'POST',
        body,
        kind: 'admin',
      }),
    delete: (id, version) =>
      api(base + '/' + encodeURIComponent(id), { method: 'DELETE', body: { version }, kind: 'admin' }),
    upload: body => api('/api/v1/admin/media', { method: 'POST', body, kind: 'admin' }),
  };
}
export const offerMerchantsApi = {
  list: query => api('/api/v1/admin/offers/merchants?' + new URLSearchParams(query), { kind: 'admin' }),
  save: (id, body) =>
    api('/api/v1/admin/offers/merchants' + (id ? '/' + encodeURIComponent(id) : ''), {
      method: id ? 'PUT' : 'POST',
      body,
      kind: 'admin',
    }),
};
