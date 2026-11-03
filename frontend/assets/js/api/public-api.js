// 公開頁面讀取的 API；不需要登入，也不帶 CSRF。
import { api } from '../core/api-client.js';

const get = (path, query) => api(`${path}?${new URLSearchParams(query)}`);

export const publicApi = {
  attractions: query => get('/api/v1/attractions', query),
  films: query => get('/api/v1/films', query),
  search: query => get('/api/v1/search', query),
};
