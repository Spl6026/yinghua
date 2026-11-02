import { api } from '../core/api-client.js';

const base = '/api/v1/admin/';
export const adminApi = {
  list: query => api(base + 'admins?' + new URLSearchParams(query), { kind: 'admin' }),
  get: id => api(base + 'admins/' + encodeURIComponent(id), { kind: 'admin' }),
  create: body => api(base + 'admins', { method: 'POST', body, kind: 'admin' }),
  update: (id, body) =>
    api(base + 'admins/' + encodeURIComponent(id), { method: 'PUT', body, kind: 'admin' }),
  remove: (id, version) =>
    api(base + 'admins/' + encodeURIComponent(id), { method: 'DELETE', body: { version }, kind: 'admin' }),
  roles: () => api(base + 'roles', { kind: 'admin' }),
  catalog: () => api(base + 'permissions/catalog', { kind: 'admin' }),
  matrix: id => api(base + 'admins/' + encodeURIComponent(id) + '/permissions', { kind: 'admin' }),
  saveMatrix: (id, body) =>
    api(base + 'admins/' + encodeURIComponent(id) + '/permissions', { method: 'PUT', body, kind: 'admin' }),
};
