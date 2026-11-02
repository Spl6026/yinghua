// 電影 API；媒體上傳沿用同一個受控的上傳端點。
import { adminResource, publishRetry, uploadMedia } from './admin-resource.js';

const base = '/api/v1/admin/films';
export const filmsApi = { ...adminResource(base), retry: publishRetry(base), upload: uploadMedia };
