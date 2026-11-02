import { api } from '../core/api-client.js';
const base = '/api/v1/me/itineraries';
// 本人行程只由 session 辨識身分，所以 API 不接受 memberId 參數。
export const itinerariesApi = {
  list: (page = 1) => api(base + '?' + new URLSearchParams({ page: String(page), pageSize: '20' })),
  get: id => api(base + '/' + encodeURIComponent(id)),
  create: body => api(base, { method: 'POST', body }),
  save: (id, body) => api(base + '/' + encodeURIComponent(id), { method: 'PUT', body }),
  select: (id, version) =>
    api(base + '/' + encodeURIComponent(id) + '/current', { method: 'PUT', body: { version } }),
  delete: (id, version) => api(base + '/' + encodeURIComponent(id), { method: 'DELETE', body: { version } }),
  apply: (id, routeId, version) =>
    api(base + '/' + encodeURIComponent(id) + '/routes', { method: 'POST', body: { routeId, version } }),
  route: id => api('/api/v1/routes/' + encodeURIComponent(id)),
  routes: () => api('/api/v1/routes?pageSize=1'),
};
