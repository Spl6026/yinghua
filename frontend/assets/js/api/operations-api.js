// 後台總覽、操作紀錄、會員查詢與一般設定。
import { adminRequest } from './admin-resource.js';

export const operationsApi = {
  dashboard: () => adminRequest('/api/v1/admin/dashboard'),
  auditLogs: page => adminRequest(`/api/v1/admin/audit-logs?${new URLSearchParams({ page: String(page) })}`),
  members: query => adminRequest(`/api/v1/admin/members?${new URLSearchParams(query)}`),
  settings: () => adminRequest('/api/v1/admin/settings'),
  saveSettings: body => adminRequest('/api/v1/admin/settings', { method: 'PUT', body }),
};
