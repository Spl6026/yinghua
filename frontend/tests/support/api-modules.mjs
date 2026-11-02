// 載入真正的 API 模組（網址組法、HTTP 方法都是正式程式），只把最底層的 api() 換成測試提供的函式。
// 舊式的 node:vm 測試可把這些物件放進沙箱，沿用原本對 api(path, options) 的斷言。
import { loadModule } from './load-module.mjs';

const target = {
  api: async path => {
    throw new Error('測試沒有提供 api 替身：' + path);
  },
};
const stubs = { 'core/api-client.js': { api: (...args) => target.api(...args) } };

export const { publicApi } = await loadModule('api/public-api.js', stubs);
export const { operationsApi } = await loadModule('api/operations-api.js', stubs);
export const { authApi } = await loadModule('api/auth-api.js', stubs);

/** 指定這次測試的 api() 替身，回傳可放進沙箱的 API 模組。 */
export function apiModules(api) {
  target.api = api;
  return { publicApi, operationsApi, authApi };
}
