import { api } from '../core/api-client.js';
export const listOffers = ({ page = 1 } = {}) =>
  api('/api/v1/offers?' + new URLSearchParams({ page: String(page), pageSize: '20' }));
