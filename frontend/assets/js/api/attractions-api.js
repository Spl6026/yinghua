// 景點、媒體與地圖網址解析共用的 API 介面。
import { adminRequest, adminResource, publishRetry, uploadMedia } from './admin-resource.js';

const base = '/api/v1/admin/attractions';
export const attractionsApi = {
  ...adminResource(base),
  samples: () => adminRequest(`${base}/samples`),
  retry: publishRetry(base),
  upload: uploadMedia,
  resolve: body => adminRequest('/api/v1/admin/places/resolve-map-url', { method: 'POST', body }),
};
