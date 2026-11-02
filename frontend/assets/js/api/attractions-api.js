// 景點、媒體與地圖網址解析共用的 API 介面。
import { api } from '../core/api-client.js';
const base = '/api/v1/admin/attractions';
export const attractionsApi = {
  samples: () => api(`${base}/samples`, { kind: 'admin' }),
  list: parameters => api(`${base}?${new URLSearchParams(parameters)}`, { kind: 'admin' }),
  get: id => api(`${base}/${encodeURIComponent(id)}`, { kind: 'admin' }),
  save: (id, body) =>
    api(id ? `${base}/${encodeURIComponent(id)}` : base, {
      method: id ? 'PUT' : 'POST',
      body,
      kind: 'admin',
    }),
  delete: (id, version) =>
    api(`${base}/${encodeURIComponent(id)}`, { method: 'DELETE', body: { version }, kind: 'admin' }),
  retry: () => api(`${base}/publish-retry`, { method: 'POST', body: {}, kind: 'admin' }),
  upload: body => api('/api/v1/admin/media', { method: 'POST', body, kind: 'admin' }),
  resolve: body => api('/api/v1/admin/places/resolve-map-url', { method: 'POST', body, kind: 'admin' }),
};
