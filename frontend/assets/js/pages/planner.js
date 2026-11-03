import { plannerView } from '../core/planner-links.js';
import { toast } from '../core/ui.js';
import { initializeTripOverview } from './trip-overview.js';
import { initializeTripDetail } from './trip-detail.js';
import { initializeTripEditor } from './trip-editor.js';

// 行程頁入口：依網址選擇要顯示的畫面（總覽、行程內容或編輯），其餘兩個區塊保持隱藏。
const views = {
  overview: initializeTripOverview,
  detail: initializeTripDetail,
  edit: initializeTripEditor,
};

export function initializePlanner(search = location.search) {
  const { view, tripId, intent } = plannerView(search);
  return views[view]({ tripId, intent });
}

// 初始化途中出錯（例如模組載入後 API 異常）時給使用者看得到的提示，而不是留下沒有反應的頁面。
initializePlanner().catch(error => {
  toast(error?.message || '行程頁載入失敗，請重新整理頁面。');
});
