import { api } from '../core/api-client.js';
export const listNews = ({ type = '', page = 1 } = {}) => {
  const params = new URLSearchParams({ page: String(page), pageSize: '20' });
  if (type) params.set('type', type);
  return api('/api/v1/news?' + params);
};
