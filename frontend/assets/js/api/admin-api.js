import { adminRequest, adminResource } from './admin-resource.js';

const base = '/api/v1/admin/';
const permissions = id => `${base}admins/${encodeURIComponent(id)}/permissions`;
export const adminApi = {
  ...adminResource(base + 'admins'),
  roles: () => adminRequest(base + 'roles'),
  catalog: () => adminRequest(base + 'permissions/catalog'),
  matrix: id => adminRequest(permissions(id)),
  saveMatrix: (id, body) => adminRequest(permissions(id), { method: 'PUT', body }),
};
