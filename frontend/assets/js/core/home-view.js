import {escapeHtml as e,contentDate} from './content-view.js';

// [S：功能規格 5；E-26] 原型首頁三列，活動時間缺少時展示發布日期，不捏造活動時間。
export function homeNewsRows(items) {
  return items.map(item=>{
    const stamp=item.eventStartAt||item.publishAt;
    const date=new Date(stamp);
    const day=new Intl.DateTimeFormat('en-US',{timeZone:'Asia/Taipei',day:'2-digit'}).format(date);
    const month=new Intl.DateTimeFormat('en-US',{timeZone:'Asia/Taipei',month:'short'}).format(date).toUpperCase();
    const weekday=new Intl.DateTimeFormat('en-US',{timeZone:'Asia/Taipei',weekday:'short'}).format(date).toUpperCase();
    const label=item.eventStartAt?'活動日期':'發布日期';
    // [S L10；E] 首頁消息入口與消息列表一致，預先說明另開分頁。
    return `<a class="event-row reveal in-view" href="${e(item.url)}" target="_blank" rel="noopener noreferrer" aria-label="閱讀${e(item.title)}（另開新分頁）"><span class="event-date" aria-label="${label} ${e(contentDate(stamp))}"><b>${e(day)}</b><small>${e(month+' / '+weekday)}</small></span><span class="event-type">${e(item.eventLabel||(item.type==='event'?'活動消息':'城市消息'))}</span><strong>${e(item.title)}</strong><span class="event-location">${e(item.eventLocation)}</span><span class="event-arrow" aria-hidden="true">↗</span></a>`;
  }).join('') || '<p class="empty-results">目前沒有活動與消息。</p>';
}
