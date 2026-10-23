// [S：E13／G15；E-27] 本人資源薄封裝；沿用既有 Cookie、CSRF 與錯誤邊界。
import { api } from '../core/api-client.js';

export const memberResourcesApi = {
  favorites: (cursor = '', pageSize = 20) => api('/api/v1/me/favorites?' + new URLSearchParams({cursor, pageSize})),
  favoriteStatus: ids => api('/api/v1/me/favorites/status?' + new URLSearchParams({ids:ids.join(',')})),
  saveFavorite: (id, saved) => api('/api/v1/me/favorites/' + encodeURIComponent(id), {method:saved ? 'PUT' : 'DELETE'}),
};
