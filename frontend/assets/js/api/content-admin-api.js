import { adminResource, uploadMedia } from './admin-resource.js';

// 只接受兩個明確的主題，避免用任意名稱組出管理端點。
export function contentAdminApi(module) {
  if (!['news', 'offers'].includes(module)) throw new Error('不支援的內容模組。');
  return { ...adminResource('/api/v1/admin/' + module), upload: uploadMedia };
}
const merchants = adminResource('/api/v1/admin/offers/merchants');
export const offerMerchantsApi = { list: merchants.list, save: merchants.save };
