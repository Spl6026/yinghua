// 後台各內容的 API 都是同一組 REST 操作；在這裡統一網址組法與身分，各模組只補自己特有的端點。
import { api } from '../core/api-client.js';

export const adminRequest = (path, options = {}) => api(path, { ...options, kind: 'admin' });

/** list／get／save（有 id 為修改、否則新增）／delete（帶版本號）。 */
export function adminResource(base) {
  const item = id => `${base}/${encodeURIComponent(id)}`;
  return {
    list: query => adminRequest(`${base}?${new URLSearchParams(query)}`),
    get: id => adminRequest(item(id)),
    save: (id, body) => adminRequest(id ? item(id) : base, { method: id ? 'PUT' : 'POST', body }),
    delete: (id, version) => adminRequest(item(id), { method: 'DELETE', body: { version } }),
  };
}

/** 公開頁需要重新產生時（例如上次發布失敗），由有編輯權限的人手動觸發。 */
export const publishRetry = base => () => adminRequest(`${base}/publish-retry`, { method: 'POST', body: {} });

/** 景點、電影與消息／優惠共用的圖片上傳端點。 */
export const uploadMedia = body => adminRequest('/api/v1/admin/media', { method: 'POST', body });
