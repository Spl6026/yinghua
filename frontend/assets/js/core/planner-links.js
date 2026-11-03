// 行程頁的三個畫面共用 /planner.html，以網址參數區分，上一頁、重新整理與分享連結都會回到同一個畫面：
// 無參數是行程總覽、?trip= 是行程內容（唯讀）、?edit= 是編輯畫面。
// ?place=／?route= 是從景點或推薦行程帶來的待辦，沒有指定行程時先在總覽選擇要加到哪一份。
const id = value => encodeURIComponent(value);

export const tripOverviewUrl = (intent = {}) => '/planner.html' + query(intent);
export const tripDetailUrl = tripId => `/planner.html?trip=${id(tripId)}`;
export const tripEditUrl = (tripId, intent = {}) => `/planner.html?edit=${id(tripId)}${query(intent, '&')}`;

function query({ place, route } = {}, prefix = '?') {
  const params = new URLSearchParams();
  if (place) params.set('place', place);
  if (route) params.set('route', route);
  const text = params.toString();
  return text ? prefix + text : '';
}

/** 依網址決定要顯示哪個畫面。 */
export function plannerView(search) {
  const params = new URLSearchParams(search);
  const intent = { place: params.get('place') || '', route: params.get('route') || '' };
  if (params.get('edit')) return { view: 'edit', tripId: params.get('edit'), intent };
  if (params.get('trip')) return { view: 'detail', tripId: params.get('trip'), intent };
  return { view: 'overview', tripId: null, intent };
}

/** 總覽與內容頁顯示用的行程名稱與日期。 */
export const tripTitle = trip => trip?.title || '未命名行程';
export const tripDateLabel = trip => trip?.tripDate || '未定日期';
