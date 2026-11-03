import { adminRequest, adminResource } from './admin-resource.js';

const base = '/api/v1/admin/routes';
// 路線與候選景點都使用 routes 權限，不需要另外具備景點管理權。
export const recommendedRoutesApi = {
  ...adminResource(base),
  candidates: (q, page) =>
    adminRequest(`${base}/attractions?${new URLSearchParams({ q, page: String(page) })}`),
};
