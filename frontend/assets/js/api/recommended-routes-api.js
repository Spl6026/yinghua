import { api } from '../core/api-client.js';

const base = '/api/v1/admin/routes';
// [S H14/H24；E-32] 路線與候選景點使用 routes 權限，無須景點管理權。
export const recommendedRoutesApi = {
  list: query => api(base + '?' + new URLSearchParams(query), { kind: 'admin' }),
  get: id => api(base + '/' + encodeURIComponent(id), { kind: 'admin' }),
  save: (id, body) =>
    api(base + (id ? '/' + encodeURIComponent(id) : ''), {
      method: id ? 'PUT' : 'POST',
      body,
      kind: 'admin',
    }),
  delete: (id, version) =>
    api(base + '/' + encodeURIComponent(id), { method: 'DELETE', body: { version }, kind: 'admin' }),
  candidates: (q, page) =>
    api(base + '/attractions?' + new URLSearchParams({ q, page: String(page) }), { kind: 'admin' }),
};
