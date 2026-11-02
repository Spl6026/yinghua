// 同一頁內不同模組之間的通知（Observer）：發送端與接收端只依賴這裡的事件名稱，彼此不必互相 import。
export const FAVORITE_CHANGED = 'member-favorite-change';
// 一般設定與 SEO 設定在同一頁、共用網站名稱與摘要；一區儲存後通知另一區重新讀取。
export const SETTINGS_SECTION_SAVED = 'settings-section-saved';

export const emitPageEvent = (name, detail) => document.dispatchEvent(new CustomEvent(name, { detail }));
export const onPageEvent = (name, listener) => document.addEventListener(name, listener);
