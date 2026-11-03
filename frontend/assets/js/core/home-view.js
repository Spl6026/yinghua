import { escapeHtml, contentDate } from './content-view.js';
import { taipeiEnglish, taipeiFormatter } from './taipei-time.js';

// 原型首頁的三列內容；缺少活動時間時改顯示發布日期，不捏造活動時間。
export function homeNewsRows(items) {
  return (
    items
      .map(item => {
        const stamp = item.eventStartAt || item.publishAt;
        const date = new Date(stamp);
        const day = taipeiFormatter('en-US', { day: '2-digit' }).format(date);
        const month = taipeiEnglish(date, { month: 'short' });
        const weekday = taipeiEnglish(date, { weekday: 'short' });
        const label = item.eventStartAt ? '活動日期' : '發布日期';
        // 首頁的消息入口與消息列表一致，事先告知會另開分頁。
        return `<a class="event-row reveal in-view" href="${escapeHtml(item.url)}" target="_blank" rel="noopener noreferrer" aria-label="閱讀${escapeHtml(item.title)}（另開新分頁）"><span class="event-date" aria-label="${label} ${escapeHtml(contentDate(stamp))}"><b>${escapeHtml(day)}</b><small>${escapeHtml(month + ' / ' + weekday)}</small></span><span class="event-type">${escapeHtml(item.eventLabel || (item.type === 'event' ? '活動消息' : '城市消息'))}</span><strong>${escapeHtml(item.title)}</strong><span class="event-location">${escapeHtml(item.eventLocation)}</span><span class="event-arrow" aria-hidden="true">↗</span></a>`;
      })
      .join('') || '<p class="empty-results">目前沒有活動與消息。</p>'
  );
}
