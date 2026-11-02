// 電影 API；媒體上傳沿用同一個受控的上傳端點。
import { api } from '../core/api-client.js';
const base = '/api/v1/admin/films';
export const filmsApi = {
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
};
