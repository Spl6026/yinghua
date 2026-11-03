import { element } from '../core/ui.js';

// 行程內容頁的唯讀站點：只顯示順序、時間、備註與地點資訊，修改要進編輯畫面。
// 值只透過 textContent 寫入，不插入使用者提供的 HTML。
export function tripStopView(item, index) {
  const name = item.attraction?.name || item.snapshotName,
    node = element('li', { className: 'itinerary-stop trip-stop-view' }),
    info = element('div', { className: 'stop-info' });
  info.append(element('strong', {}, name));
  if (item.attraction) {
    const meta = [item.attraction.district, item.attraction.typeLabel || item.attraction.category].filter(
      Boolean
    );
    if (meta.length) info.append(element('span', { className: 'trip-stop-meta' }, meta.join('・')));
  } else
    info.append(
      element(
        'p',
        {},
        item.availability === 'unknown' ? '公開資訊更新中。' : '這個地點目前不公開，可在編輯時移除或更換。'
      )
    );
  if (item.note) info.append(element('p', { className: 'trip-stop-note' }, item.note));
  if (item.attraction?.url)
    info.append(
      element('a', { className: 'text-link trip-stop-detail', href: item.attraction.url }, '看介紹 ↗')
    );
  node.append(
    element('span', { className: 'stop-number', 'aria-hidden': 'true' }, String(index + 1).padStart(2, '0')),
    info,
    element('span', { className: 'trip-stop-time' }, item.startTime || '時間未定')
  );
  return node;
}
