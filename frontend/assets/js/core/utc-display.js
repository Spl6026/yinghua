import { formatTaipeiDateTime } from './taipei-time.js';
// 資料庫中不含時區偏移的時間是 UTC；顯示時一律轉為台北時間，不依訪客裝置的時區。
export function formatUtcDateTime(value) {
  if (typeof value !== 'string' || !value.trim()) return '—';
  const stamp = /^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}:\d{2}(?:\.\d+)?$/.test(value)
    ? value.replace(' ', 'T') + 'Z'
    : value;
  const date = new Date(stamp);
  if (!Number.isFinite(date.getTime())) return '—';
  return formatTaipeiDateTime(date);
}
